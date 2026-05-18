<?php

namespace App\Models;

class OnlineOrderItem extends TenantModel
{
    protected $casts = [
        'price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'meta' => 'array',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];
}
