<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ValuationAuditLogController extends Controller
{
    public function __invoke(Request $request)
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json([
                'code' => 'TENANT_NOT_RESOLVED',
                'message' => 'Tenant tidak ditemukan.',
                'details' => [],
            ], 400);
        }

        $validated = $request->validate([
            'action' => ['nullable', 'string', 'max:120'],
            'actor_id' => ['nullable', 'uuid'],
            'request_id' => ['nullable', 'uuid'],
            'preset' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $preset = $validated['preset'] ?? 'last7';
        $startDate = $validated['start_date'] ?? $validated['from'] ?? null;
        $endDate = $validated['end_date'] ?? $validated['to'] ?? null;
        [$startUtc, $endExclusiveUtc, $rangeMeta] = $this->resolveRange($preset, $startDate, $endDate);

        $limit = (int) ($validated['limit'] ?? 50);

        $query = DB::table('valuation_audit_logs as v')
            ->leftJoin('users as u', function ($join) {
                $join->on('v.actor_id', '=', 'u.id')
                    ->on('v.tenant_id', '=', 'u.tenant_id');
            })
            ->where('v.tenant_id', $tenantId)
            ->when(isset($validated['action']), fn ($q) => $q->where('v.action', $validated['action']))
            ->when(isset($validated['actor_id']), fn ($q) => $q->where('v.actor_id', $validated['actor_id']))
            ->when(isset($validated['request_id']), fn ($q) => $q->where('v.request_id', $validated['request_id']))
            ->where('v.created_at', '>=', $startUtc)
            ->where('v.created_at', '<', $endExclusiveUtc);

        $rows = $query
            ->orderByDesc('v.created_at')
            ->limit($limit)
            ->get([
                'v.id',
                'v.action',
                'v.actor_id',
                'u.name as actor_name',
                'v.request_id',
                'v.meta_json',
                'v.ip_address',
                'v.user_agent',
                'v.created_at',
            ]);

        return response()->json([
            'logs' => $rows,
            'count' => $rows->count(),
            'range' => $rangeMeta,
        ]);
    }

    private function resolveRange(string $preset, ?string $startDate, ?string $endDate): array
    {
        $timezone = 'Asia/Jakarta';
        $today = CarbonImmutable::now($timezone)->startOfDay();

        [$startLocal, $endLocal, $label] = match ($preset) {
            'today' => [$today, $today, 'Hari Ini'],
            'yesterday' => [$today->subDay(), $today->subDay(), 'Kemarin'],
            'last30', '30days' => [$today->subDays(29), $today, '30 Hari Terakhir'],
            'thisMonth' => [$today->startOfMonth(), $today->endOfMonth()->startOfDay(), 'Bulan Ini'],
            'lastMonth' => [
                $today->subMonthNoOverflow()->startOfMonth(),
                $today->subMonthNoOverflow()->endOfMonth()->startOfDay(),
                'Bulan Lalu',
            ],
            'custom' => $this->resolveCustomRange($startDate, $endDate, $today),
            'last7', '7days' => [$today->subDays(6), $today, '7 Hari Terakhir'],
            default => [$today->subDays(6), $today, '7 Hari Terakhir'],
        };

        $days = $startLocal->diffInDays($endLocal) + 1;
        $limited = false;
        if ($days > 90) {
            $endLocal = $startLocal->addDays(89);
            $limited = true;
        }

        return [
            $startLocal->setTimezone('UTC'),
            $endLocal->addDay()->startOfDay()->setTimezone('UTC'),
            [
                'preset' => $preset,
                'start_date' => $startLocal->toDateString(),
                'end_date' => $endLocal->toDateString(),
                'label' => $label,
                'timezone' => 'Asia/Jakarta',
                'limited' => $limited,
                'limit_days' => 90,
            ],
        ];
    }

    private function resolveCustomRange(?string $startDate, ?string $endDate, CarbonImmutable $fallback): array
    {
        $timezone = 'Asia/Jakarta';
        $startLocal = CarbonImmutable::parse($startDate ?? $fallback->toDateString(), $timezone)->startOfDay();
        $endLocal = CarbonImmutable::parse($endDate ?? $startDate ?? $fallback->toDateString(), $timezone)->startOfDay();

        if ($endLocal < $startLocal) {
            [$startLocal, $endLocal] = [$endLocal, $startLocal];
        }

        return [$startLocal, $endLocal, 'Periode Kustom'];
    }
}
