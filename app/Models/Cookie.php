<?php

namespace App\Models;

use App\Enums\CookieCategory;
use App\Enums\CookieStatus;
use App\Enums\CookieType;
use Database\Factories\CookieFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cookie extends Model
{
    /** @use HasFactory<CookieFactory> */
    use HasFactory;

    protected $fillable = [
        'domain_id',
        'scan_id',
        'name',
        'provider',
        'provider_url',
        'category',
        'purpose',
        'retention',
        'data_controller',
        'gdpr_portal_url',
        'expiry',
        'type',
        'source_domain',
        'is_first_party',
        'status',
        'first_seen_at',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'category' => CookieCategory::class,
            'type' => CookieType::class,
            'status' => CookieStatus::class,
            'is_first_party' => 'boolean',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function scan(): BelongsTo
    {
        return $this->belongsTo(Scan::class);
    }
}
