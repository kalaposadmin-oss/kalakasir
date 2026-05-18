<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\UserRoleResolverService;
use App\Services\WebRouteContextService;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OpeningBalancePageController extends Controller
{
    public function __construct(
        private readonly WebRouteContextService $routeContext,
        private readonly UserRoleResolverService $roleResolver,
    ) {
    }

    public function index(Request $request): View
    {
        $tenant = TenantContext::get();
        $user = $request->user();

        if (! $tenant && filled($user?->tenant_id)) {
            $tenant = Tenant::query()->find($user->tenant_id);
        }

        $role = null;
        if ($tenant && $user) {
            $role = $this->roleResolver->resolveNormalizedRole((string) $tenant->id, (string) $user->id);

            if (! $role && filled($user->role ?? null)) {
                $role = $this->roleResolver->normalize((string) $user->role);
            }
        }

        abort_unless(in_array($role, ['admin', 'owner', 'superadmin'], true), 403);

        return view('opening-balance', $this->routeContext->withTenant([
            'showMenu' => true,
            'currentPath' => '/admin/opening-balance',
            'backUrl' => '/backoffice/dashboard',
        ]));
    }
}
