<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerCategory extends TenantModel
{
    public $timestamps = true;

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }
}
