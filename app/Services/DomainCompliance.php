<?php

namespace App\Services;

use App\Enums\BannerStatus;
use App\Enums\CookieCategory;
use App\Models\Domain;
use Carbon\CarbonImmutable;

/**
 * US-DASH-3 / US-ADMIN-3 — the per-domain compliance health checklist.
 * Shared by the owner dashboard and the admin overview so both judge
 * compliance identically.
 */
class DomainCompliance
{
    public const SCAN_FRESH_DAYS = 60;

    /**
     * @return array{checklist: array<int, array{key: string, label: string, ok: bool, hint: string|null}>, isCompliant: bool}
     */
    public function evaluate(Domain $domain): array
    {
        $checklist = $this->checklist($domain);
        $isCompliant = collect($checklist)->every(fn (array $item) => $item['ok']);

        return [
            'checklist' => $checklist,
            'isCompliant' => $isCompliant,
        ];
    }

    /**
     * @return array<int, array{key: string, label: string, ok: bool, hint: string|null}>
     */
    public function checklist(Domain $domain): array
    {
        $banner = $domain->publishedBanner;
        $hasReject = false;
        $hasPolicy = false;

        if ($banner) {
            $content = is_array($banner->content) ? $banner->content : [];
            $hasReject = collect($content)->contains(
                fn ($lang) => is_array($lang) && ! empty($lang['rejectAll']),
            );
            $hasPolicy = ! empty($banner->policy_url);
        }

        $recentScan = $domain->last_scanned_at
            && $domain->last_scanned_at->greaterThan(CarbonImmutable::now()->subDays(self::SCAN_FRESH_DAYS));

        $unclassified = $domain->cookies()
            ->where('category', CookieCategory::Unclassified)
            ->count();

        return [
            [
                'key' => 'banner_live',
                'label' => 'Banner is live',
                'ok' => (bool) $domain->banner_live && $banner?->status === BannerStatus::Published,
                'hint' => $domain->banner_live ? null : 'Publish your banner from the builder.',
            ],
            [
                'key' => 'reject_button',
                'label' => 'Reject button present',
                'ok' => $hasReject,
                'hint' => $hasReject ? null : 'Add an equal-prominence Reject button in the banner.',
            ],
            [
                'key' => 'policy_linked',
                'label' => 'Privacy/cookie policy linked',
                'ok' => $hasPolicy,
                'hint' => $hasPolicy ? null : 'Set a policy URL in the banner builder.',
            ],
            [
                'key' => 'scan_recent',
                'label' => sprintf('Scan within %d days', self::SCAN_FRESH_DAYS),
                'ok' => $recentScan,
                'hint' => $recentScan ? null : 'Run a fresh scan to keep disclosures current.',
            ],
            [
                'key' => 'no_unclassified',
                'label' => 'No unclassified cookies',
                'ok' => $unclassified === 0,
                'hint' => $unclassified === 0
                    ? null
                    : sprintf('%d cookie(s) need a category.', $unclassified),
            ],
        ];
    }
}
