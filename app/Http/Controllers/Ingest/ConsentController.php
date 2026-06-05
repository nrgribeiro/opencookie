<?php

namespace App\Http\Controllers\Ingest;

use App\Enums\ConsentMethod;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Ingest\Concerns\ResolvesDomain;
use App\Http\Requests\Ingest\StoreConsentRequest;
use App\Models\BannerConfig;
use App\Services\Banner\BannerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class ConsentController extends Controller
{
    use ResolvesDomain;

    public function __invoke(StoreConsentRequest $request, string $domainUid): JsonResponse
    {
        $domain = $this->resolveDomain($domainUid);
        $banner = $this->publishedBanner($domain); // 404 if no live banner to consent to

        $data = $request->validated();
        $consentId = $data['consentId'] ?? (string) Str::uuid();
        $now = now();
        $expiresAt = $now->copy()->addDays($domain->consent_expiry_days);
        $language = $data['language'] ?? $banner->default_language;

        $domain->consentRecords()->create([
            'consent_id' => $consentId,
            'method' => ConsentMethod::from($data['method']),
            'categories' => $data['categories'],
            'banner_version' => $data['bannerVersion'],
            'policy_version' => $data['policyVersion'],
            // Authoritative: recomputed from the published banner, not trusted
            // from the client, so the stored proof can't be spoofed or omitted.
            'consent_text_hash' => $this->consentTextHash($banner, $language),
            'ip_hash' => $this->hashIp($request->ip()),
            'user_agent' => Str::limit((string) $request->userAgent(), 480, ''),
            'language' => $language,
            'created_at' => $now,
            'expires_at' => $expiresAt,
        ]);

        return response()->json([
            'consentId' => $consentId,
            'stored' => true,
            'expiresAt' => $expiresAt->toIso8601String(),
        ], 201);
    }

    /**
     * Deterministic fingerprint of the exact consent text shown, in the
     * visitor's language, derived from the published banner (US-LOG-1 proof).
     * Mirrors the SDK's consentTextHash() field selection but is authoritative.
     */
    private function consentTextHash(BannerConfig $banner, string $language): string
    {
        $content = is_array($banner->content) ? $banner->content : [];
        $langContent = $content[$language] ?? [];
        $defaultContent = $content[$banner->default_language] ?? [];

        $about = $langContent['aboutCookies']
            ?? $defaultContent['aboutCookies']
            ?? BannerService::DEFAULT_ABOUT_COOKIES;

        $parts = [
            $language,
            (string) ($langContent['title'] ?? ''),
            (string) ($langContent['body'] ?? ''),
            (string) ($langContent['acceptAll'] ?? ''),
            (string) ($langContent['rejectAll'] ?? ''),
            (string) ($langContent['customize'] ?? ''),
            (string) ($banner->policy_url ?? ''),
            (string) $about,
        ];

        return hash('sha256', implode("\u{241F}", $parts));
    }

    /**
     * Salted, non-reversible IP hash. PII minimization (spec §4.6).
     * Production salt lives in Key Vault; here it derives from APP_KEY.
     */
    private function hashIp(?string $ip): ?string
    {
        if (! $ip) {
            return null;
        }

        return hash('sha256', $ip.'|'.config('app.key'));
    }
}
