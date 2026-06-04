<?php

namespace App\Models;

use App\Enums\BannerStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BannerConfig extends Model
{
    /** @use HasFactory<\Database\Factories\BannerConfigFactory> */
    use HasFactory;

    protected $fillable = [
        'domain_id',
        'version',
        'status',
        'layout',
        'content',
        'languages',
        'default_language',
        'policy_url',
        'consent_mode_map',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => BannerStatus::class,
            'version' => 'integer',
            'layout' => 'array',
            'content' => 'array',
            'languages' => 'array',
            'consent_mode_map' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function isPublished(): bool
    {
        return $this->status === BannerStatus::Published;
    }
}
