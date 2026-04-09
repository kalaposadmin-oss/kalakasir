<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Discount extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'sale_id',
        'type',
        'value',
        'notes',
        'created_at',
    ];
}
