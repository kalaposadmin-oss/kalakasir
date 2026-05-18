<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends TenantModel
{
    protected $casts = [
        'order_link_token_created_at' => 'immutable_datetime',
        'order_link_token_rotated_at' => 'immutable_datetime',
    ];

    public function receivables(): HasMany
    {
        return $this->hasMany(Receivable::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CustomerCategory::class, 'customer_category_id');
    }
}
