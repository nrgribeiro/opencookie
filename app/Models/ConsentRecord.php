<?php

namespace App\Models;

use App\Enums\ConsentMethod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only consent proof. Records are immutable: no updates, no UPDATED_AT.
 * Purged by partition drop at the 24-month retention boundary (see data-model.md §3).
 */
class ConsentRecord extends Model
{
    /** @use HasFactory<\Database\Factories\ConsentRecordFactory> */
    use HasFactory;

    /** Immutable: only created_at is tracked. */
    public const UPDATED_AT = null;

    protected $fillable = [
        'domain_id',
        'consent_id',
        'method',
        'categories',
        'banner_version',
        'policy_version',
        'consent_text_hash',
        'ip_hash',
        'user_agent',
        'language',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'method' => ConsentMethod::class,
            'categories' => 'array',
            'banner_version' => 'integer',
            'policy_version' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }
}
