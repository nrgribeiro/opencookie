<?php

namespace App\Http\Controllers;

use App\Enums\CookieCategory;
use App\Http\Controllers\Ingest\ConfigController;
use App\Http\Controllers\Ingest\DeclarationController;
use App\Http\Requests\UpdateCookieRequest;
use App\Models\Cookie;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;

class CookieController extends Controller
{
    /**
     * US-SCAN-3 — manually re-classify a cookie. The override is keyed by cookie
     * identity (name + source_domain) so it survives future scans.
     */
    public function update(UpdateCookieRequest $request, Cookie $cookie): RedirectResponse
    {
        $this->authorize('update', $cookie);

        $data = $request->validated();
        $category = CookieCategory::from($data['category']);
        $translations = array_filter(
            $data['purposeTranslations'] ?? [],
            fn ($v) => is_string($v) && trim($v) !== '',
        );

        // Partial updates: only overwrite a field when its key is present in the
        // request, otherwise keep the cookie's current value.
        $providerUrl = array_key_exists('providerUrl', $data)
            ? ($data['providerUrl'] ?: null)
            : $cookie->provider_url;
        $retention = array_key_exists('retention', $data)
            ? ($data['retention'] ?: null)
            : $cookie->retention;
        $dataController = array_key_exists('dataController', $data)
            ? ($data['dataController'] ?: null)
            : $cookie->data_controller;
        $gdprPortalUrl = array_key_exists('gdprPortalUrl', $data)
            ? ($data['gdprPortalUrl'] ?: null)
            : $cookie->gdpr_portal_url;

        $cookie->domain->cookieOverrides()->updateOrCreate(
            ['cookie_name' => $cookie->name, 'source_domain' => $cookie->source_domain],
            [
                'category' => $category,
                'provider' => $data['provider'] ?? $cookie->provider,
                'provider_url' => $providerUrl,
                'retention' => $retention,
                'data_controller' => $dataController,
                'gdpr_portal_url' => $gdprPortalUrl,
                'purpose' => $data['purpose'] ?? $cookie->purpose,
                'purpose_translations' => $translations ?: null,
            ],
        );

        $cookie->update([
            'category' => $category,
            'provider' => $data['provider'] ?? $cookie->provider,
            'provider_url' => $providerUrl,
            'retention' => $retention,
            'data_controller' => $dataController,
            'gdpr_portal_url' => $gdprPortalUrl,
            'purpose' => $data['purpose'] ?? $cookie->purpose,
        ]);

        DeclarationController::bustCache($cookie->domain);
        Cache::forget(
            ConfigController::cacheKey($cookie->domain->domain_uid),
        );

        return back()->with('status', 'Cookie classification updated.');
    }
}
