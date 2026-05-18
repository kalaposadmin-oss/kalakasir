<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemHealthEventOccurrence extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'payload_json' => 'array',
        'detected_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(SystemHealthEvent::class, 'system_health_event_id');
    }
}