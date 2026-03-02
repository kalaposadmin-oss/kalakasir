<?php

namespace App\Jobs;

use App\Services\AnomalyService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class DetectInventoryInvariantJob implements ShouldQueue
{
    use Queueable;

    public function handle(AnomalyService $anomalyService): void
    {
        $tenantIds = DB::table('tenants')->pluck('id');

        foreach ($tenantIds as $tenantId) {
            $this->checkLedgerBalanceDrift((string) $tenantId, $anomalyService);
            $this->checkLayerNegative((string) $tenantId, $anomalyService);
            $this->checkAllocationQtyMismatch((string) $tenantId, $anomalyService);
            $this->checkAllocationCostMismatch((string) $tenantId, $anomalyService);
            $this->checkJournalCogsMismatch((string) $tenantId, $anomalyService);
            $this->checkVoidLedgerMismatch((string) $tenantId, $anomalyService);
            $this->checkVoidAllocationReversal((string) $tenantId, $anomalyService);
        }
    }

    private function checkLedgerBalanceDrift(string $tenantId, AnomalyService $anomalyService): void
    {
        $rows = DB::table('stock_ledger as sl')
            ->join('inventory_balances as ib', function ($join) {
                $join->on('sl.tenant_id', '=', 'ib.tenant_id')
                    ->on('sl.product_id', '=', 'ib.product_id')
                    ->on('sl.location_id', '=', 'ib.location_id');
            })
            ->where('sl.tenant_id', $tenantId)
            ->select('sl.product_id', 'sl.location_id', 'ib.qty_on_hand')
            ->selectRaw('sum(sl.qty_in - sl.qty_out) as ledger_qty')
            ->groupBy('sl.product_id', 'sl.location_id', 'ib.qty_on_hand')
            ->havingRaw('abs(sum(sl.qty_in - sl.qty_out) - ib.qty_on_hand) > 0.01')
            ->limit(25)
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        $anomalyService->raise(
            tenantId: $tenantId,
            severity: 'high',
            type: 'fifo_ledger_balance_drift',
            payload: [
                'count' => (int) $rows->count(),
                'sample' => $rows->take(5)->values(),
                'checked_at' => CarbonImmutable::now('UTC')->toIso8601String(),
            ]
        );
    }

    private function checkLayerNegative(string $tenantId, AnomalyService $anomalyService): void
    {
        $count = DB::table('inventory_layers')
            ->where('tenant_id', $tenantId)
            ->where('qty_remaining', '<', 0)
            ->count();

        if ($count === 0) {
            return;
        }

        $anomalyService->raise(
            tenantId: $tenantId,
            severity: 'high',
            type: 'fifo_layer_negative',
            payload: [
                'count' => (int) $count,
                'checked_at' => CarbonImmutable::now('UTC')->toIso8601String(),
            ]
        );
    }

    private function checkAllocationQtyMismatch(string $tenantId, AnomalyService $anomalyService): void
    {
        $rows = DB::table('inventory_issue_allocations as ia')
            ->join('sale_items as si', function ($join) {
                $join->on('ia.reference_item_id', '=', 'si.id')
                    ->on('ia.tenant_id', '=', 'si.tenant_id');
            })
            ->where('ia.tenant_id', $tenantId)
            ->where('ia.reference_type', 'sale')
            ->select('ia.reference_item_id', 'si.qty')
            ->selectRaw('sum(ia.qty) as alloc_qty')
            ->groupBy('ia.reference_item_id', 'si.qty')
            ->havingRaw('abs(sum(ia.qty) - si.qty) > 0.01')
            ->limit(25)
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        $anomalyService->raise(
            tenantId: $tenantId,
            severity: 'med',
            type: 'fifo_allocation_qty_mismatch',
            payload: [
                'count' => (int) $rows->count(),
                'sample' => $rows->take(5)->values(),
                'checked_at' => CarbonImmutable::now('UTC')->toIso8601String(),
            ]
        );
    }

    private function checkAllocationCostMismatch(string $tenantId, AnomalyService $anomalyService): void
    {
        $rows = DB::table('inventory_issue_allocations as ia')
            ->join('sale_items as si', function ($join) {
                $join->on('ia.reference_item_id', '=', 'si.id')
                    ->on('ia.tenant_id', '=', 'si.tenant_id');
            })
            ->where('ia.tenant_id', $tenantId)
            ->where('ia.reference_type', 'sale')
            ->select('ia.reference_item_id', 'si.qty', 'si.hpp_snapshot')
            ->selectRaw('sum(ia.total_cost) as alloc_cost')
            ->groupBy('ia.reference_item_id', 'si.qty', 'si.hpp_snapshot')
            ->havingRaw('abs(sum(ia.total_cost) - (si.qty * si.hpp_snapshot)) > 0.01')
            ->limit(25)
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        $anomalyService->raise(
            tenantId: $tenantId,
            severity: 'med',
            type: 'fifo_allocation_cost_mismatch',
            payload: [
                'count' => (int) $rows->count(),
                'sample' => $rows->take(5)->values(),
                'checked_at' => CarbonImmutable::now('UTC')->toIso8601String(),
            ]
        );
    }

    private function checkJournalCogsMismatch(string $tenantId, AnomalyService $anomalyService): void
    {
        $mapping = DB::table('account_mappings')
            ->where('tenant_id', $tenantId)
            ->first(['cogs_account_id']);

        if (! $mapping || ! $mapping->cogs_account_id) {
            return;
        }

        $allocBySale = DB::table('inventory_issue_allocations as ia')
            ->where('ia.tenant_id', $tenantId)
            ->where('ia.reference_type', 'sale')
            ->whereNull('ia.reversed_at')
            ->select('ia.reference_id')
            ->selectRaw('sum(ia.total_cost) as alloc_cost')
            ->groupBy('ia.reference_id');

        $journalBySale = DB::table('journal_entries as je')
            ->join('journal_items as ji', function ($join) use ($mapping) {
                $join->on('ji.journal_entry_id', '=', 'je.id')
                    ->on('ji.tenant_id', '=', 'je.tenant_id')
                    ->where('ji.account_id', '=', $mapping->cogs_account_id)
                    ->where('ji.is_reversal', '=', false);
            })
            ->where('je.tenant_id', $tenantId)
            ->where('je.reference_type', 'sale')
            ->select('je.reference_id')
            ->selectRaw('sum(ji.debit) as journal_cogs')
            ->groupBy('je.reference_id');

        $rows = DB::table('sales as s')
            ->joinSub($allocBySale, 'a', function ($join) {
                $join->on('a.reference_id', '=', 's.id');
            })
            ->joinSub($journalBySale, 'j', function ($join) {
                $join->on('j.reference_id', '=', 's.id');
            })
            ->where('s.tenant_id', $tenantId)
            ->where('s.status', 'completed')
            ->select('s.id as reference_id', 'a.alloc_cost', 'j.journal_cogs')
            ->whereRaw('abs(a.alloc_cost - j.journal_cogs) > 0.01')
            ->limit(25)
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        $anomalyService->raise(
            tenantId: $tenantId,
            severity: 'med',
            type: 'fifo_journal_cogs_mismatch',
            payload: [
                'count' => (int) $rows->count(),
                'sample' => $rows->take(5)->values(),
                'checked_at' => CarbonImmutable::now('UTC')->toIso8601String(),
            ]
        );
    }

    private function checkVoidLedgerMismatch(string $tenantId, AnomalyService $anomalyService): void
    {
        $rows = DB::table('sales as s')
            ->join('stock_ledger as sl', function ($join) {
                $join->on('sl.reference_id', '=', 's.id')
                    ->on('sl.tenant_id', '=', 's.tenant_id')
                    ->where('sl.reference_type', '=', 'sale');
            })
            ->leftJoin('stock_ledger as v', function ($join) {
                $join->on('v.reference_id', '=', 's.id')
                    ->on('v.tenant_id', '=', 's.tenant_id')
                    ->where('v.reference_type', '=', 'void');
            })
            ->where('s.tenant_id', $tenantId)
            ->where('s.status', 'voided')
            ->select('s.id')
            ->selectRaw('sum(sl.qty_out) as sold_qty')
            ->selectRaw('coalesce(sum(v.qty_in), 0) as void_in_qty')
            ->groupBy('s.id')
            ->havingRaw('abs(sum(sl.qty_out) - coalesce(sum(v.qty_in), 0)) > 0.01')
            ->limit(25)
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        $anomalyService->raise(
            tenantId: $tenantId,
            severity: 'high',
            type: 'fifo_void_ledger_mismatch',
            payload: [
                'count' => (int) $rows->count(),
                'sample' => $rows->take(5)->values(),
                'checked_at' => CarbonImmutable::now('UTC')->toIso8601String(),
            ]
        );
    }

    private function checkVoidAllocationReversal(string $tenantId, AnomalyService $anomalyService): void
    {
        $rows = DB::table('inventory_issue_allocations as ia')
            ->join('sales as s', function ($join) {
                $join->on('ia.reference_id', '=', 's.id')
                    ->on('ia.tenant_id', '=', 's.tenant_id');
            })
            ->where('ia.tenant_id', $tenantId)
            ->where('ia.reference_type', 'sale')
            ->where('s.status', 'voided')
            ->whereNull('ia.reversed_at')
            ->select('ia.reference_id')
            ->selectRaw('count(*) as open_allocations')
            ->groupBy('ia.reference_id')
            ->limit(25)
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        $anomalyService->raise(
            tenantId: $tenantId,
            severity: 'high',
            type: 'fifo_void_allocation_not_reversed',
            payload: [
                'count' => (int) $rows->count(),
                'sample' => $rows->take(5)->values(),
                'checked_at' => CarbonImmutable::now('UTC')->toIso8601String(),
            ]
        );
    }
}
