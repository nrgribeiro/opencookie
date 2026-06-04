<?php

namespace App\Models;

use App\Enums\CookieCategory;
use Database\Factories\CookieOverrideFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CookieOverride extends Model
{
    /** @use HasFactory<CookieOverrideFactory> */
    use HasFactory;

    protected $fillable = [
        'domain_id',
        'cookie_name',
        'source_domain',
        'category',
        'provider',
        'provider_url',
        'purpose',
        'purpose_translations',
    ];

    protected function casts(): array
    {
        return [
            'category' => CookieCategory::class,
            'purpose_translations' => 'array',
        ];
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }
}
