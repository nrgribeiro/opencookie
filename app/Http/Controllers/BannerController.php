<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateBannerRequest;
use App\Models\Domain;
use App\Services\Banner\BannerService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BannerController extends Controller
{
    public function __construct(private readonly BannerService $banner) {}

    /**
     * US-BAN-1..5 — open the builder on the current draft.
     */
    public function edit(Domain $domain): Response
    {
        $this->authorize('update', $domain);

        $draft = $this->banner->draftFor($domain);
        $published = $domain->bannerConfigs()
            ->where('status', \App\Enums\BannerStatus::Published)
            ->latest('version')
            ->first();

        return Inertia::render('domains/banner', [
            'domain' => ['id' => $domain->domain_uid, 'hostname' => $domain->hostname],
            'config' => $this->present($draft),
            'publishedVersion' => $published?->version,
            'requiredContentKeys' => BannerService::REQUIRED_CONTENT_KEYS,
        ]);
    }

    /**
     * US-BAN-1..5 — save the draft.
     */
    public function update(UpdateBannerRequest $request, Domain $domain): RedirectResponse
    {
        $this->authorize('update', $domain);

        $draft = $this->banner->draftFor($domain);
        $draft->update($request->validated());

        return back()->with('status', 'Draft saved.');
    }

    /**
     * US-BAN-6 — publish the draft (validates completeness in the service).
     */
    public function publish(Domain $domain): RedirectResponse
    {
        $this->authorize('update', $domain);

        $draft = $this->banner->draftFor($domain);
        $this->banner->publish($draft);

        return back()->with('status', 'Banner published.');
    }

    /**
     * @return array<string, mixed>
     */
    private function present($config): array
    {
        return [
            'version' => $config->version,
            'status' => $config->status->value,
            'layout' => $config->layout,
            'content' => $config->content,
            'languages' => $config->languages,
            'defaultLanguage' => $config->default_language,
            'policyUrl' => $config->policy_url,
            'consentModeMap' => $config->consent_mode_map,
            'publishedAt' => $config->published_at?->toIso8601String(),
        ];
    }
}
