<?php

namespace App\Models;

class AuditLog extends TenantModel
{
	protected $casts = [
		'payload_json' => 'array',
		'created_at' => 'datetime',
	];
}
