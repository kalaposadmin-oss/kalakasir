<?php

namespace App\Models;

class ProductionBatchItem extends TenantModel
{
	public $timestamps = true;

	protected $casts = [
		'qty' => 'float',
		'planned_qty' => 'float',
		'actual_qty' => 'float',
		'recipe_factor' => 'float',
		'unit_cost_snapshot' => 'float',
		'created_at' => 'datetime',
		'updated_at' => 'datetime',
	];
}
