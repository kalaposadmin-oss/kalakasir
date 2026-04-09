<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class Material extends TenantModel
{
    use SoftDeletes;

    protected $casts = [
        'is_active' => 'bool',
        'unit_cost' => 'float',
    ];
}
