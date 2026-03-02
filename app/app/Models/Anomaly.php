<?php

namespace App\Models;

class Anomaly extends TenantModel
{
	protected $casts = [
		'payload_json' => 'array',
		'detected_at' => 'datetime',
		'resolved_at' => 'datetime',
	];
}
