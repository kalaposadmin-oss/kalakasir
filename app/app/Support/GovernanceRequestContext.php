<?php

namespace App\Support;

use App\Services\UserRoleResolverService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GovernanceRequestContext
{
    public static function validationRules(): array
    {
        return [
            'source_module' => ['nullable', 'string', 'max:50'],
            'requested_by_role' => ['nullable', 'string', 'max:50'],
            'approval_required' => ['nullable', 'boolean'],
        ];
    }

    public static function fromRequest(Request $request, array $validated): array
    {
        $tenantId = TenantContext::id();
        $user = $request->user();

        $resolver = app(UserRoleResolverService::class);
        $actorRole = $resolver->resolveNormalizedRole($tenantId ?? '', $user?->id)
            ?? $resolver->normalize((string) ($user?->role ?? ''));

        if (! $actorRole && $tenantId && $user?->id) {
            $fallbackRole = DB::table('user_roles')
                ->join('roles', function ($join): void {
                    $join->on('user_roles.role_id', '=', 'roles.id')
                        ->on('user_roles.tenant_id', '=', 'roles.tenant_id');
                })
                ->where('user_roles.tenant_id', $tenantId)
                ->where('user_roles.user_id', $user->id)
                ->value('roles.name');

            if ($fallbackRole) {
                $actorRole = $resolver->normalize((string) $fallbackRole);
            }
        }

        $requestedRoleRaw = isset($validated['requested_by_role'])
            ? trim((string) $validated['requested_by_role'])
            : '';

        $requestedRole = $requestedRoleRaw !== ''
            ? $resolver->normalize($requestedRoleRaw)
            : null;

        $approvalRequired = filter_var($validated['approval_required'] ?? false, FILTER_VALIDATE_BOOL);

        return [
            'source_module' => isset($validated['source_module']) ? trim((string) $validated['source_module']) : null,
            'requested_by_role' => $requestedRole,
            'approval_required' => (bool) $approvalRequired,
            'actor_role' => $actorRole ?: null,
        ];
    }

    public static function enforceRoleConsistency(array $context): void
    {
        if (($context['requested_by_role'] ?? null) === null) {
            return;
        }

        if (($context['actor_role'] ?? null) === null) {
            return;
        }

        if ($context['requested_by_role'] !== $context['actor_role']) {
            throw ValidationException::withMessages([
                'requested_by_role' => ['Role pada payload tidak sesuai role actor yang terautentikasi.'],
            ]);
        }
    }

    public static function enforceApprovalPolicy(array $context, string $operation): void
    {
        if (! ($context['approval_required'] ?? false)) {
            return;
        }

        $actorRole = strtolower((string) ($context['actor_role'] ?? ''));
        if ($actorRole === 'cashier') {
            throw ValidationException::withMessages([
                'approval_required' => [
                    'Aksi '.$operation.' memerlukan approval supervisor/admin untuk role kasir.',
                ],
            ]);
        }
    }

    public static function responseMeta(array $context): array
    {
        return [
            'source_module' => $context['source_module'],
            'requested_by_role' => $context['requested_by_role'],
            'approval_required' => $context['approval_required'],
            'actor_role' => $context['actor_role'],
        ];
    }
}
