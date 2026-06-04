<?php

namespace App\Jobs;

use App\Enums\CookieCategory;
use App\Enums\CookieStatus;
use App\Mail\NewCookieAlert;
use App\Models\Cookie;
use App\Models\Scan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * US-SCAN-4 / US-SET-3 — email the owner when a scan finds new or unclassified
 * cookies. Skipped when notifications are disabled.
 */
class SendCookieAlertJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $scanId) {}

    public function handle(): void
    {
        $scan = Scan::with(['domain.user', 'domain.notificationSetting'])->find($this->scanId);
        if (! $scan || ! $scan->domain) {
            return;
        }

        $domain = $scan->domain;
        $enabled = $domain->notificationSetting?->new_cookie_alerts ?? true;
        if (! $enabled || ! $domain->user?->email) {
            return;
        }

        $alertCookies = $domain->cookies()
            ->whereIn('status', [CookieStatus::New, CookieStatus::Active])
            ->where(function ($q): void {
                $q->where('status', CookieStatus::New)
                    ->orWhere('category', CookieCategory::Unclassified);
            })
            ->orderBy('name')
            ->limit(50)
            ->get();

        if ($alertCookies->isEmpty()) {
            return;
        }

        $unclassified = $alertCookies->filter(
            fn (Cookie $c) => $c->category === CookieCategory::Unclassified,
        )->count();

        $rows = $alertCookies->map(fn (Cookie $c) => [
            'name' => $c->name,
            'sourceDomain' => $c->source_domain,
            'category' => $c->category->value,
        ])->all();

        Mail::to($domain->user->email)->send(
            new NewCookieAlert($domain, $scan, $rows, $unclassified),
        );
    }
}
