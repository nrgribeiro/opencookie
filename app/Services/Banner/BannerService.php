<?php

namespace App\Services\Banner;

use App\Enums\BannerStatus;
use App\Http\Controllers\Ingest\ConfigController;
use App\Models\BannerConfig;
use App\Models\Domain;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class BannerService
{
    /** Required content keys per language (BAN-2 enforces rejectAll exists). */
    public const REQUIRED_CONTENT_KEYS = ['title', 'body', 'acceptAll', 'rejectAll', 'customize'];

    public const DEFAULT_CONSENT_MODE_MAP = [
        'analytics_storage' => ['statistics'],
        'ad_storage' => ['marketing'],
        'ad_user_data' => ['marketing'],
        'ad_personalization' => ['marketing'],
    ];

    /**
     * Return the editable draft for a domain, creating one (cloned from the
     * published version, or scaffolded) when none exists.
     */
    public function draftFor(Domain $domain): BannerConfig
    {
        $draft = $domain->bannerConfigs()
            ->where('status', BannerStatus::Draft)
            ->latest('version')
            ->first();

        if ($draft) {
            return $draft;
        }

        $published = $domain->bannerConfigs()
            ->where('status', BannerStatus::Published)
            ->latest('version')
            ->first();

        if ($published) {
            return $domain->bannerConfigs()->create([
                'version' => $this->nextVersion($domain),
                'status' => BannerStatus::Draft,
                'layout' => $published->layout,
                'content' => $published->content,
                'languages' => $published->languages,
                'default_language' => $published->default_language,
                'policy_url' => $published->policy_url,
                'consent_mode_map' => $published->consent_mode_map,
                'published_at' => null,
            ]);
        }

        return $domain->bannerConfigs()->create($this->scaffold());
    }

    /**
     * Publish a draft: validate completeness, archive the current published
     * version, then mark this one published.
     */
    public function publish(BannerConfig $draft): void
    {
        $this->assertPublishable($draft);

        $draft->domain->bannerConfigs()
            ->where('status', BannerStatus::Published)
            ->update(['status' => BannerStatus::Archived]);

        $draft->update([
            'status' => BannerStatus::Published,
            'published_at' => now(),
        ]);

        // Invalidate the public config cache so the SDK picks up changes fast.
        Cache::forget(ConfigController::cacheKey($draft->domain->domain_uid));
    }

    public function nextVersion(Domain $domain): int
    {
        return (int) $domain->bannerConfigs()->max('version') + 1;
    }

    /**
     * BAN-2 / BAN-5 — completeness checks enforced only at publish time.
     */
    private function assertPublishable(BannerConfig $draft): void
    {
        $errors = [];

        if (blank($draft->policy_url)) {
            $errors['banner'][] = 'A privacy/cookie policy URL is required before publishing (informed consent).';
        }

        foreach (($draft->languages ?? []) as $lang) {
            $content = $draft->content[$lang] ?? [];
            foreach (self::REQUIRED_CONTENT_KEYS as $key) {
                if (blank($content[$key] ?? null)) {
                    $errors['banner'][] = "Missing \"{$key}\" text for language \"{$lang}\".";
                }
            }
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function scaffold(): array
    {
        return [
            'version' => 1,
            'status' => BannerStatus::Draft,
            'layout' => [
                'type' => 'box',
                'position' => 'bottom-left',
                'theme' => 'light',
                'colors' => ['accent' => '#2563eb'],
                'logo' => null,
            ],
            'content' => [
                'en' => [
                    'title' => 'We use cookies',
                    'body' => 'We use cookies to improve your experience. You can accept or reject non-essential cookies.',
                    'acceptAll' => 'Accept all',
                    'rejectAll' => 'Reject all',
                    'customize' => 'Customize',
                ],
            ],
            'languages' => ['en'],
            'default_language' => 'en',
            'policy_url' => null,
            'consent_mode_map' => self::DEFAULT_CONSENT_MODE_MAP,
            'published_at' => null,
        ];
    }
}
