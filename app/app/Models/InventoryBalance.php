<?php

namespace App\Models;

class InventoryBalance extends TenantModel
{
	public $incrementing = false;

	protected $primaryKey = null;

	public $timestamps = false;

	public function uniqueIds(): array
	{
		return [];
	}
}
