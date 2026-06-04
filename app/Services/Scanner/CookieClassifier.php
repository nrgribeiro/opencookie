<?php

namespace App\Services\Scanner;

use App\Enums\CookieCategory;
use Illuminate\Support\Str;

/**
 * In-house cookie classification (functional-spec §8 — built, seeded from the
 * Open Cookie Database). MVP uses a small static map by cookie name and by
 * known third-party host; unknown entries fall through to Unclassified.
 */
class CookieClassifier
{
    /** Exact cookie-name → category. */
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

    /** Host substring → category. */
    private const HOST_MAP = [
        'google-analytics.com' => CookieCategory::Statistics,
        'googletagmanager.com' => CookieCategory::Statistics,
        'doubleclick.net' => CookieCategory::Marketing,
        'facebook.net' => CookieCategory::Marketing,
        'hotjar.com' => CookieCategory::Statistics,
        'ads.linkedin.com' => CookieCategory::Marketing,
    ];

    public function classify(string $name, ?string $sourceDomain = null): CookieCategory
    {
        // Prefix match handles _ga_* style variants.
        foreach (self::NAME_MAP as $known => $category) {
            if ($name === $known || Str::startsWith($name, $known.'_') || Str::startsWith($name, $known)) {
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
}
