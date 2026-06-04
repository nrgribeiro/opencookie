<?php

use App\Enums\DomainVerifyStatus;
use App\Models\Domain;
use App\Models\User;

it('redirects guests away from domains index', function () {
    $this->get('/domains')->assertRedirect('/login');
});

it('lists the owner domains', function () {
    $user = User::factory()->create();
    Domain::factory()->for($user)->create(['hostname' => 'example.com']);

    $this->actingAs($user)
        ->get('/domains')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('domains/index')
            ->has('domains', 1)
            ->where('canCreate', false)
        );
});

it('creates a domain with verification and notification defaults', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/domains', [
        'hostname' => 'example.com',
    ]);

    $domain = Domain::firstWhere('hostname', 'example.com');

    expect($domain)->not->toBeNull()
        ->and($domain->user_id)->toBe($user->id)
        ->and($domain->verify_status)->toBe(DomainVerifyStatus::Pending)
        ->and($domain->domain_uid)->toStartWith('dom_')
        ->and($domain->verifications()->count())->toBe(1)
        ->and($domain->notificationSetting()->exists())->toBeTrue();

    $response->assertRedirect(route('domains.show', $domain));
});

it('normalizes hostname by stripping scheme, path and case', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/domains', [
        'hostname' => 'https://Example.com/some/path',
    ]);

    expect(Domain::firstWhere('hostname', 'example.com'))->not->toBeNull();
});

it('rejects an invalid hostname', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/domains', ['hostname' => 'not a domain'])
        ->assertSessionHasErrors('hostname');

    expect(Domain::count())->toBe(0);
});

it('rejects a duplicate hostname', function () {
    $user = User::factory()->create();
    Domain::factory()->create(['hostname' => 'example.com']);

    $this->actingAs($user)
        ->post('/domains', ['hostname' => 'example.com'])
        ->assertSessionHasErrors('hostname');
});

it('enforces the free-tier single-domain cap', function () {
    $user = User::factory()->create();
    Domain::factory()->for($user)->create(['hostname' => 'first.com']);

    $this->actingAs($user)
        ->post('/domains', ['hostname' => 'second.com'])
        ->assertSessionHasErrors('hostname');

    expect($user->domains()->count())->toBe(1);
});

it('lets the owner view a domain', function () {
    $user = User::factory()->create();
    $domain = Domain::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('domains.show', $domain))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('domains/show')
            ->where('domain.hostname', $domain->hostname)
            ->has('snippet')
        );
});

it('forbids viewing another users domain', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $domain = Domain::factory()->for($owner)->create();

    $this->actingAs($other)
        ->get(route('domains.show', $domain))
        ->assertForbidden();
});

it('lets the owner delete a domain', function () {
    $user = User::factory()->create();
    $domain = Domain::factory()->for($user)->create();

    $this->actingAs($user)
        ->delete(route('domains.destroy', $domain))
        ->assertRedirect(route('domains.index'));

    expect(Domain::count())->toBe(0);
});

it('forbids deleting another users domain', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $domain = Domain::factory()->for($owner)->create();

    $this->actingAs($other)
        ->delete(route('domains.destroy', $domain))
        ->assertForbidden();

    expect(Domain::count())->toBe(1);
});
