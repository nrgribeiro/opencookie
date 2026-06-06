<?php

namespace App\Services\Scanner;

use App\Enums\CookieCategory;
use App\Models\CookieClassification;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

/**
 * In-house cookie classification (functional-spec §8). Primary source is the
 * cookie_classifications table, populated from the Open Cookie Database via
 * `cookies:import-ocd`. When that table is empty or unavailable it falls back
 * to a built-in static map so scans still classify common cookies offline.
 */
class CookieClassifier
{
    /** Exact / prefix cookie-name → category (fallback when the DB is empty). */
    private const NAME_MAP = [
        '_ga' => CookieCategory::Statistics,
        '_gid' => CookieCategory::Statistics,
        '_gat' => CookieCategory::Statistics,
        '_gcl_au' => CookieCategory::Marketing,
        '_fbp' => CookieCategory::Marketing,
        'fr' => CookieCategory::Marketing,
        '_hjSessionUser' => CookieCategory::Statistics,
        'IDE' => CookieCategory::Marketing,
        'NID' => CookieCategory::Marketing,
        'csrf_token' => CookieCategory::Necessary,
        'XSRF-TOKEN' => CookieCategory::Necessary,
        'laravel_session' => CookieCategory::Necessary,
        'PHPSESSID' => CookieCategory::Necessary,
    ];

    /** Host substring → category (fallback). */
    private const HOST_MAP = [
        'google-analytics.com' => CookieCategory::Statistics,
        'googletagmanager.com' => CookieCategory::Statistics,
        'doubleclick.net' => CookieCategory::Marketing,
        'facebook.net' => CookieCategory::Marketing,
        'hotjar.com' => CookieCategory::Statistics,
        'ads.linkedin.com' => CookieCategory::Marketing,
    ];

    /**
     * Exact / prefix cookie-name → provider (fallback when the DB is empty).
     * GA's _ga* cookies are first-party by domain but set by Google's JS, so
     * they must still be attributed to "Google Analytics".
     */
    private const PROVIDER_NAME_MAP = [
        '_ga' => 'Google Analytics',
        '_gid' => 'Google Analytics',
        '_gat' => 'Google Analytics',
        '_gcl_au' => 'Google Ads',
        '_fbp' => 'Meta (Facebook)',
        'fr' => 'Meta (Facebook)',
        '_hjSessionUser' => 'Hotjar',
        'IDE' => 'Google DoubleClick',
        'NID' => 'Google',
    ];

    /** Exact-name index: lower(name) → list of classifications. */
    private ?array $exact = null;

    /** Wildcard (prefix) classifications. @var array<int, CookieClassification>|null */
    private ?array $wildcards = null;

    public function classify(string $name, ?string $sourceDomain = null): CookieCategory
    {
        return $this->lookup($name, $sourceDomain)?->category
            ?? $this->fallbackCategory($name, $sourceDomain);
    }

    /**
     * Best-effort provider name for a cookie: DB classification first, then the
     * built-in prefix map. Returns null when nothing matches.
     */
    public function provider(string $name, ?string $sourceDomain = null): ?string
    {
        $fromDb = $this->lookup($name, $sourceDomain)?->provider;
        if ($fromDb) {
            return $fromDb;
        }

        foreach (self::PROVIDER_NAME_MAP as $known => $provider) {
            if ($name === $known || Str::startsWith($name, $known)) {
                return $provider;
            }
        }

        return null;
    }

    /**
     * Best-effort purpose/description for a cookie, from the DB classification.
     * Returns null when there is no DB match or the match has no description.
     */
    public function purpose(string $name, ?string $sourceDomain = null): ?string
    {
        $purpose = $this->lookup($name, $sourceDomain)?->purpose;

        return $purpose !== null && $purpose !== '' ? $purpose : null;
    }

    /** Documented retention period (e.g. "2 years", "session"), DB only. */
    public function retention(string $name, ?string $sourceDomain = null): ?string
    {
        $v = $this->lookup($name, $sourceDomain)?->retention;

        return $v !== null && $v !== '' ? $v : null;
    }

    /** Data controller responsible for the cookie (e.g. "Google"), DB only. */
    public function dataController(string $name, ?string $sourceDomain = null): ?string
    {
        $v = $this->lookup($name, $sourceDomain)?->data_controller;

        return $v !== null && $v !== '' ? $v : null;
    }

    /** Provider's privacy / GDPR rights portal URL, DB only. */
    public function gdprPortalUrl(string $name, ?string $sourceDomain = null): ?string
    {
        $v = $this->lookup($name, $sourceDomain)?->gdpr_portal_url;

        return $v !== null && $v !== '' ? $v : null;
    }

    /**
     * Full classification record (category + provider + purpose) for the given
     * cookie, from the DB only. Returns null when there is no DB match.
     */
    public function lookup(string $name, ?string $sourceDomain = null): ?CookieClassification
    {
        $this->load();

        $candidates = $this->exact[Str::lower($name)] ?? [];
        $match = $this->pickByDomain($candidates, $sourceDomain);
        if ($match) {
            return $match;
        }

        foreach ($this->wildcards ?? [] as $entry) {
            if ($entry->name !== '' && Str::startsWith($name, $entry->name)
                && $this->domainMatches($entry, $sourceDomain)) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * @param  array<int, CookieClassification>  $candidates
     */
    private function pickByDomain(array $candidates, ?string $sourceDomain): ?CookieClassification
    {
        $domainless = null;
        foreach ($candidates as $entry) {
            if ($entry->domain && $sourceDomain && Str::contains($sourceDomain, $entry->domain)) {
                return $entry; // domain-specific match wins
            }
            if (! $entry->domain) {
                $domainless = $domainless ?? $entry;
            }
        }

        return $domainless;
    }

    private function domainMatches(CookieClassification $entry, ?string $sourceDomain): bool
    {
        if (! $entry->domain) {
            return true;
        }

        return $sourceDomain !== null && Str::contains($sourceDomain, $entry->domain);
    }

    private function fallbackCategory(string $name, ?string $sourceDomain): CookieCategory
    {
        foreach (self::NAME_MAP as $known => $category) {
            if ($name === $known || Str::startsWith($name, $known)) {
                return $category;
            }
        }

        if ($sourceDomain) {
            foreach (self::HOST_MAP as $host => $category) {
                if (Str::contains($sourceDomain, $host)) {
                    return $category;
                }
            }
        }

        return CookieCategory::Unclassified;
    }

    /** Lazily load + index the classification table (once per instance). */
    private function load(): void
    {
        if ($this->exact !== null) {
            return;
        }

        $this->exact = [];
        $this->wildcards = [];

        try {
            /** @var Collection<int, CookieClassification> $rows */
            $rows = CookieClassification::query()->get();
        } catch (Throwable) {
            return; // table missing → fallback only
        }

        foreach ($rows as $row) {
            if ($row->is_wildcard) {
                $this->wildcards[] = $row;

                continue;
            }
            $this->exact[Str::lower($row->name)][] = $row;
        }
    }
}
