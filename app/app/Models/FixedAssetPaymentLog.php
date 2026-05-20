<?php

namespace App\Models;

class FixedAssetPaymentLog extends TenantModel
{
    protected $fillable = [
        'tenant_id',
        'fixed_asset_id',
        'payment_date',
        'payment_account_id',
        'journal_entry_id',
        'cash_transaction_id',
        'amount',
        'notes',
        'created_by',
        'created_at',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'float',
        'created_at' => 'datetime',
    ];
}
