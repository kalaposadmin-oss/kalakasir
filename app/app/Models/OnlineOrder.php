<?php

namespace App\Models;

class OnlineOrder extends TenantModel
{
    protected $casts = [
        'subtotal' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'shipping_discount' => 'decimal:2',
        'extra_fee' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'token_expires_at' => 'immutable_datetime',
        'shipping_filled_at' => 'immutable_datetime',
        'payment_message_sent_at' => 'immutable_datetime',
        'payment_verified_at' => 'immutable_datetime',
        'cancelled_at' => 'immutable_datetime',
        'completed_at' => 'immutable_datetime',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];

    public function items()
    {
        return $this->hasMany(OnlineOrderItem::class, 'online_order_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function reservations()
    {
        return $this->hasMany(StockReservation::class, 'online_order_id');
    }
}
