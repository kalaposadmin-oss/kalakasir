<?php

namespace App\Services;

use App\Models\AccountMapping;
use App\Models\InventoryBalance;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\OpeningPostingLog;
use App\Models\OpeningSession;
use App\Models\StockLedger;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class OpeningBalancePostingService
{
    public function __construct(
        private readonly OpeningBalanceValidationService $validationService,
        private readonly OpeningBalanceLockService $lockService,
        private readonly LockPeriodService $lockPeriodService,
        private readonly InventoryValuationService $inventoryValuationService,
        private readonly AuditLogger $auditLogger,
        private readonly OpeningBalanceDraftService $draftService,
    ) {
    }

    public function post(string $sessionId, ?string $actorId, array $context = [], bool $wrapTransaction = true): OpeningSession
    {
        $tenantId = (string) ($context['tenant_id'] ?? TenantContext::id());
        if (! $tenantId) {
            throw new RuntimeException('Tenant tidak ditemukan.');
        }

        $runner = function () use ($tenantId, $sessionId, $actorId, $context) {
            $session = OpeningSession::query()
                ->where('tenant_id', $tenantId)
                ->where('id', $sessionId)
                ->lockForUpdate()
                ->firstOrFail();

            $session->load(['stockLines', 'fundLines']);
            $this->lockService->preventModification($session);

            $openingDate = CarbonImmutable::parse((string) $session->opening_date, 'UTC');
            $this->lockPeriodService->ensureNotLocked($openingDate, $tenantId);

            $totals = $this->validationService->validateForPost($session);
            $now = CarbonImmutable::now('UTC');

            $mapping = AccountMapping::query()
                ->where('tenant_id', $tenantId)
                ->firstOrFail();

            if (! $mapping->inventory_account_id) {
                throw new RuntimeException('Mapping akun inventory belum tersedia.');
            }

            $stockLedgerCount = 0;
            foreach ($session->stockLines as $line) {
                $variantId = $line->product_variant_id ? (string) $line->product_variant_id : null;
                $qty = (float) $line->qty;
                $unitCost = (float) $line->unit_cost;
                $lineValue = (float) $line->line_value;

                InventoryBalance::query()->firstOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'product_id' => (string) $line->product_id,
                        'product_variant_id' => $variantId,
                        'location_id' => (string) $line->location_id,
                    ],
                    [
                        'qty_on_hand' => 0,
                    ]
                );

                DB::table('inventory_balances')
                    ->where('tenant_id', $tenantId)
                    ->where('product_id', $line->product_id)
                    ->where('location_id', $line->location_id)
                    ->when($variantId, function ($query) use ($variantId) {
                        $query->where('product_variant_id', $variantId);
                    }, function ($query) {
                        $query->whereNull('product_variant_id');
                    })
                    ->update([
                        'qty_on_hand' => DB::raw('qty_on_hand + '.(float) $qty),
                    ]);

                $balanceAfter = (float) DB::table('inventory_balances')
                    ->where('tenant_id', $tenantId)
                    ->where('product_id', $line->product_id)
                    ->where('location_id', $line->location_id)
                    ->when($variantId, function ($query) use ($variantId) {
                        $query->where('product_variant_id', $variantId);
                    }, function ($query) {
                        $query->whereNull('product_variant_id');
                    })
                    ->value('qty_on_hand');

                StockLedger::query()->create([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'product_id' => $line->product_id,
                    'product_variant_id' => $variantId,
                    'location_id' => $line->location_id,
                    'reference_type' => 'opening_balance',
                    'reference_id' => $session->id,
                    'reference_item_id' => $line->id,
                    'qty_in' => $qty,
                    'qty_out' => 0,
                    'balance_after' => $balanceAfter,
                    'unit_cost' => $unitCost,
                    'total_cost' => $lineValue,
                    'created_at' => $now,
                    'created_by' => $actorId,
                ]);

                $this->inventoryValuationService->receive(
                    tenantId: $tenantId,
                    productId: (string) $line->product_id,
                    productVariantId: $variantId,
                    locationId: (string) $line->location_id,
                    qty: $qty,
                    unitCost: $unitCost,
                    sourceType: 'opening_balance',
                    sourceId: (string) $session->id,
                    sourceItemId: (string) $line->id,
                    at: $openingDate,
                );

                $stockLedgerCount++;
            }

            $journalEntryId = (string) Str::uuid();
            JournalEntry::query()->create([
                'id' => $journalEntryId,
                'tenant_id' => $tenantId,
                'reference_type' => 'opening_balance',
                'reference_id' => $session->id,
                'date' => $openingDate->toDateString(),
                'description' => 'Opening balance posting',
                'status' => 'posted',
                'posted_at' => $now,
                'created_by' => $actorId,
                'created_at' => $now,
            ]);

            $journalItems = [];
            if ($totals['inventory_total'] > 0) {
                $journalItems[] = [
                    'id' => (string) Str::uuid(),
                    'journal_entry_id' => $journalEntryId,
                    'tenant_id' => $tenantId,
                    'account_id' => $mapping->inventory_account_id,
                    'debit' => $totals['inventory_total'],
                    'credit' => 0,
                    'is_reversal' => false,
                ];
            }

            foreach ($session->fundLines as $line) {
                $journalItems[] = [
                    'id' => (string) Str::uuid(),
                    'journal_entry_id' => $journalEntryId,
                    'tenant_id' => $tenantId,
                    'account_id' => $line->account_id,
                    'debit' => (float) $line->amount,
                    'credit' => 0,
                    'is_reversal' => false,
                ];
            }

            $journalItems[] = [
                'id' => (string) Str::uuid(),
                'journal_entry_id' => $journalEntryId,
                'tenant_id' => $tenantId,
                'account_id' => $session->equity_account_id,
                'debit' => 0,
                'credit' => $totals['equity_total'],
                'is_reversal' => false,
            ];

            JournalItem::query()->insert($journalItems);

            $inventoryLayerCount = (int) DB::table('inventory_layers')
                ->where('tenant_id', $tenantId)
                ->where('source_type', 'opening_balance')
                ->where('source_id', $session->id)
                ->count();

            OpeningPostingLog::query()->create([
                'id' => (string) Str::uuid(),
                'opening_session_id' => $session->id,
                'tenant_id' => $tenantId,
                'journal_entry_id' => $journalEntryId,
                'stock_ledger_count' => $stockLedgerCount,
                'inventory_layer_count' => $inventoryLayerCount,
                'payload' => [
                    'totals' => $totals,
                    'fund_line_count' => $session->fundLines->count(),
                    'stock_line_count' => $session->stockLines->count(),
                ],
                'posted_at' => $now,
                'created_by' => $actorId,
            ]);

            $session->update([
                'status' => 'posted',
                'posted_at' => $now,
                'posted_by' => $actorId,
                'equity_amount' => $totals['equity_total'],
                'updated_at' => $now,
            ]);

            $this->lockService->lockInitialPeriod($session->fresh(), $actorId, 'Opening balance posted');

            $this->auditLogger->log(
                event: 'opening_balance.posted',
                actorId: $actorId,
                referenceType: 'opening_balance',
                referenceId: $session->id,
                payload: [
                    'totals' => $totals,
                    'stock_ledger_count' => $stockLedgerCount,
                    'inventory_layer_count' => $inventoryLayerCount,
                ],
                ipAddress: $context['ip_address'] ?? null,
                userAgent: $context['user_agent'] ?? null,
                tenantId: $tenantId,
                occurredAt: $now,
            );

            return OpeningSession::query()
                ->where('tenant_id', $tenantId)
                ->where('id', $session->id)
                ->with(['stockLines', 'fundLines', 'postingLogs'])
                ->firstOrFail();
        };

        if (! $wrapTransaction) {
            return $runner();
        }

        return DB::transaction($runner);
    }

    /**
     * @param  list<array{product_variant_id:?string,reference_item_id:?string,qty:float,unit_cost:float}>  $receipts
     */
    public function postInventoryOnlyOpening(
        string $tenantId,
        string $openingDate,
        string $locationId,
        string $productId,
        array $receipts,
        ?string $actorId,
        array $context = []
    ): ?OpeningSession {
        if ($receipts === []) {
            return null;
        }

        $session = $this->draftService->createSession([
            'tenant_id' => $tenantId,
            'opening_date' => $openingDate,
            'meta' => [
                'source' => $context['source'] ?? 'legacy_initial_stock',
                'product_id' => $productId,
            ],
        ], $actorId);

        foreach ($receipts as $receipt) {
            $this->draftService->addStockLine($session->id, [
                'tenant_id' => $tenantId,
                'location_id' => $locationId,
                'product_id' => $productId,
                'product_variant_id' => $receipt['product_variant_id'] ?? null,
                'qty' => (float) ($receipt['qty'] ?? 0),
                'unit_cost' => (float) ($receipt['unit_cost'] ?? 0),
                'reference_note' => $context['reference_note'] ?? null,
            ], $actorId);
        }

        return $this->post($session->id, $actorId, array_merge($context, [
            'tenant_id' => $tenantId,
        ]));
    }
}
