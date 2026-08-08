<?php

use App\Jobs\RunScanJob;
use App\Models\Domain;
use App\Models\Tier;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

it('dispatches scans only for matching verified, scheduled domains', function () {
    Queue::fake();

    $paidTier = Tier::factory()->create(['scheduled_scans_allowed' => true]);
    $paidUser = fn () => User::factory()->for($paidTier, 'tier')->create();

    $match = Domain::factory()->for($paidUser())->verified()->scheduledScans('monthly')->create();
    Domain::factory()->for($paidUser())->verified()->scheduledScans('weekly')->create();   // wrong frequency
    Domain::factory()->for($paidUser())->scheduledScans('monthly')->create();              // not verified
    Domain::factory()->for($paidUser())->verified()->create();                             // scheduling off

    $this->artisan('scans:dispatch-scheduled --frequency=monthly')
        ->assertSuccessful();

    expect($match->scans()->count())->toBe(1);
    Queue::assertPushed(RunScanJob::class, 1);
});

it('skips domains whose tier no longer allows scheduled scans', function () {
    Queue::fake();

    $freeTier = Tier::factory()->create(['scheduled_scans_allowed' => false]);
    $downgraded = User::factory()->for($freeTier, 'tier')->create();

    $skipped = Domain::factory()
        ->for($downgraded)
        ->verified()
        ->scheduledScans('monthly')
        ->create();

    $this->artisan('scans:dispatch-scheduled --frequency=monthly')
        ->assertSuccessful();

    expect($skipped->scans()->count())->toBe(0);
    Queue::assertNotPushed(RunScanJob::class);
});
