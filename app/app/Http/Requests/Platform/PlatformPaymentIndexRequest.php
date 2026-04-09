<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

class PlatformPaymentIndexRequest extends FormRequest
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
            'q' => ['nullable', 'string', 'max:100'],
            'tenant_id' => ['nullable', 'uuid'],
            'status' => ['nullable', 'in:pending,paid,failed,refunded'],
            'paid_from' => ['nullable', 'date'],
            'paid_to' => ['nullable', 'date'],
            'sort_by' => ['nullable', 'in:paid_at,amount,status,created_at'],
            'sort_dir' => ['nullable', 'in:asc,desc'],
        ];
    }
}
