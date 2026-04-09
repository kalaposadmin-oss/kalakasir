<?php

namespace App\Models;

use App\Models\ProductPriceTier;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends TenantModel
{
    use SoftDeletes;

    public const PRODUCTION_MODE_NONE = 'none';
    public const PRODUCTION_MODE_AUTO_CONSUME_ON_SELL = 'auto_consume_on_sell';
    public const PRODUCTION_MODE_BATCH = 'batch_production';

    public const PRODUCTION_MODES = [
        self::PRODUCTION_MODE_NONE,
        self::PRODUCTION_MODE_AUTO_CONSUME_ON_SELL,
        self::PRODUCTION_MODE_BATCH,
    ];

    public $timestamps = true;

    protected $casts = [
        'track_stock' => 'bool',
        'bom_items' => 'array',
        'variants' => 'array',
    ];

    protected $attributes = [
        'production_mode' => self::PRODUCTION_MODE_NONE,
    ];

    public function priceTiers()
    {
        return $this->hasMany(ProductPriceTier::class);
    }
}
