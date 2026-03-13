<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Tenant extends Model
{
    use HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'name',
        'timezone',
        'inventory_valuation_method',
        'domain',
        'subdomain',
        'logo_url',
        'plan',
        'default_location_id',
        'auto_consume_on_sell_enabled',
        'allow_negative_raw_material_stock',
    ];

    protected $casts = [
        'auto_consume_on_sell_enabled' => 'boolean',
        'allow_negative_raw_material_stock' => 'boolean',
    ];

    public function getPlanAttribute($value): string
    {
        $normalized = strtoupper(trim((string) $value));

        return in_array($normalized, ['S', 'M', 'P'], true) ? $normalized : 'S';
    }

    public function setPlanAttribute($value): void
    {
        $normalized = strtoupper(trim((string) $value));
        $this->attributes['plan'] = in_array($normalized, ['S', 'M', 'P'], true) ? $normalized : 'S';
    }
}
