<?php

namespace App\Models;

use App\Support\PurchaseMode;
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
        'business_category',
        'domain',
        'subdomain',
        'logo_url',
        'plan',
        'purchase_mode',
        'default_location_id',
        'auto_consume_on_sell_enabled',
        'allow_negative_raw_material_stock',
        'rounding_mode',
        'rounding_increment',
    ];

    protected $casts = [
        'auto_consume_on_sell_enabled' => 'boolean',
        'allow_negative_raw_material_stock' => 'boolean',
        'rounding_increment' => 'integer',
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

    public function getPurchaseModeAttribute($value): string
    {
        return PurchaseMode::normalize((string) $value);
    }

    public function setPurchaseModeAttribute($value): void
    {
        $this->attributes['purchase_mode'] = PurchaseMode::normalize((string) $value);
    }
}

