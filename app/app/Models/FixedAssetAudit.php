<?php

namespace App\Models;

class FixedAssetAudit extends TenantModel
{
    protected $fillable = [
        'tenant_id',
        'fixed_asset_id',
        'audit_date',
        'location_id',
        'physical_status',
        'condition',
        'notes',
        'audited_by',
    ];

    protected $casts = [
        'audit_date' => 'date',
    ];
}
