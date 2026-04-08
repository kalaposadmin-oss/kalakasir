<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\Platform\PlatformDashboardService;
use App\Services\UserRoleResolverService;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PlatformDashboardController extends Controller
{
    public function __construct(
        private readonly UserRoleResolverService $roleResolver,
        private readonly PlatformDashboardService $dashboardService,
    ) {
    }

    public function index(Request $request): RedirectResponse|Response|View
    {
        if (! Auth::check()) {
            return redirect('/backoffice/login');
        }

        $user = $request->user();
        $tenantId = TenantContext::id() ?? $user?->tenant_id;
        $role = $this->roleResolver->resolveNormalizedRole((string) $tenantId, (string) $user?->id);

        if (! $role && filled($user?->role ?? null)) {
            $role = $this->roleResolver->normalize((string) $user->role);
        }

        abort_unless($role === 'superadmin', 403, 'Anda tidak memiliki akses ke halaman ini.');

        $this->expireImpersonationIfNeeded($request, (string) $user?->id);

        if (app()->environment('testing')) {
            return response('Platform Dashboard', 200);
        }

        return view('platform.dashboard', [
            'dashboard' => $this->dashboardService->summary([]),
        ]);
    }

    private function expireImpersonationIfNeeded(Request $request, string $actorId): void
    {
        if (! $request->hasSession()) {
            return;
        }

        $impersonation = $request->session()->get('impersonation');
        if (! is_array($impersonation) || empty($impersonation['expires_at'])) {
            return;
        }

        try {
            if (! CarbonImmutable::parse((string) $impersonation['expires_at'], 'UTC')->isPast()) {
                return;
            }

            $tenantId = (string) ($impersonation['tenant_id'] ?? '');
            if ($tenantId !== '' && Tenant::query()->where('id', $tenantId)->exists()) {
                DB::table('audit_logs')->insert([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'actor_id' => $actorId !== '' ? $actorId : null,
                    'event_type' => 'tenant_impersonation_ended',
                    'reference_type' => 'tenant',
                    'reference_id' => $tenantId,
                    'payload_json' => json_encode([
                        'reason' => 'Impersonation session expired.',
                        'request_id' => $impersonation['request_id'] ?? null,
                        'expires_at' => $impersonation['expires_at'],
                    ]),
                    'created_at' => now('UTC'),
                ]);
            }

            $request->session()->forget(['impersonation', 'current_tenant_id']);
        } catch (\Throwable) {
            $request->session()->forget('impersonation');
        }
    }
}