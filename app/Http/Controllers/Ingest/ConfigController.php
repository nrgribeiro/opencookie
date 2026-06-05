<?php

namespace App\Http\Controllers\Ingest;

use App\Enums\CookieStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Ingest\Concerns\ResolvesDomain;
use App\Models\BannerConfig;
use App\Models\Domain;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class ConfigController extends Controller
{
    use ResolvesDomain;

    /** Config cache TTL — short so banner publishes propagate quickly. */
    public const CACHE_TTL = 300;

    /** Standard consent categories. Necessary is locked on (BAN-3). English is the fallback. */
    private const CATEGORIES = [
        ['id' => 'necessary', 'required' => true, 'name' => 'Necessary', 'description' => 'Required for the site to function. Always on.'],
        ['id' => 'preferences', 'required' => false, 'name' => 'Preferences', 'description' => 'Remembers your settings and choices.'],
        ['id' => 'statistics', 'required' => false, 'name' => 'Statistics', 'description' => 'Helps us understand how the site is used.'],
        ['id' => 'marketing', 'required' => false, 'name' => 'Marketing', 'description' => 'Used to deliver and measure ads.'],
    ];

    /**
     * Built-in category translations (name, description) per base language.
     * Falls back to English (CATEGORIES) for any language not listed here, so
     * non-English visitors get localized category labels (informed consent).
     */
    private const CATEGORY_I18N = [
        'necessary' => [
            'pt' => ['Necessários', 'Essenciais ao funcionamento do site. Sempre ativos.'],
            'de' => ['Notwendig', 'Für den Betrieb der Website erforderlich. Immer aktiv.'],
            'fr' => ['Nécessaires', 'Indispensables au fonctionnement du site. Toujours actifs.'],
            'es' => ['Necesarias', 'Necesarias para el funcionamiento del sitio. Siempre activas.'],
        ],
        'preferences' => [
            'pt' => ['Preferências', 'Memoriza as suas definições e escolhas.'],
            'de' => ['Präferenzen', 'Speichert Ihre Einstellungen und Auswahl.'],
            'fr' => ['Préférences', 'Mémorise vos réglages et vos choix.'],
            'es' => ['Preferencias', 'Recuerda sus ajustes y elecciones.'],
        ],
        'statistics' => [
            'pt' => ['Estatísticas', 'Ajuda-nos a perceber como o site é utilizado.'],
            'de' => ['Statistik', 'Hilft uns zu verstehen, wie die Website genutzt wird.'],
            'fr' => ['Statistiques', 'Nous aide à comprendre comment le site est utilisé.'],
            'es' => ['Estadísticas', 'Nos ayuda a entender cómo se usa el sitio.'],
        ],
        'marketing' => [
            'pt' => ['Marketing', 'Utilizado para apresentar e medir anúncios.'],
            'de' => ['Marketing', 'Wird zur Auslieferung und Messung von Werbung verwendet.'],
            'fr' => ['Marketing', 'Utilisé pour diffuser et mesurer les publicités.'],
            'es' => ['Marketing', 'Se utiliza para mostrar y medir anuncios.'],
        ],
    ];

    public function __invoke(string $domainUid): JsonResponse
    {
        $payload = Cache::remember(
            self::cacheKey($domainUid),
            self::CACHE_TTL,
            fn () => $this->build($domainUid),
        );

        return response()->json($payload)
            ->header('Cache-Control', 'public, max-age=60');
    }

    public static function cacheKey(string $domainUid): string
    {
        return "ingest:config:{$domainUid}";
    }

    /**
     * @return array<string, mixed>
     */
    private function build(string $domainUid): array
    {
        $domain = $this->resolveDomain($domainUid);
        $banner = $this->publishedBanner($domain);
        $policyVersion = (int) $domain->policyVersions()->max('version') ?: 1;

        return [
            'domainId' => $domain->domain_uid,
            'bannerVersion' => $banner->version,
            'policyVersion' => $policyVersion,
            'consentExpiryDays' => $domain->consent_expiry_days,
            'defaultLanguage' => $banner->default_language,
            'languages' => $banner->languages,
            'policyUrl' => $banner->policy_url,
            'layout' => $banner->layout,
            'content' => $banner->content,
            'categories' => $this->categories($banner),
            'cookieDetails' => $this->cookieDetails($domain, $banner),
            'consentModeMap' => $banner->consent_mode_map,
        ];
    }

    /**
     * Cookies grouped by category for the banner's details modal.
     * Owner-edited override fields take precedence over scanner-detected values.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function cookieDetails(Domain $domain, BannerConfig $banner): array
    {
        $languages = $banner->languages ?: [$banner->default_language ?? 'en'];
        $defaultLang = $banner->default_language ?? ($languages[0] ?? 'en');

        $overrides = $domain->cookieOverrides()->get()
            ->keyBy(fn ($o) => $o->cookie_name.'|'.($o->source_domain ?? ''));

        $grouped = [];

        $domain->cookies()
            ->where('status', '!=', CookieStatus::NotSeen->value)
            ->orderBy('category')
            ->orderBy('name')
            ->get()
            ->each(function ($cookie) use (&$grouped, $overrides, $languages, $defaultLang): void {
                $key = $cookie->name.'|'.($cookie->source_domain ?? '');
                $override = $overrides->get($key);

                $translations = is_array($override?->purpose_translations)
                    ? $override->purpose_translations
                    : [];

                $purposePerLang = [];
                foreach ($languages as $lang) {
                    $purposePerLang[$lang] = $translations[$lang]
                        ?? $translations[$defaultLang]
                        ?? (string) ($cookie->purpose ?? '');
                }

                $grouped[$cookie->category->value][] = [
                    'name' => (string) $cookie->name,
                    'provider' => (string) ($cookie->provider ?? ''),
                    'providerUrl' => $cookie->provider_url ?: null,
                    'purpose' => $purposePerLang,
                    'expiry' => (string) ($cookie->expiry ?? ''),
                    'sourceDomain' => $cookie->source_domain,
                    'isFirstParty' => (bool) $cookie->is_first_party,
                ];
            });

        return $grouped;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function categories(BannerConfig $banner): array
    {
        $languages = $banner->languages ?: ['en'];

        return collect(self::CATEGORIES)->map(function (array $cat) use ($languages) {
            $i18n = self::CATEGORY_I18N[$cat['id']] ?? [];
            $name = [];
            $description = [];

            foreach ($languages as $l) {
                $base = strtolower(explode('-', (string) $l)[0]);
                [$n, $d] = $i18n[$base] ?? [$cat['name'], $cat['description']];
                $name[$l] = $n;
                $description[$l] = $d;
            }

            return [
                'id' => $cat['id'],
                'required' => $cat['required'],
                'name' => $name,
                'description' => $description,
            ];
        })->all();
    }
}
