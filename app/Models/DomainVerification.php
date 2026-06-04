<?php

namespace App\Models;

use App\Enums\VerificationMethod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DomainVerification extends Model
{
    /** @use HasFactory<\Database\Factories\DomainVerificationFactory> */
    use HasFactory;

    protected $fillable = [
        'domain_id',
        'method',
        'token',
        'verified_at',
        'last_checked_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'method' => VerificationMethod::class,
            'verified_at' => 'datetime',
            'last_checked_at' => 'datetime',
        ];
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }
}
