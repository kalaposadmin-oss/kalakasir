<?php

namespace App\Models;

class PaymentSetting extends TenantModel
{
    protected $casts = [
        'is_active' => 'bool',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];
}
