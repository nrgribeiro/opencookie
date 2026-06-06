<?php

namespace App\Services\Scanner;

use App\Enums\CookieType;
use App\Models\Domain;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * HTTP scanner: breadth-first crawls same-host pages (up to $pageLimit),
 * reading Set-Cookie headers and third-party <script src> hosts on each.
 *
 * Limitation: plain HTTP fetch, no JS execution — cookies/trackers set
 * client-side by JavaScript are not observed here (only Set-Cookie response
 * headers and statically-referenced third-party script hosts). A full headless
 * crawl (Playwright) is deferred — see technical-spec.md §4.4.
 */
class HttpSiteScanner implements SiteScanner
{
    /** Skip obvious non-HTML assets when discovering links. */
    private const ASSET_EXT = [
        'jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'ico', 'css', 'js', 'mjs',
        'woff', 'woff2', 'ttf', 'eot', 'pdf', 'zip', 'gz', 'mp4', 'webm', 'mp3',
        'xml', 'rss', 'json', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
    ];

    public function scan(Domain $domain, int $pageLimit): ScanResult
    {
        $host = $domain->hostname;
        $start = "https://{$host}/";

        /** @var array<string, DetectedCookie> $cookies keyed by name|sourceDomain */
        $cookies = [];
        $seenScriptHosts = [];

        $queue = [$start];
        $visited = [];
        $pagesCrawled = 0;

        while ($queue !== [] && $pagesCrawled < $pageLimit) {
            $url = array_shift($queue);
            $norm = $this->normalize($url);
            if ($norm === null || isset($visited[$norm])) {
                continue;
            }
            $visited[$norm] = true;

            try {
                $response = Http::timeout(15)
                    ->withHeaders(['Accept' => 'text/html'])
                    ->get($url);
            } catch (\Throwable) {
                continue;
            }

            $pagesCrawled++;

            // First-party cookies set via Set-Cookie.
            foreach ($response->cookies()->toArray() as $cookie) {
                $sourceDomain = $cookie['Domain'] ?: $host;
                $detected = new DetectedCookie(
                    name: $cookie['Name'],
                    type: CookieType::Http,
                    sourceDomain: $sourceDomain,
                    isFirstParty: $this->isFirstParty($sourceDomain, $host),
                    expiry: isset($cookie['Expires']) && $cookie['Expires']
                        ? (string) $cookie['Expires']
                        : 'session',
                );
                $cookies[$detected->name.'|'.$sourceDomain] = $detected;
            }

            // Only parse HTML bodies for scripts and links.
            if (! $this->isHtml($response)) {
                continue;
            }

            $body = $response->body();

            // Third-party script hosts.
            preg_match_all('/<script[^>]+src=["\']([^"\']+)["\']/i', $body, $scriptMatches);
            foreach ($scriptMatches[1] as $src) {
                $scriptHost = parse_url($this->absolute($src, $url) ?? $src, PHP_URL_HOST);
                if (! $scriptHost || $this->isFirstParty($scriptHost, $host) || isset($seenScriptHosts[$scriptHost])) {
                    continue;
                }
                $seenScriptHosts[$scriptHost] = true;
                $cookies[$scriptHost.'|'.$scriptHost] = new DetectedCookie(
                    name: $scriptHost,
                    type: CookieType::Script,
                    sourceDomain: $scriptHost,
                    isFirstParty: false,
                );
            }

            // Discover same-host links to crawl next.
            if (count($visited) + count($queue) < $pageLimit) {
                preg_match_all('/<a[^>]+href=["\']([^"\']+)["\']/i', $body, $linkMatches);
                foreach ($linkMatches[1] as $href) {
                    $abs = $this->absolute($href, $url);
                    $n = $this->normalize($abs);
                    if ($n === null || isset($visited[$n]) || $this->isFirstParty(parse_url($n, PHP_URL_HOST) ?? '', $host) === false) {
                        continue;
                    }
                    $queue[] = $abs;
                }
            }
        }

        return new ScanResult(pagesCrawled: $pagesCrawled, cookies: array_values($cookies));
    }

    /**
     * Resolve a possibly-relative href against the page URL. Returns null for
     * non-crawlable schemes (mailto:, tel:, javascript:, data:, fragments).
     */
    private function absolute(string $href, string $base): ?string
    {
        $href = trim($href);
        if ($href === '' || str_starts_with($href, '#')) {
            return null;
        }
        $scheme = parse_url($href, PHP_URL_SCHEME);
        if ($scheme !== null && ! in_array(strtolower($scheme), ['http', 'https'], true)) {
            return null;
        }
        if ($scheme !== null) {
            return $href;
        }

        $b = parse_url($base);
        if (! isset($b['scheme'], $b['host'])) {
            return null;
        }
        $origin = $b['scheme'].'://'.$b['host'].(isset($b['port']) ? ':'.$b['port'] : '');

        if (str_starts_with($href, '//')) {
            return $b['scheme'].':'.$href;
        }
        if (str_starts_with($href, '/')) {
            return $origin.$href;
        }
        $path = $b['path'] ?? '/';
        $dir = substr($path, 0, strrpos($path, '/') + 1) ?: '/';

        return $origin.$dir.$href;
    }

    /**
     * Canonical key for dedup: strip fragment, drop trailing slash, lowercase
     * host. Returns null for asset URLs that aren't worth crawling.
     */
    private function normalize(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }
        $p = parse_url($url);
        if (! isset($p['scheme'], $p['host'])) {
            return null;
        }
        $path = $p['path'] ?? '/';
        $ext = strtolower(pathinfo(parse_url($path, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
        if ($ext !== '' && in_array($ext, self::ASSET_EXT, true)) {
            return null;
        }
        $host = strtolower($p['host']);
        $path = rtrim($path, '/');
        $query = isset($p['query']) ? '?'.$p['query'] : '';

        return strtolower($p['scheme']).'://'.$host.($path === '' ? '/' : $path).$query;
    }

    private function isHtml(Response $response): bool
    {
        $type = $response->header('Content-Type');

        return $type === '' || str_contains(strtolower($type), 'html');
    }

    private function isFirstParty(string $candidate, string $host): bool
    {
        $candidate = ltrim($candidate, '.');

        return $candidate === $host || Str::endsWith($candidate, '.'.$host);
    }
}
