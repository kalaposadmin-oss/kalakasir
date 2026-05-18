<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SystemHealthEvent extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'payload_json' => 'array',
        'detected_at' => 'datetime',
        'first_detected_at' => 'datetime',
        'resolved_at' => 'datetime',
        'last_alerted_at' => 'datetime',
    ];

    public function occurrences(): HasMany
    {
        return $this->hasMany(SystemHealthEventOccurrence::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}