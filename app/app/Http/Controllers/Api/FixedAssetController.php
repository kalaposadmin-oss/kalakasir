<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FixedAsset;
use App\Models\FixedAssetAudit;
use App\Models\FixedAssetDepreciationEntry;
use App\Models\FixedAssetMaintenanceLog;
use App\Models\FixedAssetPaymentLog;
use App\Models\CashTransaction;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Services\TenantDefaultAccountingSetupService;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class FixedAssetController extends Controller
{
    private const PAYMENT_METHODS = ['cash', 'bank', 'payable', 'unposted'];

    public function index(Request $request)
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json(['message' => 'Tenant tidak ditemukan.'], 400);
        }

        $assets = FixedAsset::query()
            ->where('tenant_id', $tenantId)
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->when($request->filled('asset_category'), fn ($query) => $query->where('asset_category', $request->query('asset_category')))
            ->orderBy('asset_code')
            ->get();

        $mappedAssets = $assets->map(fn (FixedAsset $asset) => $this->mapAsset($asset))->values();

        return response()->json([
            'assets' => $mappedAssets,
            'data' => $mappedAssets,
            'accounting_defaults' => $this->accountingDefaults($tenantId),
        ]);
    }

    public function preview(Request $request)
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json(['message' => 'Tenant tidak ditemukan.'], 400);
        }

        app(TenantDefaultAccountingSetupService::class)->ensure($tenantId, $request->user()?->id);

        $payload = $request->validate([
            'purchase_price' => ['required', 'numeric', 'min:0'],
            'quantity' => ['nullable', 'numeric', 'min:0.0001', 'max:999999999'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'residual_value' => ['nullable', 'numeric', 'min:0'],
            'useful_life_months' => ['nullable', 'integer', 'min:0', 'max:600'],
            'payment_method' => ['nullable', Rule::in(self::PAYMENT_METHODS)],
            'payment_account_id' => ['nullable', 'uuid'],
        ]);

        [$quantity, $unitPrice, $purchasePrice] = $this->normalizePurchaseAmounts($payload);
        $residualValue = min((float) ($payload['residual_value'] ?? 0), $purchasePrice);
        $usefulLifeMonths = (int) ($payload['useful_life_months'] ?? 0);
        $monthlyDepreciation = $usefulLifeMonths > 0
            ? round(max(0, $purchasePrice - $residualValue) / $usefulLifeMonths, 2)
            : 0.0;

        return response()->json([
            'preview' => array_merge($this->buildPreview($purchasePrice, $residualValue, $usefulLifeMonths, $payload['payment_method'] ?? 'unposted'), [
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'purchase_total' => $purchasePrice,
                'monthly_depreciation' => $monthlyDepreciation,
                'annual_depreciation' => round($monthlyDepreciation * 12, 2),
                'book_value_after_12_months' => max($residualValue, round($purchasePrice - ($monthlyDepreciation * 12), 2)),
            ]),
            'accounting_defaults' => $this->accountingDefaults($tenantId),
        ]);
    }

    public function store(Request $request)
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json(['message' => 'Tenant tidak ditemukan.'], 400);
        }

        $payload = $request->validate([
            'asset_code' => ['required', 'string', 'max:80', Rule::unique('fixed_assets', 'asset_code')->where('tenant_id', $tenantId)],
            'name' => ['required', 'string', 'max:150'],
            'asset_category' => ['nullable', 'string', 'max:80'],
            'location_id' => ['nullable', 'uuid', Rule::exists('locations', 'id')->where('tenant_id', $tenantId)],
            'supplier_id' => ['nullable', 'uuid', Rule::exists('suppliers', 'id')->where('tenant_id', $tenantId)],
            'payment_method' => ['nullable', Rule::in(self::PAYMENT_METHODS)],
            'payment_account_id' => ['nullable', 'uuid', Rule::exists('chart_of_accounts', 'id')->where('tenant_id', $tenantId)],
            'serial_number' => ['nullable', 'string', 'max:120'],
            'purchase_date' => ['nullable', 'date'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'quantity' => ['nullable', 'numeric', 'min:0.0001', 'max:999999999'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'residual_value' => ['nullable', 'numeric', 'min:0'],
            'useful_life_months' => ['nullable', 'integer', 'min:0', 'max:600'],
            'depreciation_method' => ['nullable', Rule::in(FixedAsset::DEPRECIATION_METHODS)],
            'condition' => ['nullable', Rule::in(FixedAsset::CONDITIONS)],
            'status' => ['nullable', Rule::in(FixedAsset::STATUSES)],
            'warranty_until' => ['nullable', 'date'],
            'next_maintenance_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        [$quantity, $unitPrice, $purchasePrice] = $this->normalizePurchaseAmounts($payload);
        $residualValue = min((float) ($payload['residual_value'] ?? 0), $purchasePrice);
        $method = (string) ($payload['depreciation_method'] ?? 'straight_line');
        $usefulLifeMonths = (int) ($payload['useful_life_months'] ?? 0);
        $paymentMethod = (string) ($payload['payment_method'] ?? 'unposted');

        if ($method === 'straight_line' && $purchasePrice > 0 && $usefulLifeMonths <= 0) {
            return response()->json([
                'message' => 'Umur pakai wajib diisi untuk aset dengan depresiasi straight line.',
                'errors' => ['useful_life_months' => ['Umur pakai wajib diisi.']],
            ], 422);
        }

        $asset = DB::transaction(function () use ($tenantId, $payload, $quantity, $unitPrice, $purchasePrice, $residualValue, $usefulLifeMonths, $method, $paymentMethod, $request) {
            $defaults = $this->accountingDefaults($tenantId, $request->user()?->id);
            $paymentAccountId = $this->resolvePaymentAccountId($tenantId, $paymentMethod, $payload['payment_account_id'] ?? null, $defaults);

            $asset = FixedAsset::query()->create([
                'tenant_id' => $tenantId,
                'asset_code' => $payload['asset_code'],
                'name' => $payload['name'],
                'asset_category' => $payload['asset_category'] ?? 'equipment',
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'location_id' => $payload['location_id'] ?? null,
                'supplier_id' => $payload['supplier_id'] ?? null,
                'payment_method' => $paymentMethod,
                'payment_account_id' => $paymentAccountId,
                'fixed_asset_account_id' => $defaults['fixed_asset_account_id'] ?? null,
                'accumulated_depreciation_account_id' => $defaults['accumulated_depreciation_account_id'] ?? null,
                'depreciation_expense_account_id' => $defaults['depreciation_expense_account_id'] ?? null,
                'liability_status' => $paymentMethod === 'payable' && $purchasePrice > 0 ? 'open' : 'none',
                'serial_number' => $payload['serial_number'] ?? null,
                'purchase_date' => $payload['purchase_date'] ?? null,
                'purchase_price' => $purchasePrice,
                'residual_value' => $residualValue,
                'useful_life_months' => $usefulLifeMonths,
                'depreciation_method' => $method,
                'accumulated_depreciation' => 0,
                'book_value' => $purchasePrice,
                'condition' => $payload['condition'] ?? 'good',
                'status' => $payload['status'] ?? 'active',
                'warranty_until' => $payload['warranty_until'] ?? null,
                'next_maintenance_at' => $payload['next_maintenance_at'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'created_by' => $request->user()?->id,
                'updated_by' => $request->user()?->id,
            ]);

            if ($purchasePrice > 0 && $paymentMethod !== 'unposted') {
                $posting = $this->postPurchaseJournal($tenantId, $asset, $paymentMethod, $paymentAccountId, $defaults, $request);
                $asset->update([
                    'purchase_journal_entry_id' => $posting['journal_entry_id'],
                    'purchase_cash_transaction_id' => $posting['cash_transaction_id'],
                ]);
            }

            return $asset->fresh();
        });

        return response()->json([
            'asset' => $this->mapAsset($asset),
            'preview' => array_merge($this->buildPreview($purchasePrice, $residualValue, $usefulLifeMonths, $paymentMethod), [
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'purchase_total' => $purchasePrice,
            ]),
        ], 201);
    }

    public function show(string $asset)
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json(['message' => 'Tenant tidak ditemukan.'], 400);
        }

        $assetRow = $this->resolveAsset($tenantId, $asset);

        return response()->json([
            'asset' => $this->mapAsset($assetRow),
            'maintenance_logs' => FixedAssetMaintenanceLog::query()
                ->where('tenant_id', $tenantId)
                ->where('fixed_asset_id', $assetRow->id)
                ->orderByDesc('maintenance_date')
                ->get(),
            'depreciation_entries' => FixedAssetDepreciationEntry::query()
                ->where('tenant_id', $tenantId)
                ->where('fixed_asset_id', $assetRow->id)
                ->orderByDesc('period_date')
                ->get(),
            'audits' => FixedAssetAudit::query()
                ->where('tenant_id', $tenantId)
                ->where('fixed_asset_id', $assetRow->id)
                ->orderByDesc('audit_date')
                ->get(),
            'payment_logs' => FixedAssetPaymentLog::query()
                ->where('tenant_id', $tenantId)
                ->where('fixed_asset_id', $assetRow->id)
                ->orderByDesc('payment_date')
                ->get(),
        ]);
    }

    public function update(Request $request, string $asset)
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json(['message' => 'Tenant tidak ditemukan.'], 400);
        }

        $assetRow = $this->resolveAsset($tenantId, $asset);

        $payload = $request->validate([
            'asset_code' => ['required', 'string', 'max:80', Rule::unique('fixed_assets', 'asset_code')->where('tenant_id', $tenantId)->ignore($assetRow->id)],
            'name' => ['required', 'string', 'max:150'],
            'asset_category' => ['nullable', 'string', 'max:80'],
            'location_id' => ['nullable', 'uuid', Rule::exists('locations', 'id')->where('tenant_id', $tenantId)],
            'supplier_id' => ['nullable', 'uuid', Rule::exists('suppliers', 'id')->where('tenant_id', $tenantId)],
            'serial_number' => ['nullable', 'string', 'max:120'],
            'purchase_date' => ['nullable', 'date'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'quantity' => ['nullable', 'numeric', 'min:0.0001', 'max:999999999'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'residual_value' => ['nullable', 'numeric', 'min:0'],
            'useful_life_months' => ['nullable', 'integer', 'min:0', 'max:600'],
            'depreciation_method' => ['nullable', Rule::in(FixedAsset::DEPRECIATION_METHODS)],
            'condition' => ['nullable', Rule::in(FixedAsset::CONDITIONS)],
            'status' => ['nullable', Rule::in(FixedAsset::STATUSES)],
            'warranty_until' => ['nullable', 'date'],
            'next_maintenance_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        [$quantity, $unitPrice, $purchasePrice] = $this->normalizePurchaseAmounts($payload);
        $residualValue = min((float) ($payload['residual_value'] ?? 0), $purchasePrice);
        $usefulLifeMonths = (int) ($payload['useful_life_months'] ?? 0);
        $method = (string) ($payload['depreciation_method'] ?? $assetRow->depreciation_method ?? 'straight_line');

        if ($method === 'straight_line' && $purchasePrice > 0 && $usefulLifeMonths <= 0) {
            return response()->json([
                'message' => 'Umur pakai wajib diisi untuk aset dengan depresiasi straight line.',
                'errors' => ['useful_life_months' => ['Umur pakai wajib diisi.']],
            ], 422);
        }

        $financialChanged = $this->assetFinancialFieldsChanged($assetRow, $quantity, $unitPrice, $purchasePrice, $residualValue, $usefulLifeMonths, $method);
        $hasFinancialHistory = filled($assetRow->purchase_journal_entry_id)
            || (float) $assetRow->accumulated_depreciation > 0
            || (float) $assetRow->liability_paid_amount > 0;

        if ($financialChanged && $hasFinancialHistory) {
            return response()->json([
                'message' => 'Nilai pembelian, jumlah, umur pakai, dan metode depresiasi tidak bisa diubah karena aset sudah punya jurnal/depresiasi/pembayaran. Ubah data umum saja atau buat penyesuaian akuntansi.',
            ], 422);
        }

        $assetRow->forceFill([
            'asset_code' => $payload['asset_code'],
            'name' => $payload['name'],
            'asset_category' => $payload['asset_category'] ?? $assetRow->asset_category,
            'quantity' => $financialChanged ? $quantity : $assetRow->quantity,
            'unit_price' => $financialChanged ? $unitPrice : $assetRow->unit_price,
            'location_id' => $payload['location_id'] ?? null,
            'supplier_id' => $payload['supplier_id'] ?? null,
            'serial_number' => $payload['serial_number'] ?? null,
            'purchase_date' => $payload['purchase_date'] ?? null,
            'purchase_price' => $financialChanged ? $purchasePrice : $assetRow->purchase_price,
            'residual_value' => $financialChanged ? $residualValue : $assetRow->residual_value,
            'useful_life_months' => $financialChanged ? $usefulLifeMonths : $assetRow->useful_life_months,
            'depreciation_method' => $financialChanged ? $method : $assetRow->depreciation_method,
            'book_value' => $financialChanged ? $purchasePrice : $assetRow->book_value,
            'condition' => $payload['condition'] ?? $assetRow->condition,
            'status' => $payload['status'] ?? $assetRow->status,
            'warranty_until' => $payload['warranty_until'] ?? null,
            'next_maintenance_at' => $payload['next_maintenance_at'] ?? null,
            'notes' => $payload['notes'] ?? null,
            'updated_by' => $request->user()?->id,
        ])->save();

        return response()->json([
            'asset' => $this->mapAsset($assetRow->fresh()),
            'preview' => array_merge($this->buildPreview((float) $assetRow->purchase_price, (float) $assetRow->residual_value, (int) $assetRow->useful_life_months, (string) $assetRow->payment_method), [
                'quantity' => (float) $assetRow->quantity,
                'unit_price' => (float) $assetRow->unit_price,
                'purchase_total' => (float) $assetRow->purchase_price,
            ]),
        ]);
    }

    public function payment(Request $request, string $asset)
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json(['message' => 'Tenant tidak ditemukan.'], 400);
        }

        $assetRow = $this->resolveAsset($tenantId, $asset);
        if ($assetRow->payment_method !== 'payable') {
            return response()->json(['message' => 'Aset ini tidak tercatat sebagai pembelian hutang.'], 422);
        }

        $payload = $request->validate([
            'payment_date' => ['required', 'date'],
            'payment_account_id' => ['required', 'uuid', Rule::exists('chart_of_accounts', 'id')->where('tenant_id', $tenantId)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string'],
        ]);

        $remaining = $this->assetPayableRemaining($assetRow);
        $amount = round((float) $payload['amount'], 2);
        if ($amount > $remaining) {
            return response()->json([
                'message' => 'Nominal pembayaran melebihi sisa hutang aset.',
                'errors' => ['amount' => ['Sisa hutang aset adalah '.number_format($remaining, 2, '.', '').'.']],
            ], 422);
        }

        $log = DB::transaction(function () use ($tenantId, $assetRow, $payload, $amount, $request) {
            $posting = $this->postPayablePaymentJournal(
                $tenantId,
                $assetRow,
                (string) $payload['payment_account_id'],
                (string) $payload['payment_date'],
                $amount,
                $request
            );

            $log = FixedAssetPaymentLog::query()->create([
                'tenant_id' => $tenantId,
                'fixed_asset_id' => $assetRow->id,
                'payment_date' => $payload['payment_date'],
                'payment_account_id' => $payload['payment_account_id'],
                'journal_entry_id' => $posting['journal_entry_id'],
                'cash_transaction_id' => $posting['cash_transaction_id'],
                'amount' => $amount,
                'notes' => $payload['notes'] ?? null,
                'created_by' => $request->user()?->id,
            ]);

            $paidAmount = round((float) $assetRow->liability_paid_amount + $amount, 2);
            $assetRow->update([
                'liability_paid_amount' => $paidAmount,
                'liability_status' => $paidAmount >= (float) $assetRow->purchase_price ? 'paid' : 'partial',
                'updated_by' => $request->user()?->id,
            ]);

            return $log;
        });

        return response()->json([
            'payment' => $log,
            'asset' => $this->mapAsset($assetRow->fresh()),
        ], 201);
    }

    public function maintenance(Request $request, string $asset)
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json(['message' => 'Tenant tidak ditemukan.'], 400);
        }

        $assetRow = $this->resolveAsset($tenantId, $asset);
        $payload = $request->validate([
            'maintenance_date' => ['required', 'date'],
            'maintenance_type' => ['nullable', 'string', 'max:60'],
            'vendor_name' => ['nullable', 'string', 'max:150'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'condition_before' => ['nullable', Rule::in(FixedAsset::CONDITIONS)],
            'condition_after' => ['nullable', Rule::in(FixedAsset::CONDITIONS)],
            'next_maintenance_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $log = DB::transaction(function () use ($tenantId, $assetRow, $payload, $request) {
            $log = FixedAssetMaintenanceLog::query()->create([
                'tenant_id' => $tenantId,
                'fixed_asset_id' => $assetRow->id,
                'maintenance_date' => $payload['maintenance_date'],
                'maintenance_type' => $payload['maintenance_type'] ?? 'routine',
                'vendor_name' => $payload['vendor_name'] ?? null,
                'cost' => (float) ($payload['cost'] ?? 0),
                'condition_before' => $payload['condition_before'] ?? $assetRow->condition,
                'condition_after' => $payload['condition_after'] ?? $assetRow->condition,
                'next_maintenance_at' => $payload['next_maintenance_at'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'created_by' => $request->user()?->id,
            ]);

            $assetRow->update([
                'condition' => $payload['condition_after'] ?? $assetRow->condition,
                'status' => ($payload['condition_after'] ?? $assetRow->condition) === 'broken' ? 'maintenance' : $assetRow->status,
                'last_maintenance_at' => $payload['maintenance_date'],
                'next_maintenance_at' => $payload['next_maintenance_at'] ?? $assetRow->next_maintenance_at,
                'updated_by' => $request->user()?->id,
            ]);

            return $log;
        });

        return response()->json(['maintenance' => $log, 'asset' => $this->mapAsset($assetRow->fresh())], 201);
    }

    public function depreciate(Request $request, string $asset)
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json(['message' => 'Tenant tidak ditemukan.'], 400);
        }

        $assetRow = $this->resolveAsset($tenantId, $asset);
        $payload = $request->validate([
            'period_date' => ['required', 'date'],
        ]);

        if ($assetRow->depreciation_method === 'none' || (int) $assetRow->useful_life_months <= 0) {
            return response()->json(['message' => 'Aset ini tidak memakai depresiasi otomatis.'], 422);
        }

        $monthlyAmount = max(0, ((float) $assetRow->purchase_price - (float) $assetRow->residual_value) / max(1, (int) $assetRow->useful_life_months));
        $remainingDepreciable = max(0, (float) $assetRow->purchase_price - (float) $assetRow->residual_value - (float) $assetRow->accumulated_depreciation);
        $amount = min($monthlyAmount, $remainingDepreciable);

        $entry = DB::transaction(function () use ($tenantId, $assetRow, $payload, $amount, $request) {
            $accumulated = round((float) $assetRow->accumulated_depreciation + $amount, 2);
            $bookValue = max((float) $assetRow->residual_value, round((float) $assetRow->purchase_price - $accumulated, 2));
            $journalEntryId = null;

            if ($amount > 0) {
                $journalEntryId = $this->postDepreciationJournal($tenantId, $assetRow, $payload['period_date'], $amount, $request);
            }

            $entry = FixedAssetDepreciationEntry::query()->create([
                'tenant_id' => $tenantId,
                'fixed_asset_id' => $assetRow->id,
                'period_date' => CarbonImmutable::parse($payload['period_date'])->startOfMonth()->toDateString(),
                'depreciation_amount' => round($amount, 2),
                'accumulated_depreciation' => $accumulated,
                'book_value' => $bookValue,
                'journal_entry_id' => $journalEntryId,
                'posted_by' => $request->user()?->id,
                'posted_at' => CarbonImmutable::now('UTC'),
            ]);

            $assetRow->update([
                'accumulated_depreciation' => $accumulated,
                'book_value' => $bookValue,
                'updated_by' => $request->user()?->id,
            ]);

            return $entry;
        });

        return response()->json(['depreciation' => $entry, 'asset' => $this->mapAsset($assetRow->fresh())], 201);
    }

    public function audit(Request $request, string $asset)
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json(['message' => 'Tenant tidak ditemukan.'], 400);
        }

        $assetRow = $this->resolveAsset($tenantId, $asset);
        $payload = $request->validate([
            'audit_date' => ['required', 'date'],
            'location_id' => ['nullable', 'uuid', Rule::exists('locations', 'id')->where('tenant_id', $tenantId)],
            'physical_status' => ['nullable', Rule::in(['found', 'missing', 'moved', 'damaged'])],
            'condition' => ['nullable', Rule::in(FixedAsset::CONDITIONS)],
            'notes' => ['nullable', 'string'],
        ]);

        $audit = FixedAssetAudit::query()->create([
            'tenant_id' => $tenantId,
            'fixed_asset_id' => $assetRow->id,
            'audit_date' => $payload['audit_date'],
            'location_id' => $payload['location_id'] ?? $assetRow->location_id,
            'physical_status' => $payload['physical_status'] ?? 'found',
            'condition' => $payload['condition'] ?? $assetRow->condition,
            'notes' => $payload['notes'] ?? null,
            'audited_by' => $request->user()?->id,
        ]);

        return response()->json(['audit' => $audit], 201);
    }

    private function resolveAsset(string $tenantId, string $asset): FixedAsset
    {
        return FixedAsset::query()
            ->where('tenant_id', $tenantId)
            ->where(function ($query) use ($asset) {
                $query->where('id', $asset)
                    ->orWhere('asset_code', $asset);
            })
            ->firstOrFail();
    }

    private function mapAsset(FixedAsset $asset): array
    {
        return [
            'id' => $asset->id,
            'asset_code' => $asset->asset_code,
            'name' => $asset->name,
            'asset_category' => $asset->asset_category,
            'quantity' => (float) ($asset->quantity ?? 1),
            'unit_price' => (float) ($asset->unit_price ?? $asset->purchase_price),
            'location_id' => $asset->location_id,
            'supplier_id' => $asset->supplier_id,
            'payment_method' => $asset->payment_method,
            'payment_account_id' => $asset->payment_account_id,
            'fixed_asset_account_id' => $asset->fixed_asset_account_id,
            'accumulated_depreciation_account_id' => $asset->accumulated_depreciation_account_id,
            'depreciation_expense_account_id' => $asset->depreciation_expense_account_id,
            'purchase_journal_entry_id' => $asset->purchase_journal_entry_id,
            'purchase_cash_transaction_id' => $asset->purchase_cash_transaction_id,
            'liability_paid_amount' => (float) $asset->liability_paid_amount,
            'liability_status' => $asset->liability_status,
            'liability_remaining_amount' => $this->assetPayableRemaining($asset),
            'serial_number' => $asset->serial_number,
            'purchase_date' => $asset->purchase_date?->toDateString(),
            'purchase_price' => (float) $asset->purchase_price,
            'residual_value' => (float) $asset->residual_value,
            'useful_life_months' => (int) $asset->useful_life_months,
            'depreciation_method' => $asset->depreciation_method,
            'accumulated_depreciation' => (float) $asset->accumulated_depreciation,
            'book_value' => (float) $asset->book_value,
            'condition' => $asset->condition,
            'status' => $asset->status,
            'warranty_until' => $asset->warranty_until?->toDateString(),
            'last_maintenance_at' => $asset->last_maintenance_at?->toDateString(),
            'next_maintenance_at' => $asset->next_maintenance_at?->toDateString(),
            'notes' => $asset->notes,
        ];
    }

    private function accountingDefaults(string $tenantId, ?string $actorId = null): array
    {
        app(TenantDefaultAccountingSetupService::class)->ensure($tenantId, $actorId);

        $mapping = DB::table('account_mappings')->where('tenant_id', $tenantId)->first();

        return [
            'cash_account_id' => $mapping?->cash_account_id ? (string) $mapping->cash_account_id : null,
            'bank_account_id' => $mapping?->bank_account_id ? (string) $mapping->bank_account_id : null,
            'payable_account_id' => $mapping?->payable_account_id ? (string) $mapping->payable_account_id : null,
            'fixed_asset_account_id' => $this->accountIdByCode($tenantId, '1501'),
            'accumulated_depreciation_account_id' => $this->accountIdByCode($tenantId, '1502'),
            'depreciation_expense_account_id' => $this->accountIdByCode($tenantId, '6201'),
        ];
    }

    private function accountIdByCode(string $tenantId, string $code): ?string
    {
        $id = DB::table('chart_of_accounts')
            ->where('tenant_id', $tenantId)
            ->where('code', $code)
            ->value('id');

        return $id ? (string) $id : null;
    }

    private function resolvePaymentAccountId(string $tenantId, string $paymentMethod, ?string $requestedAccountId, array $defaults): ?string
    {
        if ($paymentMethod === 'cash') {
            return $requestedAccountId ?: ($defaults['cash_account_id'] ?? null);
        }

        if ($paymentMethod === 'bank') {
            return $requestedAccountId ?: ($defaults['bank_account_id'] ?? null);
        }

        if ($paymentMethod === 'payable') {
            return $defaults['payable_account_id'] ?? null;
        }

        return null;
    }

    private function postPurchaseJournal(string $tenantId, FixedAsset $asset, string $paymentMethod, ?string $paymentAccountId, array $defaults, Request $request): array
    {
        $fixedAssetAccountId = $defaults['fixed_asset_account_id'] ?? null;
        $creditAccountId = $paymentMethod === 'payable'
            ? ($defaults['payable_account_id'] ?? null)
            : $paymentAccountId;

        if (! $fixedAssetAccountId || ! $creditAccountId) {
            abort(422, 'Akun aset tetap dan akun pembayaran wajib tersedia.');
        }

        $purchaseDate = $asset->purchase_date?->toDateString() ?: CarbonImmutable::now('Asia/Jakarta')->toDateString();
        $amount = round((float) $asset->purchase_price, 2);

        $balanceBefore = null;
        if (in_array($paymentMethod, ['cash', 'bank'], true)) {
            $balanceBefore = $this->accountBalance($tenantId, $creditAccountId, $purchaseDate);
            if ($balanceBefore < $amount) {
                abort(422, 'Saldo kas/bank tidak mencukupi untuk pembelian aset ini.');
            }
        }

        $now = CarbonImmutable::now('UTC');
        $journalEntryId = (string) Str::uuid();

        JournalEntry::query()->create([
            'id' => $journalEntryId,
            'tenant_id' => $tenantId,
            'reference_type' => 'fixed_asset_purchase',
            'reference_id' => $asset->id,
            'date' => $purchaseDate,
            'description' => 'Pembelian aset: '.$asset->name,
            'status' => 'posted',
            'posted_at' => $now,
            'created_by' => $request->user()?->id,
            'created_at' => $now,
        ]);

        JournalItem::query()->insert([
            $this->journalItem($journalEntryId, $tenantId, $fixedAssetAccountId, $amount, 0),
            $this->journalItem($journalEntryId, $tenantId, $creditAccountId, 0, $amount),
        ]);

        $cashTransactionId = null;
        if (in_array($paymentMethod, ['cash', 'bank'], true)) {
            $balanceAfter = (float) $balanceBefore - $amount;
            $cashTransactionId = (string) Str::uuid();

            CashTransaction::query()->create([
                'id' => $cashTransactionId,
                'tenant_id' => $tenantId,
                'direction' => 'cash-out',
                'transaction_date' => $purchaseDate,
                'account_id' => $creditAccountId,
                'counter_account_id' => $fixedAssetAccountId,
                'category' => 'asset_purchase',
                'amount' => $amount,
                'description' => 'Pembelian aset: '.$asset->name,
                'reference' => $asset->asset_code,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'journal_entry_id' => $journalEntryId,
                'created_by' => $request->user()?->id,
                'created_at' => $now,
            ]);
        }

        return [
            'journal_entry_id' => $journalEntryId,
            'cash_transaction_id' => $cashTransactionId,
        ];
    }

    private function postDepreciationJournal(string $tenantId, FixedAsset $asset, string $periodDate, float $amount, Request $request): string
    {
        $expenseAccountId = $asset->depreciation_expense_account_id ?: $this->accountIdByCode($tenantId, '6201');
        $contraAssetAccountId = $asset->accumulated_depreciation_account_id ?: $this->accountIdByCode($tenantId, '1502');

        if (! $expenseAccountId || ! $contraAssetAccountId) {
            abort(422, 'Akun beban depresiasi dan akumulasi depresiasi wajib tersedia.');
        }

        $period = CarbonImmutable::parse($periodDate)->startOfMonth()->toDateString();
        $now = CarbonImmutable::now('UTC');
        $journalEntryId = (string) Str::uuid();

        JournalEntry::query()->create([
            'id' => $journalEntryId,
            'tenant_id' => $tenantId,
            'reference_type' => 'fixed_asset_depreciation',
            'reference_id' => $asset->id,
            'date' => $period,
            'description' => 'Depresiasi aset: '.$asset->name,
            'status' => 'posted',
            'posted_at' => $now,
            'created_by' => $request->user()?->id,
            'created_at' => $now,
        ]);

        JournalItem::query()->insert([
            $this->journalItem($journalEntryId, $tenantId, $expenseAccountId, round($amount, 2), 0),
            $this->journalItem($journalEntryId, $tenantId, $contraAssetAccountId, 0, round($amount, 2)),
        ]);

        return $journalEntryId;
    }

    private function postPayablePaymentJournal(string $tenantId, FixedAsset $asset, string $paymentAccountId, string $paymentDate, float $amount, Request $request): array
    {
        $defaults = $this->accountingDefaults($tenantId, $request->user()?->id);
        $payableAccountId = $defaults['payable_account_id'] ?? null;

        if (! $payableAccountId) {
            abort(422, 'Akun hutang usaha wajib tersedia.');
        }

        $allowedPaymentAccounts = array_filter([$defaults['cash_account_id'] ?? null, $defaults['bank_account_id'] ?? null]);
        if (! in_array($paymentAccountId, $allowedPaymentAccounts, true)) {
            abort(422, 'Pembayaran hutang aset harus memakai akun kas atau bank.');
        }

        $paymentDate = CarbonImmutable::parse($paymentDate)->toDateString();
        $balanceBefore = $this->accountBalance($tenantId, $paymentAccountId, $paymentDate);
        if ($balanceBefore < $amount) {
            abort(422, 'Saldo kas/bank tidak mencukupi untuk pembayaran hutang aset ini.');
        }

        $now = CarbonImmutable::now('UTC');
        $journalEntryId = (string) Str::uuid();

        JournalEntry::query()->create([
            'id' => $journalEntryId,
            'tenant_id' => $tenantId,
            'reference_type' => 'fixed_asset_payable_payment',
            'reference_id' => $asset->id,
            'date' => $paymentDate,
            'description' => 'Pembayaran hutang aset: '.$asset->name,
            'status' => 'posted',
            'posted_at' => $now,
            'created_by' => $request->user()?->id,
            'created_at' => $now,
        ]);

        JournalItem::query()->insert([
            $this->journalItem($journalEntryId, $tenantId, $payableAccountId, $amount, 0),
            $this->journalItem($journalEntryId, $tenantId, $paymentAccountId, 0, $amount),
        ]);

        $cashTransactionId = (string) Str::uuid();
        CashTransaction::query()->create([
            'id' => $cashTransactionId,
            'tenant_id' => $tenantId,
            'direction' => 'cash-out',
            'transaction_date' => $paymentDate,
            'account_id' => $paymentAccountId,
            'counter_account_id' => $payableAccountId,
            'category' => 'supplier_payment',
            'amount' => $amount,
            'description' => 'Pembayaran hutang aset: '.$asset->name,
            'reference' => $asset->asset_code,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceBefore - $amount,
            'journal_entry_id' => $journalEntryId,
            'created_by' => $request->user()?->id,
            'created_at' => $now,
        ]);

        return [
            'journal_entry_id' => $journalEntryId,
            'cash_transaction_id' => $cashTransactionId,
        ];
    }

    private function journalItem(string $journalEntryId, string $tenantId, string $accountId, float $debit, float $credit): array
    {
        return [
            'id' => (string) Str::uuid(),
            'journal_entry_id' => $journalEntryId,
            'tenant_id' => $tenantId,
            'account_id' => $accountId,
            'debit' => round($debit, 2),
            'credit' => round($credit, 2),
            'is_reversal' => false,
        ];
    }

    private function accountBalance(string $tenantId, string $accountId, string $asOfDate): float
    {
        $row = DB::table('journal_items as ji')
            ->join('journal_entries as je', function ($join): void {
                $join->on('je.id', '=', 'ji.journal_entry_id')
                    ->on('je.tenant_id', '=', 'ji.tenant_id');
            })
            ->where('ji.tenant_id', $tenantId)
            ->where('ji.account_id', $accountId)
            ->where('je.status', 'posted')
            ->where('je.date', '<=', $asOfDate)
            ->selectRaw('coalesce(sum(ji.debit - ji.credit), 0) as balance')
            ->first();

        return (float) ($row->balance ?? 0);
    }

    private function buildPreview(float $purchasePrice, float $residualValue, int $usefulLifeMonths, string $paymentMethod): array
    {
        $monthlyDepreciation = $usefulLifeMonths > 0
            ? round(max(0, $purchasePrice - $residualValue) / $usefulLifeMonths, 2)
            : 0.0;

        return [
            'payment_method' => $paymentMethod,
            'cash_or_bank_out' => in_array($paymentMethod, ['cash', 'bank'], true) ? round($purchasePrice, 2) : 0.0,
            'payable_increase' => $paymentMethod === 'payable' ? round($purchasePrice, 2) : 0.0,
            'fixed_asset_increase' => round($purchasePrice, 2),
            'monthly_depreciation' => $monthlyDepreciation,
            'annual_depreciation' => round($monthlyDepreciation * 12, 2),
            'book_value_after_12_months' => max($residualValue, round($purchasePrice - ($monthlyDepreciation * 12), 2)),
        ];
    }

    private function normalizePurchaseAmounts(array $payload): array
    {
        $quantity = round(max(0.0001, (float) ($payload['quantity'] ?? 1)), 4);
        $unitPrice = array_key_exists('unit_price', $payload)
            ? (float) $payload['unit_price']
            : (float) ($payload['purchase_price'] ?? 0);
        $purchasePrice = round($quantity * max(0, $unitPrice), 2);

        return [$quantity, round(max(0, $unitPrice), 2), $purchasePrice];
    }

    private function assetFinancialFieldsChanged(FixedAsset $asset, float $quantity, float $unitPrice, float $purchasePrice, float $residualValue, int $usefulLifeMonths, string $method): bool
    {
        return round((float) ($asset->quantity ?? 1), 4) !== round($quantity, 4)
            || round((float) ($asset->unit_price ?? $asset->purchase_price), 2) !== round($unitPrice, 2)
            || round((float) $asset->purchase_price, 2) !== round($purchasePrice, 2)
            || round((float) $asset->residual_value, 2) !== round($residualValue, 2)
            || (int) $asset->useful_life_months !== $usefulLifeMonths
            || (string) $asset->depreciation_method !== $method;
    }

    private function assetPayableRemaining(FixedAsset $asset): float
    {
        if ($asset->payment_method !== 'payable') {
            return 0.0;
        }

        return max(0, round((float) $asset->purchase_price - (float) $asset->liability_paid_amount, 2));
    }
}
