<?php

namespace App\Models;

class ProductionBatch extends TenantModel
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_POSTED = 'posted';
    public const STATUS_CANCELLED = 'cancelled';

    public $timestamps = true;

    protected $casts = [
        'transaction_date' => 'date:Y-m-d',
        'expected_output_qty' => 'float',
        'actual_output_qty' => 'float',
        'yield_variance_qty' => 'float',
        'yield_variance_pct' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'posted_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];
}