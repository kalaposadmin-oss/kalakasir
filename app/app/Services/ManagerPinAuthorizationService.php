<?php

namespace App\Services;

use App\Exceptions\ManagerPinException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class ManagerPinAuthorizationService
{
    public function authorizeVoid(
        string $tenantId,
        ?string $actorUserId,
        ?string $managerPin,
        ?string $ipAddress,
        array $allowedApproverRoles = ['supervisor', 'admin']
    ): string {
        $pin = trim((string) $managerPin);
        if ($pin === '') {
            throw new ManagerPinException(
                message: 'PIN manager wajib diisi.',
                errorCode: 'MANAGER_PIN_REQUIRED',
                status: 422,
            );
        }

        $key = $this->rateLimitKey($tenantId, $actorUserId, $ipAddress);
        $maxAttempts = max(1, (int) config('services.security.manager_pin.max_attempts', 5));
        $lockoutSeconds = max(30, (int) config('services.security.manager_pin.lockout_seconds', 900));

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            throw new ManagerPinException(
                message: 'Terlalu banyak percobaan PIN. Coba lagi beberapa saat.',
                errorCode: 'MANAGER_PIN_LOCKED',
                status: 429,
                details: [
                    'retry_after_seconds' => RateLimiter::availableIn($key),
                ],
            );
        }

        $normalizedAllowed = array_values(array_unique(array_map(
            fn (string $role) => app(UserRoleResolverService::class)->normalize($role),
            $allowedApproverRoles
        )));

        $candidates = DB::table('users')
            ->join('user_roles', function ($join) {
                $join->on('users.id', '=', 'user_roles.user_id')
                    ->on('users.tenant_id', '=', 'user_roles.tenant_id');
            })
            ->join('roles', function ($join) {
                $join->on('roles.id', '=', 'user_roles.role_id')
                    ->on('roles.tenant_id', '=', 'user_roles.tenant_id');
            })
            ->where('users.tenant_id', $tenantId)
            ->whereNull('users.deleted_at')
            ->whereNotNull('users.manager_pin_hash')
            ->select('users.id', 'users.manager_pin_hash', 'roles.name as role_name')
            ->distinct()
            ->get();

        foreach ($candidates as $candidate) {
            $candidateRole = app(UserRoleResolverService::class)->normalize((string) $candidate->role_name);
            if (! in_array($candidateRole, $normalizedAllowed, true)) {
                continue;
            }

            if (Hash::check($pin, (string) $candidate->manager_pin_hash)) {
                RateLimiter::clear($key);
                return (string) $candidate->id;
            }
        }

        RateLimiter::hit($key, $lockoutSeconds);

        throw new ManagerPinException(
            message: 'PIN manager tidak valid.',
            errorCode: 'MANAGER_PIN_INVALID',
            status: 422,
        );
    }

    private function rateLimitKey(string $tenantId, ?string $actorUserId, ?string $ipAddress): string
    {
        $actor = $actorUserId ?: 'guest';
        $ip = $ipAddress ?: 'unknown';

        return sprintf('security:manager-pin:void:%s:%s:%s', $tenantId, $actor, $ip);
    }
}
