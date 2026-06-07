<?php

use App\Enums\Role;
use App\Models\Domain;
use App\Models\Tier;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function superAdmin(): User
{
    return User::factory()->create()->assignRole(Role::SuperAdmin->value);
}

it('blocks non-admins from the admin area', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/admin')->assertForbidden();
    $this->actingAs($user)->get('/admin/users')->assertForbidden();
    $this->actingAs($user)->get('/admin/tiers')->assertForbidden();
});

it('redirects guests to login', function () {
    $this->get('/admin')->assertRedirect('/login');
});

it('shows the platform overview to a super admin', function () {
    $admin = superAdmin();
    Domain::factory()->create(['hostname' => 'a.com']); // not compliant (no banner)

    $this->withoutVite()->actingAs($admin)
        ->get('/admin')
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/dashboard', false)
            ->where('stats.users', fn ($v) => $v >= 1)
            ->where('stats.nonCompliantDomains', 1)
            ->has('nonCompliant.0.failing'));
});

it('lists users for a super admin', function () {
    $admin = superAdmin();

    $this->withoutVite()->actingAs($admin)
        ->get('/admin/users')
        ->assertInertia(fn (Assert $page) => $page->component('admin/users/index', false)->has('users.data'));
});

it('assigns a tier and grants super admin to a user', function () {
    $admin = superAdmin();
    $target = User::factory()->create();
    $pro = Tier::where('slug', 'pro')->firstOrFail();

    $this->actingAs($admin)
        ->put("/admin/users/{$target->id}", ['tier_id' => $pro->id, 'is_super_admin' => true])
        ->assertRedirect('/admin/users');

    $target->refresh();
    expect($target->tier_id)->toBe($pro->id)
        ->and($target->hasRole(Role::SuperAdmin->value))->toBeTrue();
});

it('refuses to revoke the last super admin', function () {
    $admin = superAdmin();

    $this->actingAs($admin)
        ->put("/admin/users/{$admin->id}", ['tier_id' => null, 'is_super_admin' => false])
        ->assertSessionHasErrors('is_super_admin');

    expect($admin->fresh()->hasRole(Role::SuperAdmin->value))->toBeTrue();
});

it('refuses to delete the last super admin', function () {
    $admin = superAdmin();

    $this->actingAs($admin)
        ->delete("/admin/users/{$admin->id}")
        ->assertSessionHasErrors('user');

    expect(User::find($admin->id))->not->toBeNull();
});

it('creates a tier and enforces a single default', function () {
    $admin = superAdmin();

    $this->actingAs($admin)
        ->post('/admin/tiers', [
            'name' => 'Scale',
            'slug' => 'scale',
            'max_domains' => 50,
            'max_scan_pages' => 1000,
            'monthly_pageview_cap' => 5000000,
            'scheduled_scans_allowed' => true,
            'is_default' => true,
        ])
        ->assertRedirect('/admin/tiers');

    expect(Tier::where('is_default', true)->count())->toBe(1)
        ->and(Tier::where('slug', 'scale')->first()->is_default)->toBeTrue();
});

it('refuses to delete the default tier', function () {
    $admin = superAdmin();
    $free = Tier::where('slug', 'free')->firstOrFail();

    $this->actingAs($admin)
        ->delete("/admin/tiers/{$free->id}")
        ->assertSessionHasErrors('tier');

    expect(Tier::find($free->id))->not->toBeNull();
});

it('refuses to delete a tier with users assigned', function () {
    $admin = superAdmin();
    $pro = Tier::where('slug', 'pro')->firstOrFail();
    User::factory()->create(['tier_id' => $pro->id]);

    $this->actingAs($admin)
        ->delete("/admin/tiers/{$pro->id}")
        ->assertSessionHasErrors('tier');

    expect(Tier::find($pro->id))->not->toBeNull();
});

it('deletes an empty non-default tier', function () {
    $admin = superAdmin();
    $tier = Tier::factory()->create(['slug' => 'temp']);

    $this->actingAs($admin)
        ->delete("/admin/tiers/{$tier->id}")
        ->assertRedirect('/admin/tiers');

    expect(Tier::find($tier->id))->toBeNull();
});
