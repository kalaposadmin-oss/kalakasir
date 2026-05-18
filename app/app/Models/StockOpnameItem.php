<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockOpnameItem extends TenantModel
{
    public $timestamps = true;

    protected $casts = [
        'system_qty' => 'float',
        'physical_qty' => 'float',
        'difference_qty' => 'float',
        'unit_cost' => 'float',
        'difference_value' => 'float',
    ];

    public function stockOpname(): BelongsTo
    {
        return $this->belongsTo(StockOpname::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
