<?php

namespace App\Services\Scanner;

use App\Enums\CookieType;
use App\Models\Domain;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * MVP scanner: fetches the homepage over HTTP, reads Set-Cookie headers and
 * third-party <script src> hosts. Full multi-page headless crawl (Playwright)
 * is deferred — see technical-spec.md §4.4.
 */
class HttpSiteScanner implements SiteScanner
{
    public function scan(Domain $domain, int $pageLimit): ScanResult
    {
        $host = $domain->hostname;
        $response = Http::timeout(15)->get("https://{$host}");

        $cookies = [];

        // First-party cookies set via Set-Cookie.
        foreach ($response->cookies()->toArray() as $cookie) {
            $cookies[] = new DetectedCookie(
                name: $cookie['Name'],
                type: CookieType::Http,
                sourceDomain: $cookie['Domain'] ?: $host,
                isFirstParty: $this->isFirstParty($cookie['Domain'] ?: $host, $host),
                expiry: isset($cookie['Expires']) && $cookie['Expires']
                    ? (string) $cookie['Expires']
                    : 'session',
            );
        }

        // Third-party script hosts.
        $body = $response->body();
        preg_match_all('/<script[^>]+src=["\']([^"\']+)["\']/i', $body, $matches);

        $seenHosts = [];
        foreach ($matches[1] as $src) {
            $scriptHost = parse_url($src, PHP_URL_HOST);
            if (! $scriptHost || $this->isFirstParty($scriptHost, $host)) {
                continue;
            }
            if (isset($seenHosts[$scriptHost])) {
                continue;
            }
            $seenHosts[$scriptHost] = true;

            $cookies[] = new DetectedCookie(
                name: $scriptHost,
                type: CookieType::Script,
                sourceDomain: $scriptHost,
                isFirstParty: false,
            );
        }

        return new ScanResult(pagesCrawled: 1, cookies: $cookies);
    }

    private function isFirstParty(string $candidate, string $host): bool
    {
        $candidate = ltrim($candidate, '.');

        return $candidate === $host || Str::endsWith($candidate, '.'.$host);
    }
}
