<?php

use App\Enums\CookieCategory;
use App\Enums\CookieStatus;
use App\Enums\CookieType;
use App\Enums\ScanStatus;
use App\Jobs\RunScanJob;
use App\Models\Cookie;
use App\Models\Domain;
use App\Models\Scan;
use App\Services\Scanner\CookieClassifier;
use App\Services\Scanner\DetectedCookie;
use App\Services\Scanner\ScanResult;
use App\Services\Scanner\SiteScanner;

/** Build a fake scanner returning fixed detected cookies. */
function fakeScanner(array $cookies, int $pages = 1): SiteScanner
{
    return new class($cookies, $pages) implements SiteScanner
    {
        public function __construct(private array $cookies, private int $pages) {}

        public function scan(Domain $domain, int $pageLimit): ScanResult
        {
            return new ScanResult($this->pages, $this->cookies);
        }
    };
}

function runScan(Domain $domain, SiteScanner $scanner): Scan
{
    $scan = $domain->scans()->create(['status' => ScanStatus::Queued]);
    (new RunScanJob($scan->id))->handle($scanner, new CookieClassifier());

    return $scan->refresh();
}

it('persists and classifies detected cookies', function () {
    $domain = Domain::factory()->create();

    $scanner = fakeScanner([
        new DetectedCookie('_ga', CookieType::Http, 'example.com', true, '2 years'),
        new DetectedCookie('mystery', CookieType::Http, 'example.com', true, 'session'),
    ]);

    $scan = runScan($domain, $scanner);

    expect($scan->status)->toBe(ScanStatus::Complete)
        ->and($scan->pages_crawled)->toBe(1)
        ->and($domain->fresh()->last_scanned_at)->not->toBeNull();

    expect($domain->cookies()->where('name', '_ga')->first()->category)
        ->toBe(CookieCategory::Statistics);
    expect($domain->cookies()->where('name', 'mystery')->first()->category)
        ->toBe(CookieCategory::Unclassified);
});

it('lets a manual override win over the classifier', function () {
    $domain = Domain::factory()->create();
    $domain->cookieOverrides()->create([
        'cookie_name' => '_ga',
        'source_domain' => 'example.com',
        'category' => CookieCategory::Marketing,
        'provider' => 'Custom',
    ]);

    $scan = runScan($domain, fakeScanner([
        new DetectedCookie('_ga', CookieType::Http, 'example.com'),
    ]));

    $cookie = $domain->cookies()->where('name', '_ga')->first();
    expect($cookie->category)->toBe(CookieCategory::Marketing)
        ->and($cookie->provider)->toBe('Custom');
});

it('marks cookies not seen in the latest scan', function () {
    $domain = Domain::factory()->create();
    $old = Cookie::factory()->for($domain)->create([
        'name' => 'gone',
        'source_domain' => 'old.example.com',
        'status' => CookieStatus::Active,
        'scan_id' => null,
    ]);

    runScan($domain, fakeScanner([
        new DetectedCookie('kept', CookieType::Http, 'example.com'),
    ]));

    expect($old->refresh()->status)->toBe(CookieStatus::NotSeen)
        ->and($domain->cookies()->where('name', 'kept')->first()->status)->toBe(CookieStatus::New);
});

it('marks the scan failed when the scanner throws', function () {
    $domain = Domain::factory()->create();
    $scanner = new class implements SiteScanner
    {
        public function scan(Domain $domain, int $pageLimit): ScanResult
        {
            throw new RuntimeException('boom');
        }
    };

    $scan = $domain->scans()->create(['status' => ScanStatus::Queued]);

    expect(fn () => (new RunScanJob($scan->id))->handle($scanner, new CookieClassifier()))
        ->toThrow(RuntimeException::class);

    expect($scan->refresh()->status)->toBe(ScanStatus::Failed)
        ->and($scan->error)->toContain('boom');
});
