<?php

namespace App\Models;

use Database\Factories\TierFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tier extends Model
{
    /** @use HasFactory<TierFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'max_domains',
        'max_scan_pages',
        'monthly_pageview_cap',
        'scheduled_scans_allowed',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'max_domains' => 'integer',
            'max_scan_pages' => 'integer',
            'monthly_pageview_cap' => 'integer',
            'scheduled_scans_allowed' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    /** @return HasMany<User> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * The fallback tier for users with no explicit assignment.
     * Cached for the request to avoid repeated lookups.
     */
    public static function default(): self
    {
        return once(fn () => static::where('is_default', true)->firstOrFail());
    }

    /** Null max_domains / monthly_pageview_cap means unlimited. */
    public function allowsUnlimitedDomains(): bool
    {
        return $this->max_domains === null;
    }
}
