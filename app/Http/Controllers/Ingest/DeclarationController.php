<?php

namespace App\Http\Controllers\Ingest;

use App\Enums\CookieStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Ingest\Concerns\ResolvesDomain;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class DeclarationController extends Controller
{
    use ResolvesDomain;

    public const CACHE_TTL = 600;

    /**
     * US-DECL-2 / US-DECL-3 — embeddable JS that renders the live cookie
     * declaration into the element with id "cmp-cookie-declaration". Resolves
     * per-language purpose strings from cookie_overrides.purpose_translations
     * (falls back to default language, then plain `purpose`).
     */
    public function __invoke(Request $request, string $domainUid): Response
    {
        $lang = $request->query('lang');
        $langKey = is_string($lang) && $lang !== '' ? $lang : 'default';

        $js = Cache::remember(
            self::cacheKey($domainUid, $langKey),
            self::CACHE_TTL,
            fn () => $this->build($domainUid, is_string($lang) && $lang !== '' ? $lang : null),
        );

        return response($js)
            ->header('Content-Type', 'application/javascript; charset=utf-8')
            ->header('Cache-Control', 'public, max-age=300');
    }

    public static function cacheKey(string $domainUid, string $lang = 'default'): string
    {
        return "ingest:declaration:{$domainUid}:{$lang}";
    }

    public static function bustCache(\App\Models\Domain $domain): void
    {
        $langs = array_values(array_unique(array_merge(
            ['default'],
            $domain->publishedBanner?->languages ?? [],
        )));

        foreach ($langs as $lang) {
            Cache::forget(self::cacheKey($domain->domain_uid, $lang));
        }
    }

    private function build(string $domainUid, ?string $requestedLang): string
    {
        $domain = $this->resolveDomain($domainUid);
        $banner = $domain->publishedBanner;
        $defaultLang = $banner?->default_language ?? 'en';
        $languages = $banner?->languages ?: [$defaultLang];
        $lang = in_array($requestedLang, $languages, true) ? $requestedLang : $defaultLang;

        $overrides = $domain->cookieOverrides()->get()
            ->keyBy(fn ($o) => $o->cookie_name.'|'.($o->source_domain ?? ''));

        $rows = $domain->cookies()
            ->where('status', '!=', CookieStatus::NotSeen->value)
            ->orderBy('category')
            ->orderBy('name')
            ->get(['name', 'provider', 'category', 'purpose', 'expiry', 'source_domain'])
            ->map(function ($c) use ($overrides, $lang, $defaultLang) {
                $key = $c->name.'|'.($c->source_domain ?? '');
                $override = $overrides->get($key);
                $translations = is_array($override?->purpose_translations)
                    ? $override->purpose_translations
                    : [];
                $purpose = $translations[$lang]
                    ?? $translations[$defaultLang]
                    ?? (string) ($c->purpose ?? '');

                return [
                    'name' => (string) $c->name,
                    'provider' => (string) ($c->provider ?? ''),
                    'category' => $c->category->value,
                    'purpose' => (string) $purpose,
                    'expiry' => (string) ($c->expiry ?? ''),
                ];
            })
            ->all();

        $json = json_encode($rows, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        return <<<JS
        (function () {
          var rows = {$json};
          var el = document.getElementById('cmp-cookie-declaration');
          if (!el) return;
          function esc(s){var d=document.createElement('div');d.textContent=s;return d.innerHTML;}
          var head = '<thead><tr><th>Name</th><th>Provider</th><th>Category</th><th>Purpose</th><th>Expiry</th></tr></thead>';
          var body = rows.map(function (r) {
            return '<tr><td>'+esc(r.name)+'</td><td>'+esc(r.provider)+'</td><td>'+esc(r.category)+
                   '</td><td>'+esc(r.purpose)+'</td><td>'+esc(r.expiry)+'</td></tr>';
          }).join('');
          el.innerHTML = '<table class="cmp-cookie-declaration">'+head+'<tbody>'+body+'</tbody></table>';
        })();
        JS;
    }
}
