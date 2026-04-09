<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

class PlatformUserIndexRequest extends FormRequest
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
            'role' => ['nullable', 'in:owner,admin,supervisor,cashier'],
            'status' => ['nullable', 'string', 'max:30'],
            'last_login_from' => ['nullable', 'date'],
            'last_login_to' => ['nullable', 'date'],
            'sort_by' => ['nullable', 'in:name,last_login_at,created_at'],
            'sort_dir' => ['nullable', 'in:asc,desc'],
        ];
    }
}
