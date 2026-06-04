<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PolicyVersion extends Model
{
    /** @use HasFactory<\Database\Factories\PolicyVersionFactory> */
    use HasFactory;

    protected $fillable = [
        'domain_id',
        'version',
        'effective_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'effective_at' => 'datetime',
        ];
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }
}
