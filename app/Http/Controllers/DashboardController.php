<?php

namespace App\Http\Controllers;

use App\Enums\ConsentMethod;
use App\Enums\CookieCategory;
use App\Models\ConsentRecord;
use App\Models\Domain;
use App\Services\DomainCompliance;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * US-DASH-1..4 — per-domain consent metrics, scan summary, health checklist,
 * and recent consent log preview. Free tier = 1 domain per account, so the
 * dashboard shows that domain (or an empty state).
 */
class DashboardController extends Controller
{
    private const RECENT_RECORDS = 10;

    private const ALLOWED_RANGES = [7, 30, 90];

    private const DEFAULT_RANGE = 30;

    private const NON_NECESSARY = ['preferences', 'statistics', 'marketing'];

    public function index(Request $request, DomainCompliance $compliance): Response
    {
        $domain = $request->user()->domains()
            ->with(['publishedBanner', 'notificationSetting'])
            ->latest('id')
            ->first();

        if (! $domain) {
            return Inertia::render('dashboard', [
                'hasDomain' => false,
            ]);
        }

        $days = (int) $request->integer('days', self::DEFAULT_RANGE);
        if (! in_array($days, self::ALLOWED_RANGES, true)) {
            $days = self::DEFAULT_RANGE;
        }

        $from = CarbonImmutable::now()->subDays($days)->startOfDay();

        return Inertia::render('dashboard', [
            'hasDomain' => true,
            'domain' => [
                'id' => $domain->domain_uid,
                'hostname' => $domain->hostname,
                'verifyStatus' => $domain->verify_status->value,
                'bannerLive' => $domain->banner_live,
                'lastScannedAt' => $domain->last_scanned_at?->toIso8601String(),
            ],
            'rangeDays' => $days,
            'rangeOptions' => self::ALLOWED_RANGES,
            'metrics' => $this->consentMetrics($domain, $from),
            'scanSummary' => $this->scanSummary($domain),
            'health' => $compliance->checklist($domain),
            'recent' => $this->recent($domain),
        ]);
    }

    /**
     * US-DASH-1 — accept/reject/custom rates, per-category opt-in, impressions.
     *
     * @return array<string, mixed>
     */
    private function consentMetrics(Domain $domain, CarbonImmutable $from): array
    {
        $methodCounts = $domain->consentRecords()
            ->where('created_at', '>=', $from)
            ->selectRaw('method, count(*) as c')
            ->groupBy('method')
            ->pluck('c', 'method')
            ->all();

        $total = array_sum($methodCounts);

        $categoryGrants = array_fill_keys(self::NON_NECESSARY, 0);

        $domain->consentRecords()
            ->where('created_at', '>=', $from)
            ->select('categories')
            ->chunk(1000, function ($chunk) use (&$categoryGrants): void {
                foreach ($chunk as $record) {
                    /** @var ConsentRecord $record */
                    $cats = (array) $record->categories;
                    foreach (self::NON_NECESSARY as $key) {
                        if (! empty($cats[$key])) {
                            $categoryGrants[$key]++;
                        }
                    }
                }
            });

        $impressions = (int) $domain->bannerImpressions()
            ->where('day', '>=', $from->toDateString())
            ->sum('count');

        return [
            'total' => $total,
            'impressions' => $impressions,
            'methods' => [
                'acceptAll' => (int) ($methodCounts[ConsentMethod::AcceptAll->value] ?? 0),
                'rejectAll' => (int) ($methodCounts[ConsentMethod::RejectAll->value] ?? 0),
                'custom' => (int) ($methodCounts[ConsentMethod::Custom->value] ?? 0),
            ],
            'categories' => collect(self::NON_NECESSARY)->mapWithKeys(fn ($k) => [
                $k => [
                    'granted' => $categoryGrants[$k],
                    'percent' => $total > 0 ? round(($categoryGrants[$k] / $total) * 100, 1) : 0,
                ],
            ])->all(),
        ];
    }

    /**
     * US-DASH-2 — totals per category + unclassified.
     *
     * @return array<string, mixed>
     */
    private function scanSummary(Domain $domain): array
    {
        $rows = $domain->cookies()
            ->selectRaw('category, count(*) as c')
            ->groupBy('category')
            ->pluck('c', 'category')
            ->all();

        return [
            'lastScannedAt' => $domain->last_scanned_at?->toIso8601String(),
            'byCategory' => collect(CookieCategory::cases())->mapWithKeys(fn ($c) => [
                $c->value => (int) ($rows[$c->value] ?? 0),
            ])->all(),
            'total' => array_sum($rows),
            'unclassified' => (int) ($rows[CookieCategory::Unclassified->value] ?? 0),
        ];
    }

    /**
     * US-DASH-4 — most recent consent records.
     *
     * @return array<int, array<string, mixed>>
     */
    private function recent(Domain $domain): array
    {
        return $domain->consentRecords()
            ->orderByDesc('created_at')
            ->limit(self::RECENT_RECORDS)
            ->get()
            ->map(fn (ConsentRecord $r) => [
                'consentId' => $r->consent_id,
                'createdAt' => $r->created_at?->toIso8601String(),
                'method' => $r->method->value,
                'language' => $r->language,
            ])
            ->all();
    }
}
