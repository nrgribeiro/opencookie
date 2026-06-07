<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DomainVerifyStatus;
use App\Http\Controllers\Controller;
use App\Models\ConsentRecord;
use App\Models\Domain;
use App\Models\Scan;
use App\Models\Tier;
use App\Models\User;
use App\Services\DomainCompliance;
use Inertia\Inertia;
use Inertia\Response;

/**
 * US-ADMIN-2/3 — platform overview: totals, tier breakdown, compliance
 * signal (non-compliant domains with failing checks), recent activity.
 */
class DashboardController extends Controller
{
    private const RECENT_LIMIT = 10;

    public function index(DomainCompliance $compliance): Response
    {
        $domains = Domain::with(['publishedBanner', 'user:id,name,email'])->get();

        $nonCompliant = [];
        $compliantCount = 0;

        foreach ($domains as $domain) {
            $result = $compliance->evaluate($domain);

            if ($result['isCompliant']) {
                $compliantCount++;

                continue;
            }

            $nonCompliant[] = [
                'id' => $domain->domain_uid,
                'hostname' => $domain->hostname,
                'owner' => $domain->user ? [
                    'id' => $domain->user->id,
                    'name' => $domain->user->name,
                    'email' => $domain->user->email,
                ] : null,
                'failing' => collect($result['checklist'])
                    ->reject(fn (array $i) => $i['ok'])
                    ->map(fn (array $i) => ['key' => $i['key'], 'label' => $i['label']])
                    ->values()
                    ->all(),
            ];
        }

        return Inertia::render('admin/dashboard', [
            'stats' => [
                'users' => User::count(),
                'domains' => $domains->count(),
                'verifiedDomains' => $domains->where('verify_status', DomainVerifyStatus::Verified)->count(),
                'bannersLive' => $domains->where('banner_live', true)->count(),
                'scans' => Scan::count(),
                'consentRecords' => ConsentRecord::count(),
                'compliantDomains' => $compliantCount,
                'nonCompliantDomains' => count($nonCompliant),
            ],
            'tiers' => Tier::withCount('users')->orderBy('id')->get()
                ->map(fn (Tier $t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'slug' => $t->slug,
                    'users' => $t->users_count,
                ]),
            'nonCompliant' => $nonCompliant,
            'recentUsers' => User::latest()->limit(self::RECENT_LIMIT)->get()
                ->map(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'createdAt' => $u->created_at?->toIso8601String(),
                ]),
            'recentScans' => Scan::with('domain:id,hostname')->latest()->limit(self::RECENT_LIMIT)->get()
                ->map(fn (Scan $s) => [
                    'id' => $s->id,
                    'hostname' => $s->domain?->hostname,
                    'status' => $s->status->value,
                    'finishedAt' => $s->finished_at?->toIso8601String(),
                ]),
        ]);
    }
}
