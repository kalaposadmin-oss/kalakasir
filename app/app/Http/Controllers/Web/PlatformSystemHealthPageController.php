<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\SystemHealthEvent;
use App\Models\Tenant;
use App\Services\Platform\PlatformSystemHealthService;
use App\Services\UserRoleResolverService;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class PlatformSystemHealthPageController extends Controller
{
    public function __construct(
        private readonly UserRoleResolverService $roleResolver,
        private readonly PlatformSystemHealthService $service,
    ) {
    }

    public function dashboard(Request $request): RedirectResponse|Response|View
    {
        if ($redirect = $this->authorizeSuperadmin($request)) {
            return $redirect;
        }

        return view('platform.system-health.dashboard', [
            'initialSummary' => $this->service->summary(['status' => 'open']),
            'tenantImpact' => $this->tenantImpactRows(),
            'latestActivity' => $this->latestActivity(),
            'runStatus' => $this->runStatus(),
            'filters' => [
                'status' => 'open',
            ],
        ]);
    }

    public function incidents(Request $request): RedirectResponse|Response|View
    {
        if ($redirect = $this->authorizeSuperadmin($request)) {
            return $redirect;
        }

        $filters = [
            'tenant_id' => (string) $request->query('tenant_id', ''),
            'severity' => (string) $request->query('severity', ''),
            'status' => (string) $request->query('status', 'open'),
            'event_name' => (string) $request->query('event_name', ''),
            'search' => (string) $request->query('search', ''),
            'from' => (string) $request->query('from', ''),
            'to' => (string) $request->query('to', ''),
            'sort_by' => (string) $request->query('sort_by', 'latest'),
            'sort_dir' => (string) $request->query('sort_dir', 'desc'),
        ];

        return view('platform.system-health.incidents', [
            'filters' => $filters,
            'tenants' => Tenant::query()->orderBy('name')->get(['id', 'name']),
            'catalog' => array_keys(config('system_health.events', [])),
        ]);
    }

    public function show(Request $request, string $eventId): RedirectResponse|Response|View
    {
        if ($redirect = $this->authorizeSuperadmin($request)) {
            return $redirect;
        }

        $event = $this->service->event($eventId);
        abort_if(! $event, 404);

        return view('platform.system-health.detail', [
            'event' => $event,
            'relatedEvents' => $this->relatedEvents($event),
        ]);
    }

    public function tenants(Request $request): RedirectResponse|Response|View
    {
        if ($redirect = $this->authorizeSuperadmin($request)) {
            return $redirect;
        }

        return view('platform.system-health.tenants', [
            'tenantImpact' => $this->tenantImpactRows(),
        ]);
    }

    private function authorizeSuperadmin(Request $request): ?RedirectResponse
    {
        if (! Auth::check()) {
            return redirect()->guest('/backoffice');
        }

        $user = $request->user();
        $tenantId = TenantContext::id() ?? $user?->tenant_id;
        $role = $this->roleResolver->resolveNormalizedRole((string) $tenantId, (string) $user?->id);

        if (! $role && filled($user?->role ?? null)) {
            $role = $this->roleResolver->normalize((string) $user->role);
        }

        abort_unless($role === 'superadmin', 403, 'Anda tidak memiliki akses ke halaman ini.');

        return null;
    }

    private function tenantImpactRows(): array
    {
        $rows = Tenant::query()
            ->leftJoin('system_health_events as open_events', function ($join): void {
                $join->on('open_events.tenant_id', '=', 'tenants.id')
                    ->where('open_events.status', '=', 'open');
            })
            ->leftJoin('system_health_events as all_events', 'all_events.tenant_id', '=', 'tenants.id')
            ->selectRaw('tenants.id as tenant_id')
            ->selectRaw('tenants.name as tenant_name')
            ->selectRaw('COUNT(DISTINCT open_events.id) as unresolved_count')
            ->selectRaw("COUNT(DISTINCT CASE WHEN open_events.severity = 'critical' THEN open_events.id END) as critical_count")
            ->selectRaw("COUNT(DISTINCT CASE WHEN open_events.severity = 'high' THEN open_events.id END) as high_count")
            ->selectRaw('COUNT(DISTINCT all_events.id) as total_event_count')
            ->selectRaw('MAX(all_events.detected_at) as last_event_at')
            ->groupBy('tenants.id', 'tenants.name')
            ->orderByDesc('critical_count')
            ->orderByDesc('high_count')
            ->orderByDesc('unresolved_count')
            ->orderBy('tenants.name')
            ->get();

        return $rows->map(function ($row) {
            $criticalCount = (int) $row->critical_count;
            $highCount = (int) $row->high_count;
            $unresolvedCount = (int) $row->unresolved_count;
            $totalEventCount = (int) $row->total_event_count;

            return [
                'tenant_id' => $row->tenant_id,
                'tenant_name' => (string) $row->tenant_name,
                'unresolved_count' => $unresolvedCount,
                'critical_count' => $criticalCount,
                'high_count' => $highCount,
                'total_event_count' => $totalEventCount,
                'last_event_at' => $row->last_event_at ? CarbonImmutable::parse($row->last_event_at, 'UTC')->toIso8601String() : null,
                'health_status' => $this->healthStatus($criticalCount, $highCount, $unresolvedCount, $totalEventCount),
            ];
        })->all();
    }

    private function latestActivity(): array
    {
        return DB::table('system_health_event_occurrences as occurrences')
            ->join('system_health_events as events', 'events.id', '=', 'occurrences.system_health_event_id')
            ->leftJoin('tenants', 'tenants.id', '=', 'events.tenant_id')
            ->orderByDesc('occurrences.detected_at')
            ->limit(12)
            ->get([
                'events.id as event_id',
                'events.event_name',
                'events.severity',
                'events.status',
                'tenants.name as tenant_name',
                'events.tenant_id',
                'occurrences.summary',
                'occurrences.detected_at',
            ])
            ->map(fn ($row) => [
                'event_id' => (string) $row->event_id,
                'event_name' => (string) $row->event_name,
                'severity' => (string) $row->severity,
                'status' => (string) $row->status,
                'tenant_name' => $row->tenant_name ?: ($row->tenant_id ?: 'Global'),
                'summary' => (string) $row->summary,
                'detected_at' => CarbonImmutable::parse($row->detected_at, 'UTC')->toIso8601String(),
            ])->all();
    }

    private function runStatus(): array
    {
        return [
            'last_validation_success' => SystemHealthEvent::query()->where('event_name', 'sales_cost_validate_failed')->max('resolved_at'),
            'last_rebuild_success' => SystemHealthEvent::query()->where('event_name', 'sales_cost_rebuild_failed')->max('resolved_at'),
            'last_scan_success' => SystemHealthEvent::query()
                ->whereIn('event_name', ['sales_cost_missing_aggregate', 'sales_cost_header_mismatch', 'cross_report_mismatch', 'daily_summary_refresh_failed'])
                ->max('resolved_at'),
        ];
    }

    private function relatedEvents(array $event): array
    {
        return SystemHealthEvent::query()
            ->with('tenant:id,name')
            ->where('id', '!=', $event['event_id'])
            ->where(function ($query) use ($event): void {
                $query->where('tenant_id', $event['tenant_id'])
                    ->orWhere(function ($inner) use ($event): void {
                        $inner->where('entity_type', $event['entity_type'])
                            ->where('entity_id', $event['entity_id']);
                    });
            })
            ->orderByDesc('detected_at')
            ->limit(6)
            ->get()
            ->map(fn ($row) => [
                'event_id' => $row->id,
                'event_name' => $row->event_name,
                'severity' => $row->severity,
                'status' => $row->status,
                'tenant_name' => $row->tenant?->name,
                'summary' => $row->summary,
                'detected_at' => optional($row->detected_at)->toIso8601String(),
            ])
            ->all();
    }

    private function healthStatus(int $criticalCount, int $highCount, int $unresolvedCount, int $totalEventCount): string
    {
        if ($totalEventCount === 0) {
            return 'gray';
        }

        if ($criticalCount > 0) {
            return 'red';
        }

        if ($highCount > 0) {
            return 'yellow';
        }

        return $unresolvedCount === 0 ? 'green' : 'gray';
    }
}
