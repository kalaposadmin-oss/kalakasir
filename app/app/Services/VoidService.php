<?php

namespace App\Services;

use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\Refund;
use App\Models\Sale;
use App\Models\StockLedger;
use App\Services\LockPeriodService;
use App\Services\InventoryValuationService;
use App\Support\Decimal;
use App\Support\TenantContext;
use App\Events\SaleVoided;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class VoidService
{
    public function handle(
        string $saleId,
        ?string $userId,
        ?string $reason = null,
        ?string $managerPin = null,
        ?string $clientIp = null,
        ?string $userAgent = null,
        ?string $requestId = null,
        ?string $idempotencyKey = null
    ): array
    {
        $tenantId = TenantContext::id();

        if (! $tenantId) {
            throw new RuntimeException('Tenant tidak ditemukan.');
        }

        $idempotencyKey = is_string($idempotencyKey) ? trim($idempotencyKey) : null;
        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $result = app(OperationIdempotencyStore::class)->getOrRemember(
                $this->idempotencyKey($tenantId, $saleId, $idempotencyKey),
                fn (): array => $this->performVoid($saleId, $userId, $reason, $managerPin, $clientIp, $userAgent, $requestId, $tenantId)
            );

            return $result['value'];
        }

        return $this->performVoid($saleId, $userId, $reason, $managerPin, $clientIp, $userAgent, $requestId, $tenantId);
    }

    private function performVoid(
        string $saleId,
        ?string $userId,
        ?string $reason,
        ?string $managerPin,
        ?string $clientIp,
        ?string $userAgent,
        ?string $requestId,
        string $tenantId
    ): array {
        $alreadyVoided = Sale::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $saleId)
            ->value('status');

        if ($alreadyVoided === 'voided') {
            return [
                'status' => 'voided',
                'already_voided' => true,
            ];
        }

        $saleForAuthorization = Sale::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $saleId)
            ->firstOrFail();

        $policy = app(TransactionAuthorizationPolicyService::class)->evaluateVoid(
            tenantId: $tenantId,
            actorUserId: $userId,
            amount: (float) $saleForAuthorization->total,
        );

        $approvedBy = $userId;
        if (! ($policy['direct_allowed'] ?? false)) {
            $approvedBy = app(ManagerPinAuthorizationService::class)->authorizeVoid(
                tenantId: $tenantId,
                actorUserId: $userId,
                managerPin: $managerPin,
                ipAddress: $clientIp,
                allowedApproverRoles: (array) ($policy['allowed_approver_roles'] ?? ['admin', 'owner']),
            );
        }

        $alreadyVoided = app(DatabaseTransactionService::class)->run(function () use ($saleId, $tenantId, $userId, $reason, $approvedBy, $clientIp, $userAgent, $requestId): bool {
            $valuationService = app(InventoryValuationService::class);

            $sale = Sale::query()
                ->where('tenant_id', $tenantId)
                ->where('id', $saleId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($sale->status === 'voided') {
                return true;
            }

            if ($sale->status !== Sale::STATUS_COMPLETED) {
                throw new RuntimeException('Transaksi tidak dapat di-void.');
            }

            $hasCompletedRefund = Refund::query()
                ->where('tenant_id', $tenantId)
                ->where('sale_id', $saleId)
                ->where('status', Refund::STATUS_COMPLETED)
                ->exists();

            if ($hasCompletedRefund) {
                throw new RuntimeException('Transaksi yang sudah direfund tidak bisa di-void.');
            }

            $now = CarbonImmutable::now('UTC');
            (new LockPeriodService())->ensureNotLocked($now, $tenantId);

            $ledgerGroups = $this->loadLedgerGroups($tenantId, $saleId);

            $this->reverseSaleLedgerRows(
                tenantId: $tenantId,
                saleId: $saleId,
                ledgerRows: $ledgerGroups->get('sale', collect()),
                userId: $userId,
                now: $now,
            );

            $this->reverseRecipeInputLedgerRows(
                tenantId: $tenantId,
                saleId: $saleId,
                ledgerRows: $ledgerGroups->get('sale_recipe_input', collect()),
                userId: $userId,
                now: $now,
            );

            $valuationService->reverseIssue(
                tenantId: $tenantId,
                referenceType: 'sale',
                referenceId: $saleId,
                at: $now,
                newSourceType: 'void',
                newSourceId: $saleId
            );

            $valuationService->reverseIssue(
                tenantId: $tenantId,
                referenceType: 'sale_recipe_input',
                referenceId: $saleId,
                at: $now,
                newSourceType: 'void_recipe_input',
                newSourceId: $saleId
            );

            $journalEntries = JournalEntry::query()
                ->where('tenant_id', $tenantId)
                ->where('reference_type', 'sale')
                ->where('reference_id', $saleId)
                ->get();

            foreach ($journalEntries as $entry) {
                $items = JournalItem::query()
                    ->where('tenant_id', $tenantId)
                    ->where('journal_entry_id', $entry->id)
                    ->get();

                $reversalEntryId = (string) Str::uuid();
                JournalEntry::query()->create([
                    'id' => $reversalEntryId,
                    'tenant_id' => $tenantId,
                    'reference_type' => 'void',
                    'reference_id' => $saleId,
                    'date' => $now->toDateString(),
                    'description' => $reason ? 'Void sale: ' . $reason : 'Void sale',
                    'status' => 'posted',
                    'posted_at' => $now,
                    'created_by' => $userId,
                    'created_at' => $now,
                ]);

                $reversalItems = $items->map(function ($item) use ($tenantId, $reversalEntryId) {
                    return [
                        'id' => (string) Str::uuid(),
                        'journal_entry_id' => $reversalEntryId,
                        'tenant_id' => $tenantId,
                        'account_id' => $item->account_id,
                        'debit' => $item->credit,
                        'credit' => $item->debit,
                        'is_reversal' => true,
                    ];
                })->all();

                JournalItem::query()->insert($reversalItems);
            }

            $sale->update([
                'status' => 'voided',
                'voided_by' => $userId,
                'void_approved_by' => $approvedBy,
                'void_reason' => $reason,
                'voided_at' => $now,
            ]);

            $payload = [
                'approver_id' => $approvedBy,
                'reason' => $reason,
            ];

            if ($requestId) {
                $payload['request_id'] = $requestId;
            }

            DB::table('audit_logs')->insert([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'actor_id' => $userId,
                'event_type' => 'sale_void_authorized',
                'reference_type' => 'sale',
                'reference_id' => $saleId,
                'payload_json' => json_encode($payload, JSON_UNESCAPED_SLASHES),
                'ip_address' => $clientIp,
                'user_agent' => $userAgent,
                'created_at' => $now,
            ]);

            event(new SaleVoided(
                saleId: $sale->id,
                tenantId: $tenantId,
                actorId: $userId,
                reason: $reason,
                occurredAt: $now->toIso8601String(),
                requestId: $requestId
            ));

            return false;
        });

        return [
            'status' => 'voided',
            'already_voided' => $alreadyVoided,
        ];
    }

    private function idempotencyKey(string $tenantId, string $saleId, string $idempotencyKey): string
    {
        return 'pos:void:'.sha1($tenantId.'|'.$saleId.'|'.$idempotencyKey);
    }

    private function loadLedgerGroups(string $tenantId, string $saleId): Collection
    {
        return StockLedger::query()
            ->where('tenant_id', $tenantId)
            ->where('reference_id', $saleId)
            ->whereIn('reference_type', ['sale', 'sale_recipe_input'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->groupBy('reference_type');
    }

    private function reverseSaleLedgerRows(
        string $tenantId,
        string $saleId,
        Collection $ledgerRows,
        ?string $userId,
        CarbonImmutable $now
    ): void {
        foreach ($ledgerRows as $row) {
            $qty = Decimal::qty($row->qty_out);

            if (Decimal::compare($qty, '0') <= 0) {
                continue;
            }

            $this->incrementInventory(
                tenantId: $tenantId,
                productId: (string) $row->product_id,
                productVariantId: $row->product_variant_id,
                locationId: (string) $row->location_id,
                qty: Decimal::float($qty, Decimal::QTY_SCALE),
            );

            $balanceAfter = $this->readInventoryBalance(
                tenantId: $tenantId,
                productId: (string) $row->product_id,
                productVariantId: $row->product_variant_id,
                locationId: (string) $row->location_id,
            );

            $this->createReversalLedgerRow(
                tenantId: $tenantId,
                saleId: $saleId,
                row: $row,
                qty: Decimal::float($qty, Decimal::QTY_SCALE),
                balanceAfter: $balanceAfter,
                reversalReferenceType: 'void',
                userId: $userId,
                now: $now,
            );
        }
    }

    private function reverseRecipeInputLedgerRows(
        string $tenantId,
        string $saleId,
        Collection $ledgerRows,
        ?string $userId,
        CarbonImmutable $now
    ): void {
        foreach ($ledgerRows as $row) {
            $qty = Decimal::qty($row->qty_out);

            if (Decimal::compare($qty, '0') <= 0) {
                continue;
            }

            $this->incrementInventory(
                tenantId: $tenantId,
                productId: (string) $row->product_id,
                productVariantId: $row->product_variant_id,
                locationId: (string) $row->location_id,
                qty: Decimal::float($qty, Decimal::QTY_SCALE),
            );

            $balanceAfter = $this->readInventoryBalance(
                tenantId: $tenantId,
                productId: (string) $row->product_id,
                productVariantId: $row->product_variant_id,
                locationId: (string) $row->location_id,
            );

            $this->createReversalLedgerRow(
                tenantId: $tenantId,
                saleId: $saleId,
                row: $row,
                qty: Decimal::float($qty, Decimal::QTY_SCALE),
                balanceAfter: $balanceAfter,
                reversalReferenceType: 'void_recipe_input',
                userId: $userId,
                now: $now,
            );
        }
    }

    private function createReversalLedgerRow(
        string $tenantId,
        string $saleId,
        StockLedger $row,
        float $qty,
        float $balanceAfter,
        string $reversalReferenceType,
        ?string $userId,
        CarbonImmutable $now
    ): void {
        StockLedger::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'product_id' => $row->product_id,
            'product_variant_id' => $row->product_variant_id,
            'location_id' => $row->location_id,
            'reference_type' => $reversalReferenceType,
            'reference_id' => $saleId,
            'reference_item_id' => $row->reference_item_id,
            'qty_in' => $qty,
            'qty_out' => 0,
            'balance_after' => $balanceAfter,
            'created_at' => $now,
            'created_by' => $userId,
        ]);
    }

    private function incrementInventory(
        string $tenantId,
        string $productId,
        ?string $productVariantId,
        string $locationId,
        float $qty
    ): void {
        if ($productVariantId) {
            $affected = DB::update(
                'UPDATE inventory_balances SET qty_on_hand = qty_on_hand + ? WHERE tenant_id = ? AND product_id = ? AND product_variant_id = ? AND location_id = ?',
                [$qty, $tenantId, $productId, $productVariantId, $locationId]
            );

            if ($affected === 0) {
                DB::table('inventory_balances')->insert([
                    'tenant_id' => $tenantId,
                    'location_id' => $locationId,
                    'product_id' => $productId,
                    'product_variant_id' => $productVariantId,
                    'qty_on_hand' => $qty,
                ]);
            }

            return;
        }

        $affected = DB::update(
            'UPDATE inventory_balances SET qty_on_hand = qty_on_hand + ? WHERE tenant_id = ? AND product_id = ? AND location_id = ? AND product_variant_id IS NULL',
            [$qty, $tenantId, $productId, $locationId]
        );

        if ($affected === 0) {
            DB::table('inventory_balances')->insert([
                'tenant_id' => $tenantId,
                'location_id' => $locationId,
                'product_id' => $productId,
                'product_variant_id' => null,
                'qty_on_hand' => $qty,
            ]);
        }
    }

    private function readInventoryBalance(
        string $tenantId,
        string $productId,
        ?string $productVariantId,
        string $locationId
    ): float {
        return Decimal::float(Decimal::qty(DB::table('inventory_balances')
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('location_id', $locationId)
            ->when($productVariantId, function ($query) use ($productVariantId) {
                $query->where('product_variant_id', $productVariantId);
            }, function ($query) {
                $query->whereNull('product_variant_id');
            })
            ->value('qty_on_hand')), Decimal::QTY_SCALE);
    }
}
