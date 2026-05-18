<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpeningFundLine extends TenantModel
{
    protected $fillable = [
        'opening_session_id',
        'tenant_id',
        'account_id',
        'source_type',
        'amount',
        'reference_note',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'float',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(OpeningSession::class, 'opening_session_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }
}
