<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\CarbonImmutable;

class LockPeriodController extends Controller
{
    public function index()
    {
        $tenantId = TenantContext::id();

        $periods = DB::table('lock_periods')
            ->where('tenant_id', $tenantId)
            ->orderByDesc('start_date')
            ->get(['id', 'start_date', 'end_date', 'is_locked']);

        return response()->json([
            'periods' => $periods,
        ]);
    }

    public function store(Request $request)
    {
        $tenantId = TenantContext::id();

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

    public function toggle(Request $request, string $period)
    {
        $tenantId = TenantContext::id();

        $validated = $request->validate([
            'is_locked' => ['required', 'boolean'],
        ]);

        $data = [
            'is_locked' => $validated['is_locked'],
        ];

        if ($validated['is_locked']) {
            $data['locked_by'] = $request->user()?->id;
            $data['locked_at'] = CarbonImmutable::now('UTC');
        }

        DB::table('lock_periods')
            ->where('tenant_id', $tenantId)
            ->where('id', $period)
            ->update($data);

        return response()->json([
            'status' => 'ok',
        ]);
    }
}
