<?php

namespace App\Models;

class Setting extends TenantModel
{
    public $timestamps = true;

    protected $casts = [
        'value_json' => 'array',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];
}
