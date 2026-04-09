<?php

namespace App\Http\Resources\Platform;

use Illuminate\Http\Resources\Json\JsonResource;

class AnomalyResource extends JsonResource
{
    public function toArray($request): array
    {
        return (array) $this->resource;
    }
}
