<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExportConsentLogsRequest;
use App\Models\ConsentRecord;
use App\Models\Domain;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * US-LOG-3 / US-DASH-4 — consent log preview + CSV export.
 *
 * Records are append-only (see [[consent-records-immutable]]). Export streams to
 * avoid loading the full 24-month range into memory.
 */
class ConsentLogController extends Controller
{
    private const CSV_COLUMNS = [
        'created_at',
        'consent_id',
        'method',
        'categories',
        'banner_version',
        'policy_version',
        'consent_text_hash',
        'ip_hash',
        'user_agent',
        'language',
        'expires_at',
    ];

    public function index(Request $request, Domain $domain): Response
    {
        $this->authorize('view', $domain);

        $recent = $domain->consentRecords()
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn (ConsentRecord $r) => [
                'consentId' => $r->consent_id,
                'createdAt' => $r->created_at?->toIso8601String(),
                'method' => $r->method->value,
                'categories' => $r->categories,
                'bannerVersion' => $r->banner_version,
                'policyVersion' => $r->policy_version,
                'language' => $r->language,
            ]);

        return Inertia::render('domains/consent-logs', [
            'domain' => [
                'id' => $domain->domain_uid,
                'hostname' => $domain->hostname,
            ],
            'records' => $recent,
            'totalCount' => $domain->consentRecords()->count(),
        ]);
    }

    public function export(ExportConsentLogsRequest $request, Domain $domain): StreamedResponse
    {
        $this->authorize('view', $domain);

        $from = $request->validated('from')
            ? CarbonImmutable::parse($request->validated('from'))->startOfDay()
            : null;
        $to = $request->validated('to')
            ? CarbonImmutable::parse($request->validated('to'))->endOfDay()
            : null;

        $filename = sprintf(
            'consent-logs-%s-%s.csv',
            $domain->domain_uid,
            now()->format('Ymd-His'),
        );

        return response()->streamDownload(function () use ($domain, $from, $to): void {
            $out = fopen('php://output', 'wb');
            fputcsv($out, self::CSV_COLUMNS);

            $domain->consentRecords()
                ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
                ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
                ->orderBy('created_at')
                ->chunk(1000, function ($chunk) use ($out): void {
                    foreach ($chunk as $r) {
                        /** @var ConsentRecord $r */
                        fputcsv($out, [
                            $r->created_at?->toIso8601String(),
                            $r->consent_id,
                            $r->method->value,
                            json_encode($r->categories, JSON_UNESCAPED_SLASHES),
                            $r->banner_version,
                            $r->policy_version,
                            $r->consent_text_hash,
                            $r->ip_hash,
                            $r->user_agent,
                            $r->language,
                            $r->expires_at?->toIso8601String(),
                        ]);
                    }
                });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=utf-8',
        ]);
    }
}
