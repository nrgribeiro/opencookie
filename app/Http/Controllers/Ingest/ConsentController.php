<?php

namespace App\Http\Controllers\Ingest;

use App\Enums\ConsentMethod;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Ingest\Concerns\ResolvesDomain;
use App\Http\Requests\Ingest\StoreConsentRequest;
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

        $domain->consentRecords()->create([
            'consent_id' => $consentId,
            'method' => ConsentMethod::from($data['method']),
            'categories' => $data['categories'],
            'banner_version' => $data['bannerVersion'],
            'policy_version' => $data['policyVersion'],
            'consent_text_hash' => $data['consentTextHash'] ?? '',
            'ip_hash' => $this->hashIp($request->ip()),
            'user_agent' => Str::limit((string) $request->userAgent(), 480, ''),
            'language' => $data['language'] ?? $banner->default_language,
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
