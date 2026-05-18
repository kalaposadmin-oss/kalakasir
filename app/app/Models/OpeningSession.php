<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OpeningSession extends TenantModel
{
    protected $fillable = [
        'tenant_id',
        'opening_date',
        'status',
        'equity_account_id',
        'equity_amount',
        'posted_at',
        'posted_by',
        'locked_at',
        'created_by',
        'meta',
    ];

    protected $casts = [
        'opening_date' => 'date',
        'posted_at' => 'datetime',
        'locked_at' => 'datetime',
        'equity_amount' => 'float',
        'meta' => 'array',
    ];

    public function stockLines(): HasMany
    {
        return $this->hasMany(OpeningStockLine::class, 'opening_session_id');
    }

    public function fundLines(): HasMany
    {
        return $this->hasMany(OpeningFundLine::class, 'opening_session_id');
    }

    public function postingLogs(): HasMany
    {
        return $this->hasMany(OpeningPostingLog::class, 'opening_session_id');
    }

    public function equityAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'equity_account_id');
    }
}
