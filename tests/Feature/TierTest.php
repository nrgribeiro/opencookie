<?php

use App\Models\Domain;
use App\Models\Tier;
use App\Models\User;

it('resolves the default tier when a user has none assigned', function () {
    $user = User::factory()->create(['tier_id' => null]);

    expect($user->resolveTier()->slug)->toBe('free')
        ->and($user->resolveTier()->is_default)->toBeTrue();
});

it('lets a higher tier add more than one domain', function () {
    $pro = Tier::where('slug', 'pro')->firstOrFail();
    $user = User::factory()->create(['tier_id' => $pro->id]);
    Domain::factory()->for($user)->create(['hostname' => 'first.com']);

    $this->actingAs($user)
        ->post('/domains', ['hostname' => 'second.com'])
        ->assertSessionHasNoErrors();

    expect($user->domains()->count())->toBe(2);
});

it('blocks a higher tier once its domain cap is reached', function () {
    $pro = Tier::where('slug', 'pro')->firstOrFail();
    $pro->update(['max_domains' => 2]);
    $user = User::factory()->create(['tier_id' => $pro->id]);
    Domain::factory()->for($user)->create(['hostname' => 'a.com']);
    Domain::factory()->for($user)->create(['hostname' => 'b.com']);

    $this->actingAs($user)
        ->post('/domains', ['hostname' => 'c.com'])
        ->assertSessionHasErrors('hostname');

    expect($user->domains()->count())->toBe(2);
});

it('imposes no domain cap on an unlimited tier', function () {
    $ent = Tier::where('slug', 'enterprise')->firstOrFail();
    $user = User::factory()->create(['tier_id' => $ent->id]);
    Domain::factory()->for($user)->create(['hostname' => 'one.com']);

    $this->actingAs($user)
        ->post('/domains', ['hostname' => 'two.com'])
        ->assertSessionHasNoErrors();

    expect($user->domains()->count())->toBe(2);
});
