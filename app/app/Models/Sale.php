<?php

namespace App\Models;

class Sale extends TenantModel
{
	public const STATUS_DRAFT = 'draft';
	public const STATUS_COMPLETED = 'completed';
	public const STATUS_VOIDED = 'voided';
}
