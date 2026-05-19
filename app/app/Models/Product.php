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

    protected $fillable = [
        'tenant_id',
        'category_id',
        'sku',
        'name',
        'price',
        'hpp_average',
        'qty_on_hand',
        'min_stock',
        'unit',
        'supplier_id',
        'image_url',
        'is_active',
        'track_stock',
        'product_mode',
        'production_mode',
        'item_type',
        'bom_items',
        'recipe_output_qty',
        'bundle_items',
        'variants',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'track_stock' => 'bool',
        'bom_items' => 'array',
        'variants' => 'array',
        'recipe_output_qty' => 'float',
    ];

    protected $attributes = [
        'production_mode' => self::PRODUCTION_MODE_NONE,
    ];

    public function priceTiers()
    {
        return $this->hasMany(ProductPriceTier::class);
    }
}
