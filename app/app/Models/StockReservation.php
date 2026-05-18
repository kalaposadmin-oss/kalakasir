<?php

namespace App\Models;

class StockReservation extends TenantModel
{
    protected $casts = [
        'qty' => 'integer',
        'expires_at' => 'immutable_datetime',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];
}
