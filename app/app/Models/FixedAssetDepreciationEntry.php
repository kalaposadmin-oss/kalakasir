<?php

namespace App\Models;

class FixedAssetDepreciationEntry extends TenantModel
{
    protected $fillable = [
        'tenant_id',
        'fixed_asset_id',
        'period_date',
        'depreciation_amount',
        'accumulated_depreciation',
        'book_value',
        'journal_entry_id',
        'posted_by',
        'posted_at',
    ];

    protected $casts = [
        'period_date' => 'date',
        'posted_at' => 'datetime',
        'depreciation_amount' => 'float',
        'accumulated_depreciation' => 'float',
        'book_value' => 'float',
    ];
}
