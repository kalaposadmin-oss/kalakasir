<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

class PlatformSystemHealthIndexRequest extends FormRequest
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
            'status' => ['nullable', 'in:open,resolved,ignored'],
            'event_name' => ['nullable', 'string', 'max:120'],
            'source' => ['nullable', 'string', 'max:120'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'search' => ['nullable', 'string', 'max:255'],
            'sort_by' => ['nullable', 'in:latest,severity,occurrence_count'],
            'sort_dir' => ['nullable', 'in:asc,desc'],
        ];
    }
}