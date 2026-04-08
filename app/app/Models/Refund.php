<?php

namespace App\Models;

class Refund extends TenantModel
{
	public const STATUS_COMPLETED = 'completed';
	public const STATUS_PENDING = 'pending';
	public const STATUS_REJECTED = 'rejected';

    protected $casts = [
        'transaction_date' => 'date:Y-m-d',
    ];
}
