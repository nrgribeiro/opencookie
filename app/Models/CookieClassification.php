<?php

namespace App\Models;

use App\Enums\CookieCategory;
use Illuminate\Database\Eloquent\Model;

/**
 * One entry in the in-house cookie classification DB. Populated from the Open
 * Cookie Database (source = ocd) or curated manually (source = manual).
 */
class CookieClassification extends Model
{
    protected $fillable = [
        'name',
        'domain',
        'category',
        'provider',
        'provider_url',
        'purpose',
        'retention',
        'data_controller',
        'gdpr_portal_url',
        'is_wildcard',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'category' => CookieCategory::class,
            'is_wildcard' => 'boolean',
        ];
    }
}
