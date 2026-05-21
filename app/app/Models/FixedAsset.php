<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class FixedAsset extends TenantModel
{
    use SoftDeletes;

    public const STATUSES = ['active', 'maintenance', 'stored', 'disposed', 'sold', 'lost'];
    public const CONDITIONS = ['new', 'good', 'fair', 'needs_repair', 'broken'];
    public const DEPRECIATION_METHODS = ['straight_line', 'none'];

    protected $fillable = [
        'tenant_id',
        'asset_code',
        'name',
        'asset_category',
        'quantity',
        'unit_price',
        'location_id',
        'supplier_id',
        'payment_method',
        'payment_account_id',
        'fixed_asset_account_id',
        'accumulated_depreciation_account_id',
        'depreciation_expense_account_id',
        'purchase_journal_entry_id',
        'purchase_cash_transaction_id',
        'liability_paid_amount',
        'liability_status',
        'serial_number',
        'purchase_date',
        'purchase_price',
        'residual_value',
        'useful_life_months',
        'depreciation_method',
        'accumulated_depreciation',
        'book_value',
        'condition',
        'status',
        'warranty_until',
        'last_maintenance_at',
        'next_maintenance_at',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'warranty_until' => 'date',
        'last_maintenance_at' => 'date',
        'next_maintenance_at' => 'date',
        'purchase_price' => 'float',
        'quantity' => 'float',
        'unit_price' => 'float',
        'residual_value' => 'float',
        'accumulated_depreciation' => 'float',
        'book_value' => 'float',
        'liability_paid_amount' => 'float',
        'useful_life_months' => 'integer',
    ];
}
