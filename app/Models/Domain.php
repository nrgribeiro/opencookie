<?php

namespace App\Models;

use App\Enums\DomainVerifyStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Domain extends Model
{
    /** @use HasFactory<\Database\Factories\DomainFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'domain_uid',
        'hostname',
        'verify_status',
        'consent_expiry_days',
        'scheduled_scan_enabled',
        'scan_frequency',
        'last_scanned_at',
        'banner_live',
    ];

    protected function casts(): array
    {
        return [
            'verify_status' => DomainVerifyStatus::class,
            'consent_expiry_days' => 'integer',
            'scheduled_scan_enabled' => 'boolean',
            'last_scanned_at' => 'datetime',
            'banner_live' => 'boolean',
        ];
    }

    /** Route-bind by public uid, not sequential id. */
    public function getRouteKeyName(): string
    {
        return 'domain_uid';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verifications(): HasMany
    {
        return $this->hasMany(DomainVerification::class);
    }

    public function scans(): HasMany
    {
        return $this->hasMany(Scan::class);
    }

    public function cookies(): HasMany
    {
        return $this->hasMany(Cookie::class);
    }

    public function cookieOverrides(): HasMany
    {
        return $this->hasMany(CookieOverride::class);
    }

    public function bannerConfigs(): HasMany
    {
        return $this->hasMany(BannerConfig::class);
    }

    public function publishedBanner(): HasOne
    {
        return $this->hasOne(BannerConfig::class)->where('status', \App\Enums\BannerStatus::Published);
    }

    public function policyVersions(): HasMany
    {
        return $this->hasMany(PolicyVersion::class);
    }

    public function consentRecords(): HasMany
    {
        return $this->hasMany(ConsentRecord::class);
    }

    public function notificationSetting(): HasOne
    {
        return $this->hasOne(NotificationSetting::class);
    }

    public function bannerImpressions(): HasMany
    {
        return $this->hasMany(BannerImpression::class);
    }

    public function isVerified(): bool
    {
        return $this->verify_status === DomainVerifyStatus::Verified;
    }
}
