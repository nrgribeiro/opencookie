<?php

namespace App\Services\Scanner;

use App\Enums\CookieType;
use App\Models\Domain;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * Headless Chromium crawler (US-SCAN-1). Shells out to scanner/crawl.mjs, which
 * crawls up to $pageLimit same-host pages, executes JS, and reports cookies,
 * localStorage/sessionStorage keys and third-party request hosts as JSON.
 */
class PlaywrightSiteScanner implements SiteScanner
{
    public function scan(Domain $domain, int $pageLimit): ScanResult
    {
        $config = config('scanner.playwright');

        $payload = json_encode([
            'url' => 'https://'.$domain->hostname,
            'maxPages' => $pageLimit,
            'pageTimeoutMs' => $config['page_timeout_ms'] ?? 15000,
        ], JSON_THROW_ON_ERROR);

        $process = new Process(
            [$config['node_binary'] ?? 'node', $config['script'], $payload],
            base_path(),
        );
        $process->setTimeout((float) ($config['timeout'] ?? 180));

        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            throw new RuntimeException('Scan timed out after '.($config['timeout'] ?? 180).'s.');
        }

        if (! $process->isSuccessful()) {
            throw new RuntimeException($this->failureReason($process->getErrorOutput()));
        }

        return $this->parse($process->getOutput());
    }

    private function parse(string $output): ScanResult
    {
        $data = json_decode(trim($output), true);

        if (! is_array($data) || ! isset($data['items']) || ! is_array($data['items'])) {
            throw new RuntimeException('Scanner returned malformed output.');
        }

        $cookies = [];

        foreach ($data['items'] as $item) {
            $type = CookieType::tryFrom((string) ($item['type'] ?? '')) ?? CookieType::Http;

            $cookies[] = new DetectedCookie(
                name: (string) ($item['name'] ?? ''),
                type: $type,
                sourceDomain: $item['sourceDomain'] ?? null,
                isFirstParty: (bool) ($item['isFirstParty'] ?? true),
                expiry: $item['expiry'] ?? null,
                provider: $item['provider'] ?? null,
            );
        }

        $cookies = array_values(array_filter($cookies, fn (DetectedCookie $c) => $c->name !== ''));

        return new ScanResult(
            pagesCrawled: (int) ($data['pagesCrawled'] ?? 0),
            cookies: $cookies,
        );
    }

    private function failureReason(string $stderr): string
    {
        $decoded = json_decode(trim($stderr), true);
        if (is_array($decoded) && ! empty($decoded['error'])) {
            return 'Scanner error: '.$decoded['error'];
        }

        return 'Scanner failed'.($stderr !== '' ? ': '.Str::limit($stderr, 200) : '.');
    }
}
