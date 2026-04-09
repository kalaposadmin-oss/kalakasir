<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

class PlatformSupportActionIndexRequest extends FormRequest
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
            'actor_id' => ['nullable', 'uuid'],
            'action_type' => [
                'nullable',
                'in:tenant_suspended,tenant_unsuspended,tenant_impersonated,tenant_impersonation_ended,tenant_force_logout,tenant_reset_owner,tenant_unlock,user_reset_password,user_force_logout,user_unlock,anomaly_resolved',
            ],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'q' => ['nullable', 'string', 'max:100'],
            'sort_by' => ['nullable', 'in:created_at,action_type'],
            'sort_dir' => ['nullable', 'in:asc,desc'],
        ];
    }
}
