<?php

namespace App\Jobs;

use App\Services\AnomalyService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Database\Query\JoinClause;
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
        // Inventory qty can already be variant-aware at the ledger/balance level,
        // but valuation state remains product/location scoped in the current schema.
        // This check therefore validates ledger-to-balance qty consistency per variant bucket,
        // not full variant-isolated valuation correctness.
        $ledgerTotals = $this->ledgerTotalsByInventoryDimension($tenantId);

        $balanceRows = DB::table('inventory_balances as ib')
            ->leftJoinSub($ledgerTotals, 'lt', function (JoinClause $join): void {
                $this->joinOnInventoryDimension($join, 'ib', 'lt');
            })
            ->where('ib.tenant_id', $tenantId)
            ->select('ib.product_id', 'ib.location_id', 'ib.product_variant_id', 'ib.qty_on_hand')
            ->selectRaw('coalesce(lt.ledger_qty, 0) as ledger_qty')
            ->whereRaw('abs(coalesce(lt.ledger_qty, 0) - ib.qty_on_hand) > 0.01')
            ->limit(25)
            ->get();

        $ledgerOnlyRows = DB::query()
            ->fromSub($ledgerTotals, 'lt')
            ->leftJoin('inventory_balances as ib', function (JoinClause $join): void {
                $this->joinOnInventoryDimension($join, 'lt', 'ib');
            })
            ->whereNull('ib.tenant_id')
            ->select('lt.product_id', 'lt.location_id', 'lt.product_variant_id')
            ->selectRaw('0 as qty_on_hand')
            ->selectRaw('lt.ledger_qty as ledger_qty')
            ->limit(25)
            ->get();

        $rows = $balanceRows
            ->concat($ledgerOnlyRows)
            ->take(25)
            ->values();

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
        $this->raiseVoidLedgerMismatchForStream(
            tenantId: $tenantId,
            anomalyService: $anomalyService,
            sourceReferenceType: 'sale',
            reversalReferenceType: 'void',
            anomalyType: 'fifo_void_ledger_mismatch'
        );

        $this->raiseVoidLedgerMismatchForStream(
            tenantId: $tenantId,
            anomalyService: $anomalyService,
            sourceReferenceType: 'sale_recipe_input',
            reversalReferenceType: 'void_recipe_input',
            anomalyType: 'fifo_void_recipe_input_ledger_mismatch'
        );
    }

    private function raiseVoidLedgerMismatchForStream(
        string $tenantId,
        AnomalyService $anomalyService,
        string $sourceReferenceType,
        string $reversalReferenceType,
        string $anomalyType
    ): void {
        $sourceTotals = DB::table('stock_ledger')
            ->where('tenant_id', $tenantId)
            ->where('reference_type', $sourceReferenceType)
            ->select('reference_id')
            ->selectRaw('sum(qty_out) as source_qty')
            ->groupBy('reference_id');

        $reversalTotals = DB::table('stock_ledger')
            ->where('tenant_id', $tenantId)
            ->where('reference_type', $reversalReferenceType)
            ->select('reference_id')
            ->selectRaw('sum(qty_in) as reversal_qty')
            ->groupBy('reference_id');

        $rows = DB::table('sales as s')
            ->joinSub($sourceTotals, 'src', function (JoinClause $join): void {
                $join->on('src.reference_id', '=', 's.id');
            })
            ->leftJoinSub($reversalTotals, 'rev', function (JoinClause $join): void {
                $join->on('rev.reference_id', '=', 's.id');
            })
            ->where('s.tenant_id', $tenantId)
            ->where('s.status', 'voided')
            ->select('s.id as reference_id')
            ->selectRaw('src.source_qty as source_qty')
            ->selectRaw('coalesce(rev.reversal_qty, 0) as reversal_qty')
            ->whereRaw('abs(src.source_qty - coalesce(rev.reversal_qty, 0)) > 0.01')
            ->limit(25)
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        $anomalyService->raise(
            tenantId: $tenantId,
            severity: 'high',
            type: $anomalyType,
            payload: [
                'source_reference_type' => $sourceReferenceType,
                'reversal_reference_type' => $reversalReferenceType,
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

    private function ledgerTotalsByInventoryDimension(string $tenantId)
    {
        return DB::table('stock_ledger')
            ->where('tenant_id', $tenantId)
            ->select('tenant_id', 'location_id', 'product_id', 'product_variant_id')
            ->selectRaw('sum(qty_in - qty_out) as ledger_qty')
            ->groupBy('tenant_id', 'location_id', 'product_id', 'product_variant_id');
    }

    private function joinOnInventoryDimension(JoinClause $join, string $leftAlias, string $rightAlias): void
    {
        // Use COALESCE so NULL variant buckets stay distinct from non-NULL buckets
        // while remaining portable across SQLite tests and the main database.
        $join->on($leftAlias.'.tenant_id', '=', $rightAlias.'.tenant_id')
            ->on($leftAlias.'.product_id', '=', $rightAlias.'.product_id')
            ->on($leftAlias.'.location_id', '=', $rightAlias.'.location_id')
            ->whereRaw('coalesce('.$leftAlias.'.product_variant_id, \'\') = coalesce('.$rightAlias.'.product_variant_id, \'\')');
    }
}
