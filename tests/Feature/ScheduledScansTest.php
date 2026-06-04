<?php

use App\Jobs\RunScanJob;
use App\Models\Domain;
use Illuminate\Support\Facades\Queue;

it('dispatches scans only for matching verified, scheduled domains', function () {
    Queue::fake();

    $match = Domain::factory()->verified()->scheduledScans('monthly')->create();
    Domain::factory()->verified()->scheduledScans('weekly')->create();   // wrong frequency
    Domain::factory()->scheduledScans('monthly')->create();              // not verified
    Domain::factory()->verified()->create();                             // scheduling off

    $this->artisan('scans:dispatch-scheduled --frequency=monthly')
        ->assertSuccessful();

    expect($match->scans()->count())->toBe(1);
    Queue::assertPushed(RunScanJob::class, 1);
});
