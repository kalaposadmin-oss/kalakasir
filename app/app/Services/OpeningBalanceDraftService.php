<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\OpeningFundLine;
use App\Models\OpeningSession;
use App\Models\OpeningStockLine;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use RuntimeException;

class OpeningBalanceDraftService
{
    public function __construct(
        private readonly OpeningBalanceLockService $lockService,
        private readonly TenantDefaultAccountingSetupService $accountingSetup,
    ) {
    }

    public function createSession(array $payload, ?string $actorId): OpeningSession
    {
        $tenantId = (string) ($payload['tenant_id'] ?? TenantContext::id());
        if (! $tenantId) {
            throw new RuntimeException('Tenant tidak ditemukan.');
        }

        $this->accountingSetup->ensure($tenantId, $actorId);

        $openingDate = (string) ($payload['opening_date'] ?? CarbonImmutable::now('UTC')->toDateString());
        $equityAccountId = $payload['equity_account_id'] ?? $this->resolveDefaultEquityAccountId($tenantId);
        $equityAmount = array_key_exists('equity_amount', $payload) && $payload['equity_amount'] !== null
            ? (float) $payload['equity_amount']
            : null;
        $now = CarbonImmutable::now('UTC');

        return OpeningSession::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'opening_date' => $openingDate,
            'status' => 'draft',
            'equity_account_id' => $equityAccountId,
            'equity_amount' => $equityAmount,
            'meta' => $payload['meta'] ?? null,
            'created_by' => $actorId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function addStockLine(string $sessionId, array $payload, ?string $actorId): OpeningStockLine
    {
        $session = $this->resolveSession($sessionId, $payload['tenant_id'] ?? null);
        $this->lockService->preventModification($session);

        $qty = (float) ($payload['qty'] ?? 0);
        $unitCost = (float) ($payload['unit_cost'] ?? 0);
        if ($qty <= 0 || $unitCost <= 0) {
            throw new RuntimeException('Qty dan unit cost opening stock wajib lebih dari 0.');
        }

        $lineValue = round($qty * $unitCost, 2);
        $now = CarbonImmutable::now('UTC');

        return OpeningStockLine::query()->create([
            'id' => (string) Str::uuid(),
            'opening_session_id' => $session->id,
            'tenant_id' => $session->tenant_id,
            'location_id' => $payload['location_id'],
            'product_id' => $payload['product_id'],
            'product_variant_id' => $payload['product_variant_id'] ?? null,
            'qty' => $qty,
            'unit_cost' => $unitCost,
            'line_value' => $lineValue,
            'reference_note' => $payload['reference_note'] ?? null,
            'created_by' => $actorId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function addFundLine(string $sessionId, array $payload, ?string $actorId): OpeningFundLine
    {
        $session = $this->resolveSession($sessionId, $payload['tenant_id'] ?? null);
        $this->lockService->preventModification($session);

        $amount = (float) ($payload['amount'] ?? 0);
        if ($amount <= 0) {
            throw new RuntimeException('Nilai opening cash/bank wajib lebih dari 0.');
        }

        $sourceType = (string) ($payload['source_type'] ?? 'cash');
        if (! in_array($sourceType, ['cash', 'bank'], true)) {
            throw new RuntimeException('source_type opening fund harus cash atau bank.');
        }

        $now = CarbonImmutable::now('UTC');

        return OpeningFundLine::query()->create([
            'id' => (string) Str::uuid(),
            'opening_session_id' => $session->id,
            'tenant_id' => $session->tenant_id,
            'account_id' => $payload['account_id'],
            'source_type' => $sourceType,
            'amount' => $amount,
            'reference_note' => $payload['reference_note'] ?? null,
            'created_by' => $actorId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function updateStockLine(string $sessionId, string $lineId, array $payload): OpeningStockLine
    {
        $session = $this->resolveSession($sessionId, $payload['tenant_id'] ?? null);
        $this->lockService->preventModification($session);

        $line = OpeningStockLine::query()
            ->where('tenant_id', $session->tenant_id)
            ->where('opening_session_id', $session->id)
            ->where('id', $lineId)
            ->first();

        if (! $line) {
            throw new RuntimeException('Stock line opening balance tidak ditemukan.');
        }

        $qty = (float) ($payload['qty'] ?? 0);
        $unitCost = (float) ($payload['unit_cost'] ?? 0);
        if ($qty <= 0 || $unitCost <= 0) {
            throw new RuntimeException('Qty dan unit cost opening stock wajib lebih dari 0.');
        }

        $locationId = (string) $payload['location_id'];
        $productId = (string) $payload['product_id'];
        $productVariantId = $payload['product_variant_id'] ?? null;

        $duplicateExists = OpeningStockLine::query()
            ->where('tenant_id', $session->tenant_id)
            ->where('opening_session_id', $session->id)
            ->where('location_id', $locationId)
            ->where('product_id', $productId)
            ->where(function ($query) use ($productVariantId): void {
                if ($productVariantId === null) {
                    $query->whereNull('product_variant_id');

                    return;
                }

                $query->where('product_variant_id', $productVariantId);
            })
            ->where('id', '!=', $line->id)
            ->exists();

        if ($duplicateExists) {
            throw new RuntimeException('Duplikasi produk/lokasi/varian pada opening stock tidak diizinkan.');
        }

        $line->forceFill([
            'location_id' => $locationId,
            'product_id' => $productId,
            'product_variant_id' => $productVariantId,
            'qty' => $qty,
            'unit_cost' => $unitCost,
            'line_value' => round($qty * $unitCost, 2),
            'reference_note' => $payload['reference_note'] ?? null,
        ])->save();

        return $line->refresh();
    }

    public function updateFundLine(string $sessionId, string $lineId, array $payload): OpeningFundLine
    {
        $session = $this->resolveSession($sessionId, $payload['tenant_id'] ?? null);
        $this->lockService->preventModification($session);

        $line = OpeningFundLine::query()
            ->where('tenant_id', $session->tenant_id)
            ->where('opening_session_id', $session->id)
            ->where('id', $lineId)
            ->first();

        if (! $line) {
            throw new RuntimeException('Fund line opening balance tidak ditemukan.');
        }

        $amount = (float) ($payload['amount'] ?? 0);
        if ($amount <= 0) {
            throw new RuntimeException('Nilai opening cash/bank wajib lebih dari 0.');
        }

        $sourceType = (string) ($payload['source_type'] ?? 'cash');
        if (! in_array($sourceType, ['cash', 'bank'], true)) {
            throw new RuntimeException('source_type opening fund harus cash atau bank.');
        }

        $accountId = (string) $payload['account_id'];

        $duplicateExists = OpeningFundLine::query()
            ->where('tenant_id', $session->tenant_id)
            ->where('opening_session_id', $session->id)
            ->where('account_id', $accountId)
            ->where('source_type', $sourceType)
            ->where('id', '!=', $line->id)
            ->exists();

        if ($duplicateExists) {
            throw new RuntimeException('Duplikasi akun/sumber pada opening fund tidak diizinkan.');
        }

        $line->forceFill([
            'account_id' => $accountId,
            'source_type' => $sourceType,
            'amount' => $amount,
            'reference_note' => $payload['reference_note'] ?? null,
        ])->save();

        return $line->refresh();
    }

    public function deleteStockLine(string $sessionId, string $lineId, ?string $tenantIdOverride = null): void
    {
        $session = $this->resolveSession($sessionId, $tenantIdOverride);
        $this->lockService->preventModification($session);

        $line = OpeningStockLine::query()
            ->where('tenant_id', $session->tenant_id)
            ->where('opening_session_id', $session->id)
            ->where('id', $lineId)
            ->first();

        if (! $line) {
            throw new RuntimeException('Stock line opening balance tidak ditemukan.');
        }

        $line->delete();
    }

    public function deleteFundLine(string $sessionId, string $lineId, ?string $tenantIdOverride = null): void
    {
        $session = $this->resolveSession($sessionId, $tenantIdOverride);
        $this->lockService->preventModification($session);

        $line = OpeningFundLine::query()
            ->where('tenant_id', $session->tenant_id)
            ->where('opening_session_id', $session->id)
            ->where('id', $lineId)
            ->first();

        if (! $line) {
            throw new RuntimeException('Fund line opening balance tidak ditemukan.');
        }

        $line->delete();
    }

    private function resolveSession(string $sessionId, ?string $tenantIdOverride = null): OpeningSession
    {
        $tenantId = (string) ($tenantIdOverride ?: TenantContext::id());
        if (! $tenantId) {
            throw new RuntimeException('Tenant tidak ditemukan.');
        }

        return OpeningSession::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $sessionId)
            ->firstOrFail();
    }

    private function resolveDefaultEquityAccountId(string $tenantId): ?string
    {
        return ChartOfAccount::query()
            ->where('tenant_id', $tenantId)
            ->where('type', 'equity')
            ->orderBy('code')
            ->value('id');
    }
}
