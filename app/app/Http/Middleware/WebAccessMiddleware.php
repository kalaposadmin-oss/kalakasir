<?php

namespace App\Http\Middleware;

use App\Services\SubscriptionService;
use App\Services\UserRoleResolverService;
use App\Support\TenantContext;
use App\Support\WebPortalResolver;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class WebAccessMiddleware
{
    public function handle(Request $request, Closure $next, ...$params): Response
    {
        if (count($params) === 1 && str_contains((string) $params[0], '|')) {
            $rawParts = array_values(array_filter(explode('|', (string) $params[0]), 'strlen'));
            $requiredPortal = strtolower((string) ($rawParts[0] ?? '*'));
            $featureKey = (string) (count($rawParts) > 1 ? $rawParts[count($rawParts) - 1] : '*');
            $rolesArg = '*';
            if (count($rawParts) > 2) {
                $rolesArg = implode('|', array_slice($rawParts, 1, -1));
            }
        } else {
            $requiredPortal = strtolower((string) ($params[0] ?? '*'));
            $rolesArg = (string) ($params[1] ?? '*');
            $featureKey = (string) ($params[2] ?? '*');
        }

        if (! Auth::check()) {
            if ($request->is('platform') || $request->is('platform/*')) {
                return redirect('/platform/login');
            }

            return redirect($requiredPortal === 'backoffice' ? '/backoffice' : '/');
        }

        $activePortal = $this->resolveActivePortal($request, $requiredPortal);
        if ($requiredPortal !== '*' && $requiredPortal !== 'any' && $activePortal !== $requiredPortal) {
            return redirect($activePortal === 'backoffice' ? '/backoffice/dashboard' : pos_default_url(Auth::user()));
        }

        if ($rolesArg !== '*' && $rolesArg !== '' && ! $this->isImpersonatingSuperadmin($request)) {
            $allowedRoles = array_values(array_filter(array_map('trim', explode('|', $rolesArg))));
            $tenantId = TenantContext::id() ?? Auth::user()?->tenant_id;
            $resolver = app(UserRoleResolverService::class);
            $role = $resolver->resolveNormalizedRole((string) $tenantId, (string) Auth::id());
            if (! $role) {
                $fallbackRole = (string) (Auth::user()?->role ?? '');
                $role = $fallbackRole !== '' ? $resolver->normalize($fallbackRole) : null;
            }
            if (! $role && $requiredPortal === 'pos') {
                $role = 'staff';
            }
            if (! $role || ! in_array($role, $allowedRoles, true)) {
                abort(403, 'Anda tidak memiliki akses ke halaman ini.');
            }
        }

        if ($featureKey !== '*' && $featureKey !== '') {
            $tenant = TenantContext::get();
            if (! $tenant) {
                $fallbackTenantId = Auth::user()?->tenant_id;
                if ($fallbackTenantId) {
                    $tenant = Tenant::query()->find($fallbackTenantId);
                }
            }

            if ($tenant && ! app(SubscriptionService::class)->canAccessFeature($tenant, $featureKey)) {
                abort(403, 'Fitur tidak tersedia di paket langganan saat ini.');
            }
        }

        return $next($request);
    }

    private function isImpersonatingSuperadmin(Request $request): bool
    {
        if (! $request->hasSession() || ! $request->session()->has('impersonation')) {
            return false;
        }

        $impersonation = $request->session()->get('impersonation');
        if (! is_array($impersonation)) {
            return false;
        }

        $expiresAt = $impersonation['expires_at'] ?? null;
        if ($expiresAt) {
            try {
                if (CarbonImmutable::parse($expiresAt, 'UTC')->isPast()) {
                    return false;
                }
            } catch (\Throwable) {
                return false;
            }
        }

        $user = Auth::user();
        if (! $user) {
            return false;
        }

        $roleFromProfile = $this->normalizeRoleName((string) ($user->role ?? ''));
        if ($roleFromProfile === 'superadmin') {
            return true;
        }

        return DB::table('user_roles')
            ->join('roles', function ($join) {
                $join->on('user_roles.role_id', '=', 'roles.id')
                    ->on('user_roles.tenant_id', '=', 'roles.tenant_id');
            })
            ->where('user_roles.user_id', $user->id)
            ->whereIn('roles.name', ['superadmin', 'super-admin', 'platform-admin'])
            ->exists();
    }

    private function normalizeRoleName(string $role): string
    {
        $role = strtolower(trim($role));

        return match ($role) {
            'superadmin', 'super-admin', 'platform-admin' => 'superadmin',
            'owner', 'pemilik' => 'owner',
            'kasir', 'cashier' => 'cashier',
            'manager', 'supervisor' => 'supervisor',
            'admin', 'administrator' => 'admin',
            'staf gudang', 'warehouse' => 'warehouse',
            default => preg_replace('/\s+/', '-', $role),
        };
    }

    private function resolveActivePortal(Request $request, string $requiredPortal): string
    {
        $fallbackPortal = in_array($requiredPortal, ['pos', 'backoffice'], true) ? $requiredPortal : 'pos';

        return app(WebPortalResolver::class)->resolveActivePortal($request, $fallbackPortal);
    }
}
