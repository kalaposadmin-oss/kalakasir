<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use App\Support\WebPortalResolver;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PortalSwitchService
{
    public function __construct(
        private readonly UserRoleResolverService $roleResolver,
        private readonly WebPortalResolver $portalResolver,
    ) {
    }

    public function switch(Request $request, string $portal, ?User $user): string
    {
        if (! in_array($portal, ['pos', 'backoffice'], true)) {
            throw new HttpException(404);
        }

        if (! $user) {
            throw new HttpException(401);
        }

        $accessError = $this->portalAccessError($portal, $this->resolvePortalRole($user));
        if ($accessError !== null) {
            throw new HttpException(403, $accessError);
        }

        $request->session()->put('auth_portal', $portal);

        $targetPath = $this->portalResolver->sanitizeNextPath(
            (string) $request->query('next', ''),
            $portal,
            $this->portalDashboard($portal)
        );

        return $this->portalResolver->portalUrl($portal, $targetPath, $request);
    }

    private function resolvePortalRole(User $user): ?string
    {
        $previousTenant = TenantContext::get();
        $tenantId = (string) ($user->tenant_id ?: '');

        try {
            if ($tenantId !== '') {
                $tenant = Tenant::query()->find($tenantId);
                if ($tenant) {
                    TenantContext::set($tenant);
                }
            }

            $role = $this->roleResolver->resolveNormalizedRole($tenantId, (string) $user->getAuthIdentifier());
            if ($role) {
                return $role;
            }

            $fallbackRole = trim((string) ($user->role ?? ''));

            return $fallbackRole !== '' ? $this->roleResolver->normalize($fallbackRole) : null;
        } finally {
            if ($previousTenant) {
                TenantContext::set($previousTenant);
            } else {
                TenantContext::clear();
            }
        }
    }

    private function portalAccessError(string $portal, ?string $role): ?string
    {
        $allowedRoles = $portal === 'backoffice'
            ? ['warehouse', 'supervisor', 'admin', 'owner', 'superadmin']
            : ['cashier', 'staff', 'warehouse', 'supervisor', 'admin', 'owner', 'superadmin'];

        if ($portal === 'pos' && $role === null) {
            return null;
        }

        if (! in_array($role, $allowedRoles, true)) {
            return $portal === 'backoffice'
                ? 'Akun ini tidak memiliki akses ke backoffice.'
                : 'Akun ini tidak memiliki akses ke POS.';
        }

        return null;
    }

    private function portalDashboard(string $portal): string
    {
        return $portal === 'backoffice' ? '/backoffice/dashboard' : '/pos/dashboard';
    }
}