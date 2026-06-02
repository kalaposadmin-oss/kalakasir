<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PeriodClosingService;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Carbon\CarbonImmutable;
use RuntimeException;

class LockPeriodController extends Controller
{
    public function index()
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json([
                'message' => 'Tenant tidak ditemukan.',
            ], 400);
        }

        $periods = DB::table('lock_periods')
            ->where('tenant_id', $tenantId)
            ->orderByDesc('start_date')
            ->get(array_values(array_filter([
                'id',
                Schema::hasColumn('lock_periods', 'name') ? 'name' : null,
                'start_date',
                'end_date',
                'is_locked',
                'locked_by',
                'locked_at',
                'reason',
                Schema::hasColumn('lock_periods', 'closed_by') ? 'closed_by' : null,
                Schema::hasColumn('lock_periods', 'closed_at') ? 'closed_at' : null,
                Schema::hasColumn('lock_periods', 'reopened_by') ? 'reopened_by' : null,
                Schema::hasColumn('lock_periods', 'reopened_at') ? 'reopened_at' : null,
                Schema::hasColumn('lock_periods', 'close_summary_json') ? 'close_summary_json' : null,
            ])))
            ->map(function ($period) {
                if (property_exists($period, 'close_summary_json') && $period->close_summary_json) {
                    $period->close_summary = json_decode((string) $period->close_summary_json, true);
                    unset($period->close_summary_json);
                }

                return $period;
            });

        return response()->json([
            'periods' => $periods,
        ]);
    }

    public function preview(Request $request, PeriodClosingService $service)
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json([
                'message' => 'Tenant tidak ditemukan.',
            ], 400);
        }

        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        return response()->json($service->preview(
            $tenantId,
            $validated['start_date'],
            $validated['end_date']
        ));
    }

    public function assistant(Request $request, PeriodClosingService $service)
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json([
                'message' => 'Tenant tidak ditemukan.',
            ], 400);
        }

        return response()->json($service->assistantStatus($tenantId));
    }

    public function store(Request $request)
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json([
                'message' => 'Tenant tidak ditemukan.',
            ], 400);
        }

        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $periodId = (string) Str::uuid();
        DB::table('lock_periods')->insert([
            'id' => $periodId,
            'tenant_id' => $tenantId,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'is_locked' => true,
            'locked_by' => $request->user()?->id,
            'locked_at' => CarbonImmutable::now('UTC'),
            'reason' => $validated['reason'] ?? null,
        ]);

        return response()->json([
            'period_id' => $periodId,
        ], 201);
    }

    public function close(Request $request, PeriodClosingService $service)
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json([
                'message' => 'Tenant tidak ditemukan.',
            ], 400);
        }

        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'name' => ['nullable', 'string', 'max:100'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $result = $service->close(
                $tenantId,
                $validated['start_date'],
                $validated['end_date'],
                $request->user()?->id,
                $validated['reason'] ?? null,
                $validated['name'] ?? null
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'code' => 'PERIOD_CLOSE_FAILED',
                'message' => $exception->getMessage(),
                'details' => [],
            ], 422);
        }

        return response()->json($result, 201);
    }

    public function reopen(Request $request, PeriodClosingService $service, string $period)
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json([
                'message' => 'Tenant tidak ditemukan.',
            ], 400);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        try {
            $result = $service->reopen($tenantId, $period, $request->user()?->id, $validated['reason']);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], $exception->getMessage() === 'Periode lock tidak ditemukan.' ? 404 : 422);
        }

        return response()->json($result);
    }

    public function toggle(Request $request, string $period)
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json([
                'message' => 'Tenant tidak ditemukan.',
            ], 400);
        }

        $validated = $request->validate([
            'is_locked' => ['required', 'boolean'],
        ]);

        $periodRow = DB::table('lock_periods')
            ->where('tenant_id', $tenantId)
            ->where('id', $period)
            ->first(['id']);

        if (! $periodRow) {
            return response()->json([
                'message' => 'Periode lock tidak ditemukan.',
            ], 404);
        }

        $data = [
            'is_locked' => $validated['is_locked'],
        ];

        if ($validated['is_locked']) {
            $data['locked_by'] = $request->user()?->id;
            $data['locked_at'] = CarbonImmutable::now('UTC');
        }

        DB::table('lock_periods')
            ->where('tenant_id', $tenantId)
            ->where('id', $periodRow->id)
            ->update($data);

        return response()->json([
            'status' => 'ok',
        ]);
    }
}
