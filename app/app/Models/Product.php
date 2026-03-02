<?php

namespace App\Models;

use App\Models\ProductPriceTier;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends TenantModel
{
    use SoftDeletes;

    public $timestamps = true;

    protected $casts = [
        'track_stock' => 'bool',
    ];

    public function priceTiers()
    {
        return $this->hasMany(ProductPriceTier::class);
    }
}
