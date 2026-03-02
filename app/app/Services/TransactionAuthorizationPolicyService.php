<?php

namespace App\Services;

use App\Exceptions\AuthorizationLimitException;

class TransactionAuthorizationPolicyService
{
    public function __construct(private readonly UserRoleResolverService $roles)
    {
    }

    public function evaluateVoid(string $tenantId, ?string $actorUserId, float $amount): array
    {
        $actorRole = $this->roles->resolveNormalizedRole($tenantId, $actorUserId);

        if ($amount <= 250000) {
            return [
                'actor_role' => $actorRole,
                'direct_allowed' => in_array($actorRole, ['cashier', 'supervisor', 'admin', 'owner'], true),
                'allowed_approver_roles' => ['admin', 'owner'],
                'required_approver_level' => 'cashier+',
            ];
        }

        if ($amount <= 1500000) {
            return [
                'actor_role' => $actorRole,
                'direct_allowed' => in_array($actorRole, ['admin', 'owner'], true),
                'allowed_approver_roles' => ['admin', 'owner'],
                'required_approver_level' => 'admin',
            ];
        }

        return [
            'actor_role' => $actorRole,
            'direct_allowed' => $actorRole === 'owner',
            'allowed_approver_roles' => ['owner'],
            'required_approver_level' => 'owner',
        ];
    }

    public function ensureAdjustmentPostingAllowed(string $tenantId, ?string $actorUserId, float $amount): void
    {
        $actorRole = $this->roles->resolveNormalizedRole($tenantId, $actorUserId);

        if ($amount <= 500000 && in_array($actorRole, ['admin', 'owner'], true)) {
            return;
        }

        if ($amount > 500000 && $actorRole === 'owner') {
            return;
        }

        throw new AuthorizationLimitException(
            message: 'Nominal adjustment melewati batas otorisasi role saat ini.',
            details: [
                'actor_role' => $actorRole,
                'required_role' => $amount > 500000 ? 'owner' : 'admin_or_owner',
                'amount' => round($amount, 2),
            ],
        );
    }
}
