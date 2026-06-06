<?php

use App\Enums\CookieType;
use App\Models\Domain;
use App\Services\Scanner\PlaywrightSiteScanner;

/**
 * Point the scanner at a PHP test double instead of the real Node/Chromium
 * crawler, so the PHP<->process<->JSON boundary is exercised in CI without a
 * browser. node_binary = the PHP CLI, script = a fixture that emits canned JSON.
 */
function fakeCrawler(string $fixture): void
{
    config()->set('scanner.playwright', [
        'node_binary' => PHP_BINARY,
        'script' => base_path('tests/Fixtures/'.$fixture),
        'timeout' => 30,
        'page_timeout_ms' => 15000,
    ]);
}

it('parses crawler output into detected cookies', function () {
    fakeCrawler('fake_crawler_ok.php');
    $domain = Domain::factory()->create(['hostname' => 'example.com']);

    $result = (new PlaywrightSiteScanner)->scan($domain, 100);

    expect($result->pagesCrawled)->toBe(12)
        // empty-name item is filtered out → 4 of 5 kept.
        ->and($result->cookies)->toHaveCount(4);

    $by = collect($result->cookies)->keyBy('name');

    // JS-set first-party cookie (the whole point of the headless driver).
    expect($by['_ga']->type)->toBe(CookieType::Http)
        ->and($by['_ga']->isFirstParty)->toBeTrue()
        ->and($by['_ga']->expiry)->toBe('2027-01-01T00:00:00.000Z');

    // Third-party cookie + tracker host + storage key all mapped.
    expect($by['_fbp']->isFirstParty)->toBeFalse();
    expect($by['connect.facebook.net']->type)->toBe(CookieType::Script);
    expect($by['cmp_pref']->type)->toBe(CookieType::LocalStorage);
});

it('throws a clear error when the crawler fails', function () {
    fakeCrawler('fake_crawler_fail.php');
    $domain = Domain::factory()->create(['hostname' => 'example.com']);

    expect(fn () => (new PlaywrightSiteScanner)->scan($domain, 100))
        ->toThrow(RuntimeException::class, 'chromium missing');
});
