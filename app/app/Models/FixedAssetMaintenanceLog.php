<?php

namespace App\Models;

class FixedAssetMaintenanceLog extends TenantModel
{
    public $timestamps = true;

    protected $fillable = [
        'tenant_id',
        'fixed_asset_id',
        'maintenance_date',
        'maintenance_type',
        'vendor_name',
        'cost',
        'condition_before',
        'condition_after',
        'next_maintenance_at',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'maintenance_date' => 'date',
        'next_maintenance_at' => 'date',
        'cost' => 'float',
    ];
}
