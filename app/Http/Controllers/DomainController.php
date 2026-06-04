<?php

namespace App\Http\Controllers;

use App\Enums\DomainVerifyStatus;
use App\Enums\VerificationMethod;
use App\Http\Requests\StoreDomainRequest;
use App\Http\Requests\VerifyDomainRequest;
use App\Models\Domain;
use App\Services\Verification\DomainVerifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class DomainController extends Controller
{
    /**
     * US-DOM-4 — list the owner's domains with status.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Domain::class);

        $domains = $request->user()->domains()
            ->latest()
            ->get()
            ->map(fn (Domain $domain) => $this->summary($domain));

        return Inertia::render('domains/index', [
            'domains' => $domains,
            'canCreate' => $request->user()->domains()->count() === 0,
        ]);
    }

    /**
     * US-DOM-1 — show the add-domain form.
     */
    public function create(Request $request): Response
    {
        $this->authorize('create', Domain::class);

        return Inertia::render('domains/create');
    }

    /**
     * US-DOM-1 — create a domain + its verification token + notification defaults.
     */
    public function store(StoreDomainRequest $request): RedirectResponse
    {
        $domain = $request->user()->domains()->create([
            'domain_uid' => $this->generateUid(),
            'hostname' => $request->validated('hostname'),
            'verify_status' => DomainVerifyStatus::Pending,
        ]);

        $domain->verifications()->create([
            'method' => VerificationMethod::DnsTxt,
            'token' => 'cmp-verify-'.Str::lower(Str::random(32)),
        ]);

        $domain->notificationSetting()->create();

        return redirect()
            ->route('domains.show', $domain)
            ->with('status', 'Domain added. Verify ownership to go live.');
    }

    /**
     * US-DOM-3 / US-DOM-4 — domain detail: status, embed snippet, verification token.
     */
    public function show(Domain $domain): Response
    {
        $this->authorize('view', $domain);

        $verification = $domain->verifications()->latest()->first();
        $latestScan = $domain->scans()->latest()->first();
        $banner = $domain->publishedBanner;
        $languages = $banner?->languages ?: [$banner?->default_language ?? 'en'];

        $overrides = $domain->cookieOverrides()->get()
            ->keyBy(fn ($o) => $o->cookie_name.'|'.($o->source_domain ?? ''));

        $missingTranslations = 0;

        $cookies = $domain->cookies()
            ->orderBy('category')
            ->orderBy('name')
            ->get()
            ->map(function ($cookie) use ($overrides, $languages, &$missingTranslations) {
                $key = $cookie->name.'|'.($cookie->source_domain ?? '');
                $override = $overrides->get($key);
                $translations = is_array($override?->purpose_translations)
                    ? $override->purpose_translations
                    : [];

                // US-DECL-3 — count any missing per-configured-language translation
                // when the owner has set at least one (i.e. opted into per-lang text).
                if (count($languages) > 1 && $translations !== []) {
                    foreach ($languages as $lang) {
                        if (empty($translations[$lang])) {
                            $missingTranslations++;
                        }
                    }
                }

                return [
                    'id' => $cookie->id,
                    'name' => $cookie->name,
                    'provider' => $cookie->provider,
                    'providerUrl' => $cookie->provider_url,
                    'category' => $cookie->category->value,
                    'purpose' => $cookie->purpose,
                    'purposeTranslations' => (object) $translations,
                    'expiry' => $cookie->expiry,
                    'type' => $cookie->type->value,
                    'sourceDomain' => $cookie->source_domain,
                    'isFirstParty' => $cookie->is_first_party,
                    'status' => $cookie->status->value,
                ];
            });

        return Inertia::render('domains/show', [
            'domain' => $this->summary($domain),
            'snippet' => $this->embedSnippet($domain),
            'declarationSnippet' => $this->declarationSnippet($domain),
            'verification' => $verification ? [
                'method' => $verification->method->value,
                'token' => $verification->token,
                'verifiedAt' => $verification->verified_at?->toIso8601String(),
                'lastCheckedAt' => $verification->last_checked_at?->toIso8601String(),
                'lastError' => $verification->last_error,
            ] : null,
            'latestScan' => $latestScan ? [
                'status' => $latestScan->status->value,
                'pagesCrawled' => $latestScan->pages_crawled,
                'finishedAt' => $latestScan->finished_at?->toIso8601String(),
                'error' => $latestScan->error,
            ] : null,
            'cookies' => $cookies,
            'cookieCounts' => [
                'total' => $cookies->count(),
                'unclassified' => $cookies->where('category', 'unclassified')->count(),
                'missingTranslations' => $missingTranslations,
            ],
            'languages' => $languages,
        ]);
    }

    /**
     * US-DOM-2 — check ownership for the chosen method and update status.
     */
    public function verify(VerifyDomainRequest $request, Domain $domain, DomainVerifier $verifier): RedirectResponse
    {
        $this->authorize('update', $domain);

        $verification = $domain->verifications()->latest()->firstOrFail();
        $method = $request->method();

        $error = $verifier->attempt($domain, $method, $verification->token);

        $verification->forceFill([
            'method' => $method,
            'last_checked_at' => now(),
            'last_error' => $error,
            'verified_at' => $error === null ? now() : null,
        ])->save();

        if ($error === null) {
            $domain->update(['verify_status' => DomainVerifyStatus::Verified]);

            return back()->with('status', 'Domain verified.');
        }

        $domain->update(['verify_status' => DomainVerifyStatus::Failed]);

        return back()->withErrors(['verification' => $error]);
    }

    /**
     * US-DOM-5 — delete a domain (and its config/scan data via cascade).
     * Consent logs are NOT deleted early — retention policy governs them.
     */
    public function destroy(Domain $domain): RedirectResponse
    {
        $this->authorize('delete', $domain);

        $domain->delete();

        return redirect()
            ->route('domains.index')
            ->with('status', 'Domain removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Domain $domain): array
    {
        return [
            'id' => $domain->domain_uid,
            'hostname' => $domain->hostname,
            'verifyStatus' => $domain->verify_status->value,
            'bannerLive' => $domain->banner_live,
            'lastScannedAt' => $domain->last_scanned_at?->toIso8601String(),
            'scheduledScans' => $domain->scheduled_scan_enabled,
            'createdAt' => $domain->created_at?->toIso8601String(),
        ];
    }

    private function embedSnippet(Domain $domain): string
    {
        $cdn = rtrim((string) (config('services.cmp_cdn') ?: config('app.url')), '/');

        return sprintf(
            '<script src="%s/sdk/v1/cmp.js" data-domain="%s" async></script>',
            $cdn,
            $domain->domain_uid,
        );
    }

    /**
     * US-DECL-2 — embeddable snippet that renders the live cookie declaration
     * on the owner's policy page. The script auto-updates as scans change the
     * cookie set; no re-paste required.
     */
    private function declarationSnippet(Domain $domain): string
    {
        $cdn = rtrim((string) (config('services.cmp_cdn') ?: config('app.url')), '/');

        return sprintf(
            "<div id=\"cmp-cookie-declaration\"></div>\n<script src=\"%s/v1/c/%s/declaration.js\" async></script>",
            $cdn,
            $domain->domain_uid,
        );
    }

    private function generateUid(): string
    {
        do {
            $uid = 'dom_'.Str::lower(Str::random(24));
        } while (Domain::where('domain_uid', $uid)->exists());

        return $uid;
    }
}
