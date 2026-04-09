<?php

namespace App\Http\Resources\Platform;

use Illuminate\Http\Resources\Json\JsonResource;

class TenantDetailResource extends JsonResource
{
    public function toArray($request): array
    {
        return (array) $this->resource;
    }
}
