<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BannerImpression extends Model
{
    /** @use HasFactory<\Database\Factories\BannerImpressionFactory> */
    use HasFactory;

    protected $fillable = [
        'domain_id',
        'day',
        'banner_version',
        'language',
        'count',
    ];

    protected function casts(): array
    {
        return [
            'day' => 'date',
            'banner_version' => 'integer',
            'count' => 'integer',
        ];
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }
}
