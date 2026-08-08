<?php

use App\Models\Domain;
use App\Models\Tier;
use App\Models\User;

it('rejects enabling scheduled scans when the tier does not allow it', function () {
    $freeTier = Tier::factory()->create(['scheduled_scans_allowed' => false]);
    $user = User::factory()->for($freeTier, 'tier')->create();
    $domain = Domain::factory()->for($user)->verified()->create();

    $response = $this->actingAs($user)->put(route('domain-settings.update', $domain), [
        'consentExpiryDays' => 365,
        'scheduledScanEnabled' => true,
        'scanFrequency' => 'monthly',
        'newCookieAlerts' => true,
    ]);

    $response->assertSessionHasErrors('scheduledScanEnabled');
    expect($domain->refresh()->scheduled_scan_enabled)->toBeFalse();
});

it('allows enabling scheduled scans when the tier allows it', function () {
    $tier = Tier::factory()->create(['scheduled_scans_allowed' => true]);
    $user = User::factory()->for($tier, 'tier')->create();
    $domain = Domain::factory()->for($user)->verified()->create();

    $response = $this->actingAs($user)->put(route('domain-settings.update', $domain), [
        'consentExpiryDays' => 365,
        'scheduledScanEnabled' => true,
        'scanFrequency' => 'monthly',
        'newCookieAlerts' => true,
    ]);

    $response->assertSessionHasNoErrors();
    expect($domain->refresh()->scheduled_scan_enabled)->toBeTrue();
});
