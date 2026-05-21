<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccountMapping;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\PaymentSettlement;
use App\Services\LockPeriodService;
use App\Services\TenantDefaultAccountingSetupService;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PaymentSettlementController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json(['message' => 'Tenant tidak ditemukan.'], 400);
        }

        app(TenantDefaultAccountingSetupService::class)->ensure($tenantId, $request->user()?->id);

        $salesDate = CarbonImmutable::parse($request->query('sales_date', CarbonImmutable::now('Asia/Jakarta')->toDateString()))->toDateString();
        $method = strtolower((string) $request->query('payment_method', 'qris'));
        $method = in_array($method, ['qris', 'bank'], true) ? $method : 'qris';

        return response()->json([
            'summary' => $this->summary($tenantId, $salesDate, $method),
            'settlements' => $this->settlements($tenantId),
            'accounts' => $this->accounts($tenantId),
            'defaults' => $this->defaults($tenantId),
        ]);
    }

    public function pending(Request $request)
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json(['message' => 'Tenant tidak ditemukan.'], 400);
        }

        app(TenantDefaultAccountingSetupService::class)->ensure($tenantId, $request->user()?->id);

        $timezone = config('app.timezone', 'Asia/Jakarta');
        $cutoffDate = CarbonImmutable::now($timezone)->subDay()->toDateString();
        $fromDate = CarbonImmutable::parse($cutoffDate, $timezone)
            ->subDays(max(1, min(30, (int) $request->query('days', 14))) - 1)
            ->toDateString();

        $expectedRows = DB::table('payments')
            ->join('sales', function ($join): void {
                $join->on('sales.id', '=', 'payments.sale_id')
                    ->on('sales.tenant_id', '=', 'payments.tenant_id');
            })
            ->where('payments.tenant_id', $tenantId)
            ->whereIn('payments.method', ['qris', 'bank'])
            ->where('sales.status', 'completed')
            ->whereDate('sales.transaction_date', '>=', $fromDate)
            ->whereDate('sales.transaction_date', '<=', $cutoffDate)
            ->selectRaw('DATE(sales.transaction_date) as sales_date, payments.method as payment_method, SUM(payments.amount) as expected_amount')
            ->groupBy(DB::raw('DATE(sales.transaction_date)'), 'payments.method')
            ->orderBy('sales_date')
            ->get();

        $settledRows = PaymentSettlement::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('payment_method', ['qris', 'bank'])
            ->whereBetween('sales_date', [$fromDate, $cutoffDate])
            ->selectRaw('sales_date, payment_method, SUM(expected_amount) as settled_amount')
            ->groupBy('sales_date', 'payment_method')
            ->get()
            ->keyBy(fn ($row) => $row->sales_date.'|'.$row->payment_method);

        $items = $expectedRows
            ->map(function ($row) use ($settledRows) {
                $expected = round((float) $row->expected_amount, 2);
                $settled = round((float) ($settledRows->get($row->sales_date.'|'.$row->payment_method)?->settled_amount ?? 0), 2);
                $remaining = round(max(0, $expected - $settled), 2);

                return [
                    'sales_date' => (string) $row->sales_date,
                    'payment_method' => (string) $row->payment_method,
                    'expected_amount' => $expected,
                    'settled_amount' => $settled,
                    'remaining_amount' => $remaining,
                    'settlement_url' => '/backoffice/finance/payment-settlements?sales_date='.$row->sales_date.'&payment_method='.$row->payment_method,
                ];
            })
            ->filter(fn (array $row) => $row['remaining_amount'] > 0.01)
            ->values();

        return response()->json([
            'has_pending' => $items->isNotEmpty(),
            'count' => $items->count(),
            'total_remaining' => round((float) $items->sum('remaining_amount'), 2),
            'items' => $items->all(),
        ]);
    }

    public function store(Request $request)
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json(['message' => 'Tenant tidak ditemukan.'], 400);
        }

        app(TenantDefaultAccountingSetupService::class)->ensure($tenantId, $request->user()?->id);

        $payload = $request->validate([
            'sales_date' => ['required', 'date'],
            'settlement_date' => ['required', 'date'],
            'payment_method' => ['nullable', Rule::in(['qris', 'bank'])],
            'settlement_account_id' => ['nullable', 'uuid', Rule::exists('chart_of_accounts', 'id')->where('tenant_id', $tenantId)->where('type', 'asset')],
            'bank_account_id' => ['required', 'uuid', Rule::exists('chart_of_accounts', 'id')->where('tenant_id', $tenantId)->where('type', 'asset')],
            'fee_account_id' => ['nullable', 'uuid', Rule::exists('chart_of_accounts', 'id')->where('tenant_id', $tenantId)->where('type', 'expense')],
            'received_amount' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $method = $payload['payment_method'] ?? 'qris';
        $salesDate = CarbonImmutable::parse($payload['sales_date'])->toDateString();
        $settlementDate = CarbonImmutable::parse($payload['settlement_date'])->toDateString();
        (new LockPeriodService())->ensureNotLocked(CarbonImmutable::parse($settlementDate), $tenantId);

        $summary = $this->summary($tenantId, $salesDate, $method);
        $expected = round((float) $summary['expected_amount'], 2);
        $received = round((float) $payload['received_amount'], 2);

        if ($summary['already_settled']) {
            return response()->json([
                'message' => 'Tanggal penjualan ini sudah pernah disettlement. Hapus/koreksi jurnal manual bila perlu revisi.',
            ], 422);
        }

        if ($expected <= 0) {
            return response()->json([
                'message' => 'Tidak ada pembayaran '.strtoupper($method).' pada tanggal penjualan ini.',
            ], 422);
        }

        if ($received > $expected) {
            return response()->json([
                'message' => 'Uang real masuk tidak boleh lebih besar dari total pembayaran POS untuk versi settlement sederhana ini.',
            ], 422);
        }

        $defaults = $this->defaults($tenantId);
        $settlementAccountId = (string) ($payload['settlement_account_id'] ?? $defaults['settlement_account_id']);
        $feeAccountId = (string) ($payload['fee_account_id'] ?? $defaults['fee_account_id']);
        $fee = round($expected - $received, 2);

        $settlement = DB::transaction(function () use ($tenantId, $payload, $salesDate, $settlementDate, $method, $expected, $received, $fee, $settlementAccountId, $feeAccountId, $request) {
            $settlementId = (string) Str::uuid();
            $journalEntryId = (string) Str::uuid();
            $now = CarbonImmutable::now('UTC');

            JournalEntry::query()->create([
                'id' => $journalEntryId,
                'tenant_id' => $tenantId,
                'reference_type' => 'payment_settlement',
                'reference_id' => $settlementId,
                'date' => $settlementDate,
                'description' => 'Settlement '.strtoupper($method).' '.$salesDate,
                'status' => 'posted',
                'posted_at' => $now,
                'created_by' => $request->user()?->id,
                'created_at' => $now,
            ]);

            PaymentSettlement::query()->create([
                'id' => $settlementId,
                'tenant_id' => $tenantId,
                'sales_date' => $salesDate,
                'settlement_date' => $settlementDate,
                'payment_method' => $method,
                'settlement_account_id' => $settlementAccountId,
                'bank_account_id' => $payload['bank_account_id'],
                'fee_account_id' => $feeAccountId,
                'journal_entry_id' => $journalEntryId,
                'expected_amount' => $expected,
                'received_amount' => $received,
                'admin_fee_amount' => $fee,
                'notes' => $payload['notes'] ?? null,
                'created_by' => $request->user()?->id,
                'created_at' => $now,
            ]);

            $items = [
                $this->journalItem($journalEntryId, $tenantId, (string) $payload['bank_account_id'], $received, 0),
                $this->journalItem($journalEntryId, $tenantId, $settlementAccountId, 0, $expected),
            ];

            if ($fee > 0) {
                $items[] = $this->journalItem($journalEntryId, $tenantId, $feeAccountId, $fee, 0);
            }

            JournalItem::query()->insert($items);

            return PaymentSettlement::query()->where('tenant_id', $tenantId)->findOrFail($settlementId);
        });

        return response()->json([
            'settlement' => $this->mapSettlement($settlement),
            'summary' => $this->summary($tenantId, $salesDate, $method),
        ], 201);
    }

    private function summary(string $tenantId, string $salesDate, string $method): array
    {
        $expected = (float) DB::table('payments')
            ->join('sales', function ($join): void {
                $join->on('sales.id', '=', 'payments.sale_id')
                    ->on('sales.tenant_id', '=', 'payments.tenant_id');
            })
            ->where('payments.tenant_id', $tenantId)
            ->where('payments.method', $method)
            ->whereDate('sales.transaction_date', $salesDate)
            ->where('sales.status', 'completed')
            ->sum('payments.amount');

        $settled = (float) PaymentSettlement::query()
            ->where('tenant_id', $tenantId)
            ->where('sales_date', $salesDate)
            ->where('payment_method', $method)
            ->sum('expected_amount');

        return [
            'sales_date' => $salesDate,
            'payment_method' => $method,
            'expected_amount' => round($expected, 2),
            'settled_amount' => round($settled, 2),
            'remaining_amount' => round(max(0, $expected - $settled), 2),
            'already_settled' => $settled > 0,
        ];
    }

    private function settlements(string $tenantId): array
    {
        return PaymentSettlement::query()
            ->where('tenant_id', $tenantId)
            ->orderByDesc('settlement_date')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn (PaymentSettlement $settlement) => $this->mapSettlement($settlement))
            ->values()
            ->all();
    }

    private function accounts(string $tenantId): array
    {
        return ChartOfAccount::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('type', ['asset', 'expense'])
            ->where(function ($query): void {
                $query->where('code', 'like', '11%')
                    ->orWhere('code', 'like', '63%')
                    ->orWhere('name', 'like', '%bank%')
                    ->orWhere('name', 'like', '%qris%')
                    ->orWhere('name', 'like', '%admin%');
            })
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type'])
            ->map(fn (ChartOfAccount $account) => $account->only(['id', 'code', 'name', 'type']))
            ->values()
            ->all();
    }

    private function defaults(string $tenantId): array
    {
        $mapping = AccountMapping::query()->where('tenant_id', $tenantId)->first();

        return [
            'settlement_account_id' => $mapping?->bank_account_id,
            'bank_account_id' => $mapping?->bank_account_id,
            'fee_account_id' => ChartOfAccount::query()->where('tenant_id', $tenantId)->where('code', '6301')->value('id'),
        ];
    }

    private function mapSettlement(PaymentSettlement $settlement): array
    {
        return [
            'id' => $settlement->id,
            'sales_date' => $settlement->sales_date,
            'settlement_date' => $settlement->settlement_date,
            'payment_method' => $settlement->payment_method,
            'settlement_account_id' => $settlement->settlement_account_id,
            'bank_account_id' => $settlement->bank_account_id,
            'fee_account_id' => $settlement->fee_account_id,
            'journal_entry_id' => $settlement->journal_entry_id,
            'expected_amount' => (float) $settlement->expected_amount,
            'received_amount' => (float) $settlement->received_amount,
            'admin_fee_amount' => (float) $settlement->admin_fee_amount,
            'notes' => $settlement->notes,
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
}
