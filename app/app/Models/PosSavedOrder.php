<?php

namespace App\Models;

class PosSavedOrder extends TenantModel
{
    protected $casts = [
        'payload' => 'array',
        'created_at' => 'immutable_datetime',
    ];
}
