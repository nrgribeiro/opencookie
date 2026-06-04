<?php

use App\Enums\CookieCategory;
use App\Enums\DomainVerifyStatus;
use App\Enums\ScanStatus;
use App\Enums\ScanTrigger;
use App\Jobs\RunScanJob;
use App\Models\Cookie;
use App\Models\Domain;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

it('queues a scan for a verified domain and dispatches the job', function () {
    Queue::fake();
    $user = User::factory()->create();
    $domain = Domain::factory()->for($user)->verified()->create();

    $this->actingAs($user)
        ->post(route('scans.store', $domain))
        ->assertRedirect()
        ->assertSessionHas('status');

    $scan = $domain->scans()->first();
    expect($scan)->not->toBeNull()
        ->and($scan->status)->toBe(ScanStatus::Queued)
        ->and($scan->trigger)->toBe(ScanTrigger::Manual);

    Queue::assertPushed(RunScanJob::class);
});

it('blocks scanning an unverified domain', function () {
    Queue::fake();
    $user = User::factory()->create();
    $domain = Domain::factory()->for($user)->create([
        'verify_status' => DomainVerifyStatus::Pending,
    ]);

    $this->actingAs($user)
        ->post(route('scans.store', $domain))
        ->assertSessionHasErrors('scan');

    expect($domain->scans()->count())->toBe(0);
    Queue::assertNothingPushed();
});

it('forbids scanning another users domain', function () {
    Queue::fake();
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $domain = Domain::factory()->for($owner)->verified()->create();

    $this->actingAs($other)
        ->post(route('scans.store', $domain))
        ->assertForbidden();
});

it('lets the owner override a cookie classification and persists it', function () {
    $user = User::factory()->create();
    $domain = Domain::factory()->for($user)->create();
    $cookie = Cookie::factory()->for($domain)->unclassified()->create([
        'name' => '_ga',
        'source_domain' => 'google.com',
    ]);

    $this->actingAs($user)
        ->patch(route('cookies.update', $cookie), [
            'category' => 'statistics',
            'provider' => 'Google Analytics',
            'purpose' => 'Analytics measurement',
        ])
        ->assertRedirect()
        ->assertSessionHas('status');

    expect($cookie->refresh()->category)->toBe(CookieCategory::Statistics)
        ->and($domain->cookieOverrides()->where('cookie_name', '_ga')->exists())->toBeTrue();
});

it('rejects overriding to a non-assignable category', function () {
    $user = User::factory()->create();
    $domain = Domain::factory()->for($user)->create();
    $cookie = Cookie::factory()->for($domain)->create();

    $this->actingAs($user)
        ->patch(route('cookies.update', $cookie), ['category' => 'unclassified'])
        ->assertSessionHasErrors('category');
});

it('forbids overriding another users cookie', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $domain = Domain::factory()->for($owner)->create();
    $cookie = Cookie::factory()->for($domain)->create();

    $this->actingAs($other)
        ->patch(route('cookies.update', $cookie), ['category' => 'statistics'])
        ->assertForbidden();
});
