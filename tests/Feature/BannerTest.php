<?php

use App\Enums\BannerStatus;
use App\Models\Domain;
use App\Models\User;

function validBannerPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'layout' => [
            'type' => 'box',
            'position' => 'bottom-left',
            'theme' => 'light',
            'colors' => ['accent' => '#2563eb'],
            'logo' => null,
        ],
        'languages' => ['en'],
        'default_language' => 'en',
        'content' => [
            'en' => [
                'title' => 'We use cookies',
                'body' => 'We use cookies to improve your experience.',
                'acceptAll' => 'Accept all',
                'rejectAll' => 'Reject all',
                'customize' => 'Customize',
            ],
        ],
        'policy_url' => 'https://example.com/cookies',
        'consent_mode_map' => ['analytics_storage' => ['statistics']],
    ], $overrides);
}

it('opens the builder and scaffolds a draft', function () {
    $user = User::factory()->create();
    $domain = Domain::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('banner.edit', $domain))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('domains/banner')
            ->where('config.status', 'draft')
            ->where('config.version', 1)
        );

    expect($domain->bannerConfigs()->count())->toBe(1);
});

it('saves the draft', function () {
    $user = User::factory()->create();
    $domain = Domain::factory()->for($user)->create();

    $this->actingAs($user)
        ->put(route('banner.update', $domain), validBannerPayload([
            'content' => ['en' => ['title' => 'Custom title']],
        ]))
        ->assertRedirect()
        ->assertSessionHas('status');

    $draft = $domain->bannerConfigs()->first();
    expect($draft->content['en']['title'])->toBe('Custom title');
});

it('rejects an invalid layout type', function () {
    $user = User::factory()->create();
    $domain = Domain::factory()->for($user)->create();

    $this->actingAs($user)
        ->put(route('banner.update', $domain), validBannerPayload(['layout' => ['type' => 'spaceship']]))
        ->assertSessionHasErrors('layout.type');
});

it('rejects a default language not in the language list', function () {
    $user = User::factory()->create();
    $domain = Domain::factory()->for($user)->create();

    $this->actingAs($user)
        ->put(route('banner.update', $domain), validBannerPayload([
            'languages' => ['en'],
            'default_language' => 'pt',
        ]))
        ->assertSessionHasErrors('default_language');
});

it('publishes a complete draft', function () {
    $user = User::factory()->create();
    $domain = Domain::factory()->for($user)->create();

    $this->actingAs($user)->put(route('banner.update', $domain), validBannerPayload());
    $this->actingAs($user)
        ->post(route('banner.publish', $domain))
        ->assertRedirect()
        ->assertSessionHas('status', 'Banner published.');

    $published = $domain->bannerConfigs()->where('status', BannerStatus::Published)->first();
    expect($published)->not->toBeNull()
        ->and($published->published_at)->not->toBeNull();
});

it('blocks publishing without a policy url', function () {
    $user = User::factory()->create();
    $domain = Domain::factory()->for($user)->create();

    $this->actingAs($user)->put(route('banner.update', $domain), validBannerPayload(['policy_url' => null]));
    $this->actingAs($user)
        ->post(route('banner.publish', $domain))
        ->assertSessionHasErrors('banner');

    expect($domain->bannerConfigs()->where('status', BannerStatus::Published)->exists())->toBeFalse();
});

it('blocks publishing when the reject button text is missing (equal prominence)', function () {
    $user = User::factory()->create();
    $domain = Domain::factory()->for($user)->create();

    $payload = validBannerPayload();
    unset($payload['content']['en']['rejectAll']);

    $this->actingAs($user)->put(route('banner.update', $domain), $payload);
    $this->actingAs($user)
        ->post(route('banner.publish', $domain))
        ->assertSessionHasErrors('banner');
});

it('clones a new draft after publish and archives the old published version', function () {
    $user = User::factory()->create();
    $domain = Domain::factory()->for($user)->create();

    // Publish v1.
    $this->actingAs($user)->put(route('banner.update', $domain), validBannerPayload());
    $this->actingAs($user)->post(route('banner.publish', $domain));

    // Open builder again → new draft cloned at next version.
    $this->actingAs($user)->get(route('banner.edit', $domain));
    $draft = $domain->bannerConfigs()->where('status', BannerStatus::Draft)->first();
    expect($draft->version)->toBe(2);

    // Publish v2 → v1 archived.
    $this->actingAs($user)->post(route('banner.publish', $domain));
    expect($domain->bannerConfigs()->where('status', BannerStatus::Published)->count())->toBe(1)
        ->and($domain->bannerConfigs()->where('status', BannerStatus::Archived)->count())->toBe(1);
});

it('forbids editing another users banner', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $domain = Domain::factory()->for($owner)->create();

    $this->actingAs($other)
        ->get(route('banner.edit', $domain))
        ->assertForbidden();
});
