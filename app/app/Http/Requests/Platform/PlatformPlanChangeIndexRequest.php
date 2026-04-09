<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

class PlatformPlanChangeIndexRequest extends FormRequest
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
            'mode' => ['nullable', 'in:upgrade,downgrade,renewal,manual_override'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'q' => ['nullable', 'string', 'max:100'],
            'sort_by' => ['nullable', 'in:created_at,mode'],
            'sort_dir' => ['nullable', 'in:asc,desc'],
        ];
    }
}
