<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

class PlatformAnomalyIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'tenant_id' => ['nullable', 'uuid'],
            'severity' => ['nullable', 'in:low,medium,high,critical'],
            'resolved' => ['nullable', 'in:true,false'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'sort_by' => ['nullable', 'in:detected_at,severity'],
            'sort_dir' => ['nullable', 'in:asc,desc'],
        ];
    }
}
