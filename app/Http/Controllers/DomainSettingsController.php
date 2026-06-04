<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Ingest\ConfigController;
use App\Http\Requests\BumpPolicyVersionRequest;
use App\Http\Requests\UpdateDomainSettingsRequest;
use App\Models\Domain;
use App\Models\PolicyVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Per-domain settings (US-SET-1, US-SET-2, US-SET-3, US-SET-5 link).
 * Banner content edits live in the banner builder; this page surfaces a link.
 */
class DomainSettingsController extends Controller
{
    public function edit(Domain $domain): Response
    {
        $this->authorize('update', $domain);

        $domain->loadMissing('notificationSetting');

        $policyVersions = $domain->policyVersions()
            ->orderByDesc('version')
            ->get()
            ->map(fn (PolicyVersion $v) => [
                'version' => $v->version,
                'effectiveAt' => $v->effective_at?->toIso8601String(),
                'notes' => $v->notes,
            ]);

        return Inertia::render('domains/settings', [
            'domain' => [
                'id' => $domain->domain_uid,
                'hostname' => $domain->hostname,
                'consentExpiryDays' => $domain->consent_expiry_days,
                'scheduledScanEnabled' => $domain->scheduled_scan_enabled,
                'scanFrequency' => $domain->scan_frequency,
            ],
            'notifications' => [
                'newCookieAlerts' => $domain->notificationSetting?->new_cookie_alerts ?? true,
            ],
            'policyVersions' => $policyVersions,
            'currentPolicyVersion' => (int) ($policyVersions->first()['version'] ?? 0),
        ]);
    }

    public function update(UpdateDomainSettingsRequest $request, Domain $domain): RedirectResponse
    {
        $data = $request->validated();

        $domain->update([
            'consent_expiry_days' => $data['consentExpiryDays'],
            'scheduled_scan_enabled' => $data['scheduledScanEnabled'],
            'scan_frequency' => $data['scheduledScanEnabled'] ? ($data['scanFrequency'] ?? 'monthly') : null,
        ]);

        $domain->notificationSetting()->updateOrCreate(
            ['domain_id' => $domain->id],
            ['new_cookie_alerts' => $data['newCookieAlerts']],
        );

        Cache::forget(ConfigController::cacheKey($domain->domain_uid));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Domain settings updated.')]);

        return back();
    }

    /**
     * US-SET-2 — publish a new policy version. SDK re-prompts visitors whose
     * stored consent's policyVersion is older than the published value.
     */
    public function bumpPolicy(BumpPolicyVersionRequest $request, Domain $domain): RedirectResponse
    {
        $next = ((int) $domain->policyVersions()->max('version')) + 1;

        $domain->policyVersions()->create([
            'version' => $next,
            'effective_at' => now(),
            'notes' => $request->validated('notes'),
        ]);

        Cache::forget(ConfigController::cacheKey($domain->domain_uid));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Policy version :v published. Visitors will be re-prompted.', ['v' => $next]),
        ]);

        return back();
    }
}
