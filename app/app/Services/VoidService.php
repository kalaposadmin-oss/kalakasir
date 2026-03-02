<?php

namespace App\Services;

use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\Sale;
use App\Models\StockLedger;
use App\Services\LockPeriodService;
use App\Services\InventoryValuationService;
use App\Support\TenantContext;
use App\Events\SaleVoided;
use Carbon\CarbonImmutable;
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
        ?string $userAgent = null
    ): void
    {
        $tenantId = TenantContext::id();

        if (! $tenantId) {
            throw new RuntimeException('Tenant tidak ditemukan.');
        }

        $alreadyVoided = Sale::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $saleId)
            ->value('status');

        if ($alreadyVoided === 'voided') {
            return;
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

        app(DatabaseTransactionService::class)->run(function () use ($saleId, $tenantId, $userId, $reason, $approvedBy, $clientIp, $userAgent): void {
            $valuationService = app(InventoryValuationService::class);

            $sale = Sale::query()
                ->where('tenant_id', $tenantId)
                ->where('id', $saleId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($sale->status === 'voided') {
                return;
            }

            $now = CarbonImmutable::now('UTC');
            (new LockPeriodService())->ensureNotLocked($now, $tenantId);

            $ledgerRows = StockLedger::query()
                ->where('tenant_id', $tenantId)
                ->where('reference_type', 'sale')
                ->where('reference_id', $saleId)
                ->get();

            foreach ($ledgerRows as $row) {
                DB::update(
                    'UPDATE inventory_balances SET qty_on_hand = qty_on_hand + ? WHERE tenant_id = ? AND product_id = ? AND location_id = ?',
                    [$row->qty_out, $tenantId, $row->product_id, $row->location_id]
                );

                $balanceAfter = (float) DB::table('inventory_balances')
                    ->where('tenant_id', $tenantId)
                    ->where('product_id', $row->product_id)
                    ->where('location_id', $row->location_id)
                    ->value('qty_on_hand');

                StockLedger::query()->create([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'product_id' => $row->product_id,
                    'location_id' => $row->location_id,
                    'reference_type' => 'void',
                    'reference_id' => $saleId,
                    'reference_item_id' => $row->reference_item_id,
                    'qty_in' => $row->qty_out,
                    'qty_out' => 0,
                    'balance_after' => $balanceAfter,
                    'created_at' => $now,
                    'created_by' => $userId,
                ]);
            }

            $valuationService->reverseIssue(
                tenantId: $tenantId,
                referenceType: 'sale',
                referenceId: $saleId,
                at: $now,
                newSourceType: 'void',
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

            DB::table('audit_logs')->insert([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'actor_id' => $userId,
                'event_type' => 'sale_void_authorized',
                'reference_type' => 'sale',
                'reference_id' => $saleId,
                'payload_json' => json_encode([
                    'approver_id' => $approvedBy,
                    'reason' => $reason,
                ], JSON_UNESCAPED_SLASHES),
                'ip_address' => $clientIp,
                'user_agent' => $userAgent,
                'created_at' => $now,
            ]);

            event(new SaleVoided(
                saleId: $sale->id,
                tenantId: $tenantId,
                actorId: $userId,
                reason: $reason,
                occurredAt: $now->toIso8601String()
            ));
        });
    }
}
