<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpeningStockLine extends TenantModel
{
    protected $fillable = [
        'opening_session_id',
        'tenant_id',
        'location_id',
        'product_id',
        'product_variant_id',
        'qty',
        'unit_cost',
        'line_value',
        'reference_note',
        'created_by',
    ];

    protected $casts = [
        'qty' => 'float',
        'unit_cost' => 'float',
        'line_value' => 'float',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(OpeningSession::class, 'opening_session_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
