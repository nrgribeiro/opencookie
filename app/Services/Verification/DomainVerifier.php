<?php

namespace App\Services\Verification;

use App\Enums\VerificationMethod;
use App\Models\Domain;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class DomainVerifier
{
    /** TXT record prefix the platform looks for. */
    public const TXT_PREFIX = 'cmp-site-verification=';

    /** Meta tag name the platform looks for. */
    public const META_NAME = 'cmp-site-verification';

    /** Well-known file path for file verification. */
    public const FILE_PATH = '/.well-known/cmp-verification.txt';

    public function __construct(private readonly TxtRecordResolver $txt) {}

    /**
     * Check the given method for the expected token on the domain's hostname.
     * Returns null on success, or a human-readable error string on failure.
     */
    public function attempt(Domain $domain, VerificationMethod $method, string $token): ?string
    {
        return match ($method) {
            VerificationMethod::DnsTxt => $this->checkDnsTxt($domain->hostname, $token),
            VerificationMethod::MetaTag => $this->checkMetaTag($domain->hostname, $token),
            VerificationMethod::File => $this->checkFile($domain->hostname, $token),
        };
    }

    private function checkDnsTxt(string $hostname, string $token): ?string
    {
        $records = $this->txt->resolve($hostname);

        foreach ($records as $record) {
            if ($record === $token || $record === self::TXT_PREFIX.$token) {
                return null;
            }
        }

        return 'TXT record not found. Add a TXT record containing "'.self::TXT_PREFIX.$token.'".';
    }

    private function checkMetaTag(string $hostname, string $token): ?string
    {
        $html = $this->fetch("https://{$hostname}");

        if ($html === null) {
            return 'Could not fetch https://'.$hostname.'.';
        }

        // Match <meta name="cmp-site-verification" content="TOKEN"> in any attribute order.
        $pattern = '/<meta[^>]*name=["\']'.preg_quote(self::META_NAME, '/').'["\'][^>]*content=["\']'
            .preg_quote($token, '/').'["\'][^>]*>/i';
        $patternReversed = '/<meta[^>]*content=["\']'.preg_quote($token, '/').'["\'][^>]*name=["\']'
            .preg_quote(self::META_NAME, '/').'["\'][^>]*>/i';

        if (preg_match($pattern, $html) || preg_match($patternReversed, $html)) {
            return null;
        }

        return 'Meta tag not found in the page <head>.';
    }

    private function checkFile(string $hostname, string $token): ?string
    {
        $body = $this->fetch("https://{$hostname}".self::FILE_PATH);

        if ($body === null) {
            return 'Could not fetch '.self::FILE_PATH.'.';
        }

        if (Str::of($body)->trim()->is($token)) {
            return null;
        }

        return 'File contents did not match the token.';
    }

    private function fetch(string $url): ?string
    {
        try {
            $response = Http::timeout(10)->get($url);

            return $response->successful() ? $response->body() : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
