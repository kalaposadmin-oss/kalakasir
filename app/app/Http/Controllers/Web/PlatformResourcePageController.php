<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Platform\PlatformAnomalyService;
use App\Services\Platform\PlatformAuditLogService;
use App\Services\Platform\PlatformHealthService;
use App\Services\Platform\PlatformInvoiceService;
use App\Services\Platform\PlatformSupportService;
use App\Services\Platform\PlatformTenantService;
use App\Services\Platform\PlatformUserService;
use App\Services\UserRoleResolverService;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PlatformResourcePageController extends Controller
{
    public function __construct(
        private readonly UserRoleResolverService $roleResolver,
        private readonly PlatformTenantService $tenantService,
        private readonly PlatformUserService $userService,
        private readonly PlatformInvoiceService $invoiceService,
        private readonly PlatformAuditLogService $auditLogService,
        private readonly PlatformAnomalyService $anomalyService,
        private readonly PlatformSupportService $supportService,
        private readonly PlatformHealthService $healthService,
    ) {
    }

    public function tenants(Request $request): RedirectResponse|View
    {
        if ($redirect = $this->authorizeSuperadmin($request)) {
            return $redirect;
        }

        $filters = $this->filters($request, [
            'q', 'status', 'plan', 'health', 'created_from', 'created_to', 'sort_by', 'sort_dir',
        ]);
        $page = $this->page($request);
        $perPage = $this->perPage($request);
        $result = $this->tenantService->list($filters, $page, $perPage);

        return view('platform.tenants', $this->pagedPayload($result, $page, $perPage) + [
            'filters' => $filters,
        ]);
    }

    public function tenantDetail(Request $request, string $tenantId): RedirectResponse|View
    {
        if ($redirect = $this->authorizeSuperadmin($request)) {
            return $redirect;
        }

        $tenantDetail = $this->tenantService->detail($tenantId, []);
        abort_if(empty($tenantDetail['tenant']['name']), 404, 'Tenant tidak ditemukan.');

        return view('platform.tenant-detail', [
            'tenantDetail' => $tenantDetail,
            'targetTenantId' => $tenantId,
        ]);
    }

    public function users(Request $request): RedirectResponse|View
    {
        if ($redirect = $this->authorizeSuperadmin($request)) {
            return $redirect;
        }

        $filters = $this->filters($request, [
            'q', 'tenant_id', 'role', 'status', 'last_login_from', 'last_login_to', 'sort_by', 'sort_dir',
        ]);
        $page = $this->page($request);
        $perPage = $this->perPage($request);
        $result = $this->userService->list($filters, $page, $perPage);

        return view('platform.users', $this->pagedPayload($result, $page, $perPage) + [
            'filters' => $filters,
        ]);
    }

    public function userDetail(Request $request, string $userId): RedirectResponse|View
    {
        if ($redirect = $this->authorizeSuperadmin($request)) {
            return $redirect;
        }

        $userDetail = $this->userService->detail($userId, []);
        abort_if(empty($userDetail['user']['name']), 404, 'User tidak ditemukan.');

        $auditLogs = $this->userService->auditLogs($userId, [], 1, 20);

        return view('platform.user-detail', [
            'userDetail' => $userDetail,
            'userRole' => $userDetail['user']['role'] ?? null,
            'userStatus' => $userDetail['user']['status'] ?? 'active',
            'lastLoginAt' => $userDetail['user']['last_login_at'] ?? null,
            'auditLogs' => $auditLogs['audit_logs'] ?? [],
        ]);
    }

    public function invoices(Request $request): RedirectResponse|View
    {
        if ($redirect = $this->authorizeSuperadmin($request)) {
            return $redirect;
        }

        $filters = $this->filters($request, [
            'q', 'tenant_id', 'status', 'due_from', 'due_to', 'sort_by', 'sort_dir',
        ]);
        $page = $this->page($request);
        $perPage = $this->perPage($request);
        $result = $this->invoiceService->list($filters, $page, $perPage);

        return view('platform.invoices', $this->pagedPayload($result, $page, $perPage) + [
            'filters' => $filters,
        ]);
    }

    public function auditLogs(Request $request): RedirectResponse|View
    {
        if ($redirect = $this->authorizeSuperadmin($request)) {
            return $redirect;
        }

        $filters = $this->filters($request, [
            'q', 'tenant_id', 'actor_id', 'event_type', 'from', 'to', 'sort_by', 'sort_dir',
        ]);
        $page = $this->page($request);
        $perPage = $this->perPage($request);
        $result = $this->auditLogService->list($filters, $page, $perPage);

        return view('platform.audit-logs', $this->pagedPayload($result, $page, $perPage) + [
            'filters' => $filters,
        ]);
    }

    public function anomalies(Request $request): RedirectResponse|View
    {
        if ($redirect = $this->authorizeSuperadmin($request)) {
            return $redirect;
        }

        $filters = $this->filters($request, [
            'tenant_id', 'severity', 'resolved', 'from', 'to', 'sort_by', 'sort_dir',
        ]);
        $page = $this->page($request);
        $perPage = $this->perPage($request);
        $result = $this->anomalyService->list($filters, $page, $perPage);

        return view('platform.anomalies', $this->pagedPayload($result, $page, $perPage) + [
            'filters' => $filters,
        ]);
    }

    public function supportSearch(Request $request): RedirectResponse|View
    {
        if ($redirect = $this->authorizeSuperadmin($request)) {
            return $redirect;
        }

        $query = trim((string) $request->query('q', ''));
        $matches = $query !== ''
            ? ($this->supportService->search(['q' => $query])['matches'] ?? [])
            : [];

        $actionFilters = $this->filters($request, [
            'tenant_id', 'actor_id', 'action_type', 'from', 'to', 'sort_by', 'sort_dir',
        ]);
        $actions = $this->supportService->actions($actionFilters, $this->page($request), $this->perPage($request));

        return view('platform.support-search', [
            'matches' => $matches,
            'actions' => $actions['actions'] ?? [],
            'total' => $actions['total'] ?? 0,
            'filters' => ['q' => $query] + $actionFilters,
        ]);
    }

    public function health(Request $request): RedirectResponse|View
    {
        if ($redirect = $this->authorizeSuperadmin($request)) {
            return $redirect;
        }

        return view('platform.health', [
            'health' => $this->healthService->summary(),
        ]);
    }

    private function authorizeSuperadmin(Request $request): ?RedirectResponse
    {
        if (! Auth::check()) {
            return redirect()->guest('/backoffice');
        }

        $user = $request->user();
        $tenantId = (string) ($user?->tenant_id ?: TenantContext::id() ?: '');
        $role = $this->roleResolver->resolveNormalizedRole($tenantId, (string) $user?->id);

        if (! $role && filled($user?->role ?? null)) {
            $role = $this->roleResolver->normalize((string) $user->role);
        }

        abort_unless($role === 'superadmin', 403, 'Anda tidak memiliki akses ke halaman ini.');

        return null;
    }

    /**
     * @param array<int, string> $allowed
     * @return array<string, mixed>
     */
    private function filters(Request $request, array $allowed): array
    {
        return collect($request->query())
            ->only($allowed)
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();
    }

    private function page(Request $request): int
    {
        return max(1, (int) $request->query('page', 1));
    }

    private function perPage(Request $request): int
    {
        return max(1, min(100, (int) $request->query('per_page', 20)));
    }

    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    private function pagedPayload(array $result, int $page, int $perPage): array
    {
        $total = (int) ($result['total'] ?? 0);

        return $result + [
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'lastPage' => max(1, (int) ceil($total / $perPage)),
        ];
    }
}
