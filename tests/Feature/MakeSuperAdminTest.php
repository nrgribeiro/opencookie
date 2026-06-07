<?php

use App\Enums\Role;
use App\Models\User;

it('promotes an existing user to super admin', function () {
    $user = User::factory()->create(['email' => 'ops@example.com']);

    $this->artisan('user:make-admin', ['email' => 'ops@example.com'])
        ->assertSuccessful();

    expect($user->fresh()->hasRole(Role::SuperAdmin->value))->toBeTrue();
});

it('fails when the user does not exist', function () {
    $this->artisan('user:make-admin', ['email' => 'nobody@example.com'])
        ->assertFailed();
});

it('is idempotent for an existing super admin', function () {
    $user = User::factory()->create(['email' => 'ops@example.com'])
        ->assignRole(Role::SuperAdmin->value);

    $this->artisan('user:make-admin', ['email' => 'ops@example.com'])
        ->assertSuccessful();

    expect($user->fresh()->hasRole(Role::SuperAdmin->value))->toBeTrue();
});
