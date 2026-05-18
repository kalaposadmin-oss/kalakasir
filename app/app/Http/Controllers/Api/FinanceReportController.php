<?php

namespace App\Http\Controllers\Api;

use App\Models\AccountMapping;
use App\Exceptions\MissingSalesCostAggregateException;
use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Services\FinanceGlReportService;
use App\Services\SalesCostReadinessService;
use App\Services\SubscriptionService;
use App\Services\SystemHealthEventService;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Support\TenantContext;

class FinanceReportController extends Controller
{
    public function index(Request $request)
    {
        $startedAt = microtime(true);
        $tenantId = TenantContext::id();
        $tenant = TenantContext::get();
        if (! $tenantId) {
            return response()->json([
                'message' => 'Tenant tidak ditemukan.',
            ], 400);
        }
        $preset = $request->query('preset', 'thisMonth');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $month = $request->query('month');

        if ($month && ! $startDate && ! $endDate) {
            $startDate = $month . '-01';
            $preset = 'custom';
        }

        [$startDateLocal, $endDateLocal, $rangeMeta] = $this->resolveAccountingDateRange($preset, $startDate, $endDate);

        try {
            app(SalesCostReadinessService::class)->assertNoMissingCompletedSalesForBusinessDateRange(
                $tenantId,
                $startDateLocal,
                $endDateLocal,
                'finance-summary'
            );

            $subscription = app(SubscriptionService::class);
            $canViewBalanceSheet = $tenant ? $subscription->canAccessFeature($tenant, 'finance.balance_sheet') : false;

            $payload = app(FinanceGlReportService::class)->build(
                $tenantId,
                $startDateLocal,
                $endDateLocal,
                $canViewBalanceSheet
            );

            $payload['period'] = $rangeMeta;

            $response = response()->json($payload);
        } catch (MissingSalesCostAggregateException $exception) {
            app(SystemHealthEventService::class)->recordOrBump('strict_reader_blocked', [
                'tenant_id' => $tenantId,
                'entity_type' => 'report_endpoint',
                'entity_id' => 'finance-summary',
                'summary' => 'Strict reader blocked finance summary because aggregate COGS is invalid.',
                'payload' => [
                    'endpoint' => 'finance-summary',
                    'query' => $request->query(),
                    'details' => $exception->details(),
                ],
                'source' => 'finance-summary-controller',
                'dedupe_key' => 'strict_reader_blocked:'.$tenantId.':finance-summary:'.sha1(json_encode($request->query(), JSON_UNESCAPED_SLASHES)),
            ]);

            $response = response()->json([
                'code' => 'SALES_COST_AGGREGATE_REQUIRED',
                'message' => $exception->getMessage(),
                'details' => $exception->details(),
            ], 409);
        }

        $this->recordSlowQueryIfNeeded($tenantId, 'finance-summary', $startedAt, [
            'query' => $request->query(),
            'source' => 'journal_gl',
        ]);

        return $response;
    }

    public function cashFlow(Request $request)
    {
        $startedAt = microtime(true);
        $tenantId = TenantContext::id();

        if (! $tenantId) {
            return response()->json([
                'message' => 'Tenant tidak ditemukan.',
            ], 400);
        }

        $preset = (string) $request->query('preset', 'thisMonth');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $month = $request->query('month');

        if ($month && ! $startDate && ! $endDate) {
            $startDate = $month.'-01';
            $preset = 'custom';
        }

        [$startDateLocal, $endDateLocal, $rangeMeta] = $this->resolveAccountingDateRange($preset, $startDate, $endDate);

        $mapping = AccountMapping::query()
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $mapping || ! $mapping->cash_account_id || ! $mapping->bank_account_id) {
            return response()->json([
                'code' => 'ACCOUNT_MAPPING_REQUIRED',
                'message' => 'Mapping akun kas dan bank wajib disetel sebelum membuka laporan arus kas.',
                'details' => [
                    'required_keys' => ['cash_account_id', 'bank_account_id'],
                ],
            ], 422);
        }

        $cashAccountIds = array_values(array_filter([
            (string) $mapping->cash_account_id,
            (string) $mapping->bank_account_id,
        ]));

        $cashLines = DB::table('journal_items as ji')
            ->join('journal_entries as je', function ($join): void {
                $join->on('je.id', '=', 'ji.journal_entry_id')
                    ->on('je.tenant_id', '=', 'ji.tenant_id');
            })
            ->join('chart_of_accounts as coa', function ($join): void {
                $join->on('coa.id', '=', 'ji.account_id')
                    ->on('coa.tenant_id', '=', 'ji.tenant_id');
            })
            ->where('ji.tenant_id', $tenantId)
            ->where('je.status', 'posted')
            ->whereBetween('je.date', [$startDateLocal, $endDateLocal])
            ->whereIn('ji.account_id', $cashAccountIds)
            ->orderBy('je.date')
            ->orderBy('je.id')
            ->get([
                'je.id as journal_entry_id',
                'je.date',
                'je.description',
                'ji.account_id',
                'coa.code as account_code',
                'coa.name as account_name',
                'ji.debit',
                'ji.credit',
            ]);

        $entryIds = $cashLines->pluck('journal_entry_id')->unique()->values();
        $counterpartByEntry = collect();
        if ($entryIds->isNotEmpty()) {
            $counterpartByEntry = DB::table('journal_items as ji')
                ->join('chart_of_accounts as coa', function ($join): void {
                    $join->on('coa.id', '=', 'ji.account_id')
                        ->on('coa.tenant_id', '=', 'ji.tenant_id');
                })
                ->where('ji.tenant_id', $tenantId)
                ->whereIn('ji.journal_entry_id', $entryIds->all())
                ->whereNotIn('ji.account_id', $cashAccountIds)
                ->groupBy('ji.journal_entry_id', 'coa.type')
                ->get([
                    'ji.journal_entry_id',
                    'coa.type',
                    DB::raw('SUM(ABS(ji.debit - ji.credit)) as absolute_movement'),
                ])
                ->groupBy('journal_entry_id');
        }

        $operating = [];
        $investing = [];
        $financing = [];

        foreach ($cashLines as $line) {
            $amount = Decimal::money((float) $line->debit - (float) $line->credit);
            if (Decimal::compare($amount, 0) === 0) {
                continue;
            }

            $section = $this->classifyCashFlowSection($counterpartByEntry->get($line->journal_entry_id, collect()));
            $row = [
                'journal_entry_id' => (string) $line->journal_entry_id,
                'date' => (string) $line->date,
                'description' => (string) ($line->description ?? 'Jurnal kas/bank'),
                'account_id' => (string) $line->account_id,
                'account_label' => trim(((string) ($line->account_code ?? '')) !== ''
                    ? ((string) $line->account_code).' - '.((string) ($line->account_name ?? '-'))
                    : (string) ($line->account_name ?? '-')),
                'amount' => Decimal::float($amount, Decimal::MONEY_SCALE),
            ];

            if ($section === 'investing') {
                $investing[] = $row;
            } elseif ($section === 'financing') {
                $financing[] = $row;
            } else {
                $operating[] = $row;
            }
        }

        $openingCash = $this->cashBalanceUntil($tenantId, $cashAccountIds, CarbonImmutable::parse($startDateLocal, 'UTC')->subDay()->toDateString());

        $netOperating = collect($operating)->reduce(
            fn (string $carry, array $row) => Decimal::add($carry, Decimal::money($row['amount']), Decimal::MONEY_SCALE),
            Decimal::money(0)
        );
        $netInvesting = collect($investing)->reduce(
            fn (string $carry, array $row) => Decimal::add($carry, Decimal::money($row['amount']), Decimal::MONEY_SCALE),
            Decimal::money(0)
        );
        $netFinancing = collect($financing)->reduce(
            fn (string $carry, array $row) => Decimal::add($carry, Decimal::money($row['amount']), Decimal::MONEY_SCALE),
            Decimal::money(0)
        );
        $netChange = Decimal::add(
            Decimal::add($netOperating, $netInvesting, Decimal::MONEY_SCALE),
            $netFinancing,
            Decimal::MONEY_SCALE
        );
        $closingCash = Decimal::add($openingCash, $netChange, Decimal::MONEY_SCALE);

        $response = response()->json([
            'period' => array_merge($rangeMeta, [
                'basis' => 'journal_entries.date',
            ]),
            'summary' => [
                'opening_cash' => Decimal::float($openingCash, Decimal::MONEY_SCALE),
                'net_operating' => Decimal::float($netOperating, Decimal::MONEY_SCALE),
                'net_investing' => Decimal::float($netInvesting, Decimal::MONEY_SCALE),
                'net_financing' => Decimal::float($netFinancing, Decimal::MONEY_SCALE),
                'net_change' => Decimal::float($netChange, Decimal::MONEY_SCALE),
                'closing_cash' => Decimal::float($closingCash, Decimal::MONEY_SCALE),
            ],
            'operating' => $operating,
            'investing' => $investing,
            'financing' => $financing,
            'meta' => [
                'source' => 'journal_gl_cash_flow',
                'cash_account_ids' => $cashAccountIds,
            ],
        ]);

        $this->recordSlowQueryIfNeeded($tenantId, 'finance-cash-flow', $startedAt, [
            'query' => $request->query(),
            'source' => 'journal_gl_cash_flow',
        ]);

        return $response;
    }

    public function cogsBreakdown(Request $request)
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json([
                'message' => 'Tenant tidak ditemukan.',
            ], 400);
        }

        $preset = (string) $request->query('preset', 'thisMonth');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $month = $request->query('month');

        if ($month && ! $startDate && ! $endDate) {
            $startDate = $month . '-01';
            $preset = 'custom';
        }

        [$startDateLocal, $endDateLocal, $rangeMeta] = $this->resolveAccountingDateRange($preset, $startDate, $endDate);
        app(SalesCostReadinessService::class)->assertNoMissingCompletedSalesForBusinessDateRange(
            $tenantId,
            $startDateLocal,
            $endDateLocal,
            'finance-summary-cogs-breakdown'
        );

        $limit = (int) $request->query('limit', 200);
        $limit = max(20, min($limit, 500));

        $rows = DB::table('sale_cost_components as scc')
            ->join('sales', function ($join): void {
                $join->on('sales.id', '=', 'scc.sale_id')
                    ->on('sales.tenant_id', '=', 'scc.tenant_id');
            })
            ->join('products', function ($join): void {
                $join->on('products.id', '=', 'scc.product_id')
                    ->on('products.tenant_id', '=', 'scc.tenant_id');
            })
            ->leftJoin('product_variants', function ($join): void {
                $join->on('product_variants.id', '=', 'scc.product_variant_id')
                    ->on('product_variants.tenant_id', '=', 'scc.tenant_id');
            })
            ->where('scc.tenant_id', $tenantId)
            ->where('sales.status', 'completed')
            ->whereRaw('DATE(COALESCE(sales.transaction_date, sales.created_at)) >= ?', [$startDateLocal])
            ->whereRaw('DATE(COALESCE(sales.transaction_date, sales.created_at)) <= ?', [$endDateLocal])
            ->groupBy(
                'scc.sale_id',
                DB::raw('DATE(COALESCE(sales.transaction_date, sales.created_at))'),
                'scc.product_id',
                'products.name',
                'scc.product_variant_id',
                'product_variants.name'
            )
            ->orderByRaw('DATE(COALESCE(sales.transaction_date, sales.created_at)) DESC')
            ->orderBy('scc.sale_id')
            ->select([
                'scc.sale_id',
                DB::raw('DATE(COALESCE(sales.transaction_date, sales.created_at)) as business_date'),
                'scc.product_id',
                'products.name as product_name',
                'scc.product_variant_id',
                'product_variants.name as variant_name',
                DB::raw('SUM(scc.qty) as total_qty'),
                DB::raw('SUM(scc.total_cost) as total_cost'),
                DB::raw('COUNT(*) as component_count'),
            ])
            ->limit($limit)
            ->get();

        $grossCogs = Decimal::money($rows->sum('total_cost'));
        $refundRows = DB::table('refunds')
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereRaw('DATE(COALESCE(transaction_date, created_at)) >= ?', [$startDateLocal])
            ->whereRaw('DATE(COALESCE(transaction_date, created_at)) <= ?', [$endDateLocal])
            ->where('refunded_cost_total', '>', 0)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get([
                'id',
                'sale_id',
                DB::raw('DATE(COALESCE(transaction_date, created_at)) as business_date'),
                'refunded_cost_total',
            ]);

        $refundedCogs = Decimal::money($refundRows->sum('refunded_cost_total'));
        $netCogs = Decimal::subtract($grossCogs, $refundedCogs, Decimal::MONEY_SCALE);

        $productSummary = $rows
            ->groupBy(function ($row) {
                return implode('|', [
                    (string) $row->product_id,
                    (string) ($row->product_variant_id ?? ''),
                ]);
            })
            ->map(function ($group) {
                $first = $group->first();
                $qty = Decimal::money($group->sum('total_qty'));
                $totalCost = Decimal::money($group->sum('total_cost'));

                return [
                    'product_id' => (string) ($first->product_id ?? ''),
                    'product_name' => (string) ($first->product_name ?? 'Produk'),
                    'product_variant_id' => $first->product_variant_id ? (string) $first->product_variant_id : null,
                    'variant_name' => $first->variant_name ? (string) $first->variant_name : null,
                    'qty' => Decimal::float($qty, Decimal::MONEY_SCALE),
                    'cogs_total' => Decimal::float($totalCost, Decimal::MONEY_SCALE),
                ];
            })
            ->sortByDesc('cogs_total')
            ->values()
            ->take(20)
            ->all();

        return response()->json([
            'period' => array_merge($rangeMeta, [
                'basis' => 'sales.transaction_date',
            ]),
            'summary' => [
                'gross_cogs' => Decimal::float($grossCogs, Decimal::MONEY_SCALE),
                'refunded_cogs' => Decimal::float($refundedCogs, Decimal::MONEY_SCALE),
                'net_cogs' => Decimal::float($netCogs, Decimal::MONEY_SCALE),
                'transaction_count' => $rows->pluck('sale_id')->unique()->count(),
                'detail_row_count' => $rows->count(),
                'component_count' => (int) $rows->sum('component_count'),
                'returned_row_count' => $rows->count(),
                'limit' => $limit,
            ],
            'details' => $rows->map(function ($row) {
                $qty = Decimal::money($row->total_qty ?? 0);
                $totalCost = Decimal::money($row->total_cost ?? 0);
                $unitCost = Decimal::compare($qty, 0) === 0
                    ? Decimal::money(0)
                    : Decimal::divide($totalCost, $qty, Decimal::MONEY_SCALE);

                return [
                    'sale_id' => (string) $row->sale_id,
                    'business_date' => (string) $row->business_date,
                    'product_id' => (string) ($row->product_id ?? ''),
                    'product_name' => (string) ($row->product_name ?? 'Produk'),
                    'product_variant_id' => $row->product_variant_id ? (string) $row->product_variant_id : null,
                    'variant_name' => $row->variant_name ? (string) $row->variant_name : null,
                    'qty' => Decimal::float($qty, Decimal::MONEY_SCALE),
                    'unit_cost' => Decimal::float($unitCost, Decimal::MONEY_SCALE),
                    'cogs_total' => Decimal::float($totalCost, Decimal::MONEY_SCALE),
                    'component_count' => (int) ($row->component_count ?? 0),
                ];
            })->values()->all(),
            'refund_adjustments' => $refundRows->map(function ($row) {
                return [
                    'refund_id' => (string) $row->id,
                    'sale_id' => (string) ($row->sale_id ?? ''),
                    'business_date' => (string) ($row->business_date ?? ''),
                    'cogs_amount' => Decimal::float(Decimal::money($row->refunded_cost_total ?? 0), Decimal::MONEY_SCALE),
                ];
            })->values()->all(),
            'product_summary' => $productSummary,
        ]);
    }

    public function accountDetail(Request $request)
    {
        $startedAt = microtime(true);
        $tenantId = TenantContext::id();

        if (! $tenantId) {
            return response()->json([
                'message' => 'Tenant tidak ditemukan.',
            ], 400);
        }

        $validated = $request->validate([
            'account_id' => ['required', 'uuid'],
            'preset' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'month' => ['nullable', 'date_format:Y-m'],
            'limit' => ['nullable', 'integer', 'min:10', 'max:200'],
        ]);

        $preset = (string) ($validated['preset'] ?? 'thisMonth');
        $startDate = $validated['start_date'] ?? null;
        $endDate = $validated['end_date'] ?? null;
        $month = $validated['month'] ?? null;

        if ($month && ! $startDate && ! $endDate) {
            $startDate = $month.'-01';
            $preset = 'custom';
        }

        [$startDateLocal, $endDateLocal, $rangeMeta] = $this->resolveAccountingDateRange($preset, $startDate, $endDate);

        $account = DB::table('chart_of_accounts')
            ->where('tenant_id', $tenantId)
            ->where('id', (string) $validated['account_id'])
            ->first(['id', 'code', 'name', 'type']);

        if (! $account) {
            return response()->json([
                'message' => 'Akun tidak ditemukan.',
            ], 404);
        }

        $openingTotals = $this->accountAggregateUntil($tenantId, (string) $account->id, $startDateLocal, false);
        $periodTotals = $this->accountAggregateForRange($tenantId, (string) $account->id, $startDateLocal, $endDateLocal);
        $endingTotals = $this->accountAggregateUntil($tenantId, (string) $account->id, $endDateLocal, true);

        $openingBalance = $this->normalizedAmountByType(
            (string) $account->type,
            (float) ($openingTotals->debit_total ?? 0),
            (float) ($openingTotals->credit_total ?? 0)
        );
        $periodMovement = $this->normalizedAmountByType(
            (string) $account->type,
            (float) ($periodTotals->debit_total ?? 0),
            (float) ($periodTotals->credit_total ?? 0)
        );
        $endingBalance = $this->normalizedAmountByType(
            (string) $account->type,
            (float) ($endingTotals->debit_total ?? 0),
            (float) ($endingTotals->credit_total ?? 0)
        );

        $limit = (int) ($validated['limit'] ?? 80);

        $entries = DB::table('journal_items as ji')
            ->join('journal_entries as je', function ($join): void {
                $join->on('je.id', '=', 'ji.journal_entry_id')
                    ->on('je.tenant_id', '=', 'ji.tenant_id');
            })
            ->where('ji.tenant_id', $tenantId)
            ->where('ji.account_id', (string) $account->id)
            ->where('je.status', 'posted')
            ->whereBetween('je.date', [$startDateLocal, $endDateLocal])
            ->orderByDesc('je.date')
            ->orderByDesc('je.id')
            ->limit($limit)
            ->get([
                'je.id as journal_entry_id',
                'je.reference_type',
                'je.reference_id',
                'je.date',
                'je.description',
                'ji.debit',
                'ji.credit',
            ])
            ->map(function ($row) use ($account) {
                $debit = (float) ($row->debit ?? 0);
                $credit = (float) ($row->credit ?? 0);

                return [
                    'journal_entry_id' => (string) $row->journal_entry_id,
                    'reference_type' => (string) ($row->reference_type ?? ''),
                    'reference_id' => (string) ($row->reference_id ?? ''),
                    'date' => (string) $row->date,
                    'description' => trim((string) ($row->description ?? '')) !== ''
                        ? (string) $row->description
                        : 'Jurnal '.$row->journal_entry_id,
                    'debit' => Decimal::float(Decimal::money($debit), Decimal::MONEY_SCALE),
                    'credit' => Decimal::float(Decimal::money($credit), Decimal::MONEY_SCALE),
                    'amount' => Decimal::float(
                        Decimal::money($this->normalizedAmountByType((string) $account->type, $debit, $credit)),
                        Decimal::MONEY_SCALE
                    ),
                ];
            })
            ->values()
            ->all();

        $mapping = AccountMapping::query()
            ->where('tenant_id', $tenantId)
            ->first(['inventory_account_id']);

        $inventoryBreakdown = [];
        $inventoryMeta = null;
        if ((string) ($mapping?->inventory_account_id ?? '') === (string) $account->id) {
            $snapshotDate = CarbonImmutable::now('Asia/Jakarta')->toDateString();
            $inventoryBreakdown = DB::table('inventory_summaries as summary')
                ->join('products', function ($join): void {
                    $join->on('products.id', '=', 'summary.product_id')
                        ->on('products.tenant_id', '=', 'summary.tenant_id');
                })
                ->where('summary.tenant_id', $tenantId)
                ->groupBy('summary.product_id', 'products.name', 'products.sku')
                ->orderByRaw('SUM(summary.current_value) DESC')
                ->limit(60)
                ->get([
                    'summary.product_id',
                    'products.name as product_name',
                    'products.sku as product_sku',
                    DB::raw('SUM(summary.current_qty) as current_qty'),
                    DB::raw('SUM(summary.current_value) as current_value'),
                ])
                ->map(function ($row) {
                    return [
                        'product_id' => (string) $row->product_id,
                        'product_name' => (string) ($row->product_name ?? '-'),
                        'product_sku' => (string) ($row->product_sku ?? ''),
                        'current_qty' => (float) ($row->current_qty ?? 0),
                        'current_value' => Decimal::float(Decimal::money((float) ($row->current_value ?? 0)), Decimal::MONEY_SCALE),
                    ];
                })
                ->values()
                ->all();

            $inventoryMeta = [
                'snapshot_date' => $snapshotDate,
                'product_count' => count($inventoryBreakdown),
                'note' => $endDateLocal === $snapshotDate
                    ? 'Rincian barang mengikuti nilai stok aktif per hari ini.'
                    : 'Rincian barang mengikuti snapshot stok aktif saat ini, sedangkan saldo akun tetap mengikuti periode laporan.',
            ];
        }

        $reportBasis = in_array((string) $account->type, ['asset', 'liability', 'equity'], true)
            ? 'balance_as_of_end_date'
            : 'period_activity';

        $response = response()->json([
            'period' => $rangeMeta,
            'account' => [
                'id' => (string) $account->id,
                'code' => (string) ($account->code ?? ''),
                'name' => (string) ($account->name ?? ''),
                'label' => trim(((string) ($account->code ?? '')) !== ''
                    ? ((string) $account->code).' - '.((string) ($account->name ?? ''))
                    : (string) ($account->name ?? '')),
                'type' => (string) ($account->type ?? ''),
            ],
            'summary' => [
                'opening_balance' => Decimal::float(Decimal::money($openingBalance), Decimal::MONEY_SCALE),
                'period_debit' => Decimal::float(Decimal::money((float) ($periodTotals->debit_total ?? 0)), Decimal::MONEY_SCALE),
                'period_credit' => Decimal::float(Decimal::money((float) ($periodTotals->credit_total ?? 0)), Decimal::MONEY_SCALE),
                'period_movement' => Decimal::float(Decimal::money($periodMovement), Decimal::MONEY_SCALE),
                'ending_balance' => Decimal::float(Decimal::money($endingBalance), Decimal::MONEY_SCALE),
                'report_amount' => $reportBasis === 'balance_as_of_end_date'
                    ? Decimal::float(Decimal::money($endingBalance), Decimal::MONEY_SCALE)
                    : Decimal::float(Decimal::money($periodMovement), Decimal::MONEY_SCALE),
                'report_basis' => $reportBasis,
                'entry_count' => count($entries),
            ],
            'entries' => $entries,
            'inventory_breakdown' => $inventoryBreakdown,
            'inventory_meta' => $inventoryMeta,
        ]);

        $this->recordSlowQueryIfNeeded($tenantId, 'finance-summary-account-detail', $startedAt, [
            'query' => $request->query(),
            'account_id' => (string) $account->id,
            'account_code' => (string) ($account->code ?? ''),
            'source' => 'journal_gl_account_detail',
        ]);

        return $response;
    }

    public function operational(Request $request)
    {
        $startedAt = microtime(true);
        $tenantId = TenantContext::id();
        $tenant = TenantContext::get();
        if (! $tenantId) {
            return response()->json([
                'message' => 'Tenant tidak ditemukan.',
            ], 400);
        }
        $preset = $request->query('preset', 'thisMonth');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $month = $request->query('month');

        if ($month && ! $startDate && ! $endDate) {
            $startDate = $month . '-01';
            $preset = 'custom';
        }

        [$startUtc, $endExclusiveUtc, $rangeMeta] = $this->resolveUtcRange($preset, $startDate, $endDate);

        try {
            $readiness = app(SalesCostReadinessService::class);
            $readiness->assertNoMissingCompletedSalesForCreatedAtRange(
                $tenantId,
                $startUtc->toDateTimeString(),
                $endExclusiveUtc->toDateTimeString(),
                'finance-summary'
            );

            $saleAggregateCogs = $readiness->saleAggregateTotalsSubquery($tenantId);

            $sales = Sale::query()
                ->joinSub($saleAggregateCogs, 'sale_aggregate_cogs', function ($join) {
                    $join->on('sale_aggregate_cogs.sale_id', '=', 'sales.id')
                        ->on('sale_aggregate_cogs.tenant_id', '=', 'sales.tenant_id');
                })
                ->where('sales.tenant_id', $tenantId)
                ->where('sales.created_at', '>=', $startUtc)
                ->where('sales.created_at', '<', $endExclusiveUtc)
                ->where('sales.status', 'completed')
                ->get([
                    'sales.id',
                    'sales.total',
                    DB::raw('sale_aggregate_cogs.aggregated_cogs'),
                ]);

            $grossRevenue = Decimal::money($sales->sum('total'));

            $refunds = DB::table('refunds')
                ->where('tenant_id', $tenantId)
                ->where('status', 'completed')
                ->where('created_at', '>=', $startUtc)
                ->where('created_at', '<', $endExclusiveUtc)
                ->selectRaw('COALESCE(SUM(total), 0) as total')
                ->selectRaw('COALESCE(SUM(refunded_cost_total), 0) as refunded_cost_total')
                ->first();

            $refundRevenue = Decimal::money($refunds?->total ?? 0);
            $refundedCostTotal = Decimal::money($refunds?->refunded_cost_total ?? 0);
            $revenue = Decimal::subtract($grossRevenue, $refundRevenue, Decimal::MONEY_SCALE);
            $cogs = Decimal::money($sales->sum('aggregated_cogs'));
            $netCogs = Decimal::subtract($cogs, $refundedCostTotal, Decimal::MONEY_SCALE);

            $expenses = [
                ['label' => 'HPP (Harga Pokok Penjualan)', 'amount' => Decimal::float($netCogs, Decimal::MONEY_SCALE)],
                ['label' => 'Gaji Karyawan', 'amount' => 0],
                ['label' => 'Sewa dan Utilitas', 'amount' => 0],
                ['label' => 'Pemasaran', 'amount' => 0],
                ['label' => 'Operasional Lainnya', 'amount' => 0],
            ];

            $totalExpenses = collect($expenses)
                ->reduce(fn (string $carry, array $row) => Decimal::add($carry, $row['amount'], Decimal::MONEY_SCALE), Decimal::money(0));

            $netProfit = Decimal::subtract($revenue, $totalExpenses, Decimal::MONEY_SCALE);
            $receivableSnapshot = Schema::hasTable('receivables')
                ? DB::table('receivables')
                    ->where('tenant_id', $tenantId)
                    ->selectRaw("COALESCE(SUM(CASE WHEN status <> 'paid' THEN remaining_amount ELSE 0 END), 0) as active_total")
                    ->selectRaw("COALESCE(SUM(CASE WHEN status = 'paid' THEN total_amount ELSE 0 END), 0) as paid_total")
                    ->selectRaw("COUNT(CASE WHEN status <> 'paid' THEN 1 END) as active_count")
                    ->first()
                : (object) ['active_total' => 0, 'paid_total' => 0, 'active_count' => 0];
            $subscription = app(SubscriptionService::class);
            $canViewBalanceSheet = $tenant ? $subscription->canAccessFeature($tenant, 'finance.balance_sheet') : false;

            $payload = [
                'period' => $rangeMeta,
                'summary' => [
                    'net_profit' => Decimal::float($netProfit, Decimal::MONEY_SCALE),
                    'revenue' => Decimal::float($revenue, Decimal::MONEY_SCALE),
                    'expenses' => Decimal::float($totalExpenses, Decimal::MONEY_SCALE),
                    'tax' => 0,
                ],
                'revenue_breakdown' => [
                    ['label' => 'Penjualan POS', 'amount' => Decimal::float($grossRevenue, Decimal::MONEY_SCALE)],
                    ['label' => 'Retur Penjualan', 'amount' => Decimal::float(Decimal::subtract(0, $refundRevenue, Decimal::MONEY_SCALE), Decimal::MONEY_SCALE)],
                    ['label' => 'Pendapatan Lainnya', 'amount' => 0],
                ],
                'expense_breakdown' => $expenses,
                'receivables' => [
                    'active_total' => Decimal::float(Decimal::money($receivableSnapshot?->active_total ?? 0), Decimal::MONEY_SCALE),
                    'paid_total' => Decimal::float(Decimal::money($receivableSnapshot?->paid_total ?? 0), Decimal::MONEY_SCALE),
                    'active_count' => (int) ($receivableSnapshot?->active_count ?? 0),
                ],
            ];

            if ($canViewBalanceSheet) {
                $payload['balance_sheet'] = [
                    'assets' => [
                        ['label' => 'Kas dan Bank', 'amount' => $revenue],
                        ['label' => 'Piutang Usaha', 'amount' => Decimal::float(Decimal::money($receivableSnapshot?->active_total ?? 0), Decimal::MONEY_SCALE)],
                    ],
                    'liabilities' => [
                        ['label' => 'Hutang Usaha', 'amount' => 0],
                    ],
                    'equity' => [
                        ['label' => 'Modal Bersih', 'amount' => Decimal::float($netProfit, Decimal::MONEY_SCALE)],
                    ],
                ];
            }

            $response = response()->json($payload);
        } catch (MissingSalesCostAggregateException $exception) {
            app(SystemHealthEventService::class)->recordOrBump('strict_reader_blocked', [
                'tenant_id' => $tenantId,
                'entity_type' => 'report_endpoint',
                'entity_id' => 'finance-summary-operational',
                'summary' => 'Strict reader blocked operational finance summary because aggregate COGS is invalid.',
                'payload' => [
                    'endpoint' => 'finance-summary-operational',
                    'query' => $request->query(),
                    'details' => $exception->details(),
                ],
                'source' => 'finance-summary-controller',
                'dedupe_key' => 'strict_reader_blocked:'.$tenantId.':finance-summary-operational:'.sha1(json_encode($request->query(), JSON_UNESCAPED_SLASHES)),
            ]);

            $response = response()->json([
                'code' => 'SALES_COST_AGGREGATE_REQUIRED',
                'message' => $exception->getMessage(),
                'details' => $exception->details(),
            ], 409);
        }

        $this->recordSlowQueryIfNeeded($tenantId, 'finance-summary-operational', $startedAt, [
            'query' => $request->query(),
            'source' => 'sales_refund_snapshot',
        ]);

        return $response;
    }

    private function resolveAccountingDateRange(string $preset, ?string $startDate, ?string $endDate): array
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
            default => [$today->startOfMonth(), $today->endOfMonth()->startOfDay(), 'Bulan Ini'],
        };

        $days = $startLocal->diffInDays($endLocal) + 1;
        $limited = false;
        if ($days > 90) {
            $endLocal = $startLocal->addDays(89);
            $limited = true;
        }

        return [
            $startLocal->toDateString(),
            $endLocal->toDateString(),
            [
                'preset' => $preset,
                'start_date' => $startLocal->toDateString(),
                'end_date' => $endLocal->toDateString(),
                'label' => $label,
                'timezone' => 'Asia/Jakarta',
                'basis' => 'journal_entries.date',
                'limited' => $limited,
                'limit_days' => 90,
            ],
        ];
    }

    private function resolveUtcRange(string $preset, ?string $startDate, ?string $endDate): array
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
            default => [$today->startOfMonth(), $today->endOfMonth()->startOfDay(), 'Bulan Ini'],
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

    private function classifyCashFlowSection($counterpartRows): string
    {
        if (! $counterpartRows || collect($counterpartRows)->isEmpty()) {
            return 'operating';
        }

        $score = [
            'asset' => 0.0,
            'liability' => 0.0,
            'equity' => 0.0,
            'revenue' => 0.0,
            'expense' => 0.0,
        ];

        foreach (collect($counterpartRows) as $row) {
            $type = (string) ($row->type ?? '');
            if (! array_key_exists($type, $score)) {
                continue;
            }
            $score[$type] += (float) ($row->absolute_movement ?? 0);
        }

        if ($score['equity'] > 0) {
            return 'financing';
        }

        if ($score['asset'] > 0 && $score['asset'] >= max($score['liability'], $score['revenue'], $score['expense'])) {
            return 'investing';
        }

        if ($score['liability'] > 0 && ($score['liability'] > $score['revenue'] + $score['expense'])) {
            return 'financing';
        }

        return 'operating';
    }

    private function cashBalanceUntil(string $tenantId, array $cashAccountIds, string $asOfDate): string
    {
        if ($cashAccountIds === []) {
            return Decimal::money(0);
        }

        $row = DB::table('journal_items as ji')
            ->join('journal_entries as je', function ($join): void {
                $join->on('je.id', '=', 'ji.journal_entry_id')
                    ->on('je.tenant_id', '=', 'ji.tenant_id');
            })
            ->where('ji.tenant_id', $tenantId)
            ->where('je.status', 'posted')
            ->where('je.date', '<=', $asOfDate)
            ->whereIn('ji.account_id', $cashAccountIds)
            ->selectRaw('COALESCE(SUM(ji.debit - ji.credit), 0) as cash_balance')
            ->first();

        return Decimal::money((float) ($row->cash_balance ?? 0));
    }

    private function accountAggregateUntil(string $tenantId, string $accountId, string $date, bool $inclusive): object
    {
        return DB::table('journal_items as ji')
            ->join('journal_entries as je', function ($join): void {
                $join->on('je.id', '=', 'ji.journal_entry_id')
                    ->on('je.tenant_id', '=', 'ji.tenant_id');
            })
            ->where('ji.tenant_id', $tenantId)
            ->where('ji.account_id', $accountId)
            ->where('je.status', 'posted')
            ->when(
                $inclusive,
                fn ($query) => $query->where('je.date', '<=', $date),
                fn ($query) => $query->where('je.date', '<', $date)
            )
            ->selectRaw('COALESCE(SUM(ji.debit), 0) as debit_total')
            ->selectRaw('COALESCE(SUM(ji.credit), 0) as credit_total')
            ->first();
    }

    private function accountAggregateForRange(string $tenantId, string $accountId, string $startDate, string $endDate): object
    {
        return DB::table('journal_items as ji')
            ->join('journal_entries as je', function ($join): void {
                $join->on('je.id', '=', 'ji.journal_entry_id')
                    ->on('je.tenant_id', '=', 'ji.tenant_id');
            })
            ->where('ji.tenant_id', $tenantId)
            ->where('ji.account_id', $accountId)
            ->where('je.status', 'posted')
            ->whereBetween('je.date', [$startDate, $endDate])
            ->selectRaw('COALESCE(SUM(ji.debit), 0) as debit_total')
            ->selectRaw('COALESCE(SUM(ji.credit), 0) as credit_total')
            ->first();
    }

    private function normalizedAmountByType(string $type, float $debit, float $credit): float
    {
        return match ($type) {
            'asset', 'expense' => $debit - $credit,
            'liability', 'equity', 'revenue' => $credit - $debit,
            default => 0.0,
        };
    }

    private function recordSlowQueryIfNeeded(string $tenantId, string $endpoint, float $startedAt, array $payload = []): void
    {
        $durationMs = (microtime(true) - $startedAt) * 1000;
        $thresholdMs = max(1, (int) config('system_health.report_slow_ms', 1500));

        if ($durationMs < $thresholdMs) {
            return;
        }

        app(SystemHealthEventService::class)->recordOrBump('report_query_slow', [
            'tenant_id' => $tenantId,
            'entity_type' => 'report_endpoint',
            'entity_id' => $endpoint,
            'summary' => 'Report query exceeded slow threshold.',
            'payload' => array_merge($payload, [
                'endpoint' => $endpoint,
                'duration_ms' => round($durationMs, 2),
                'threshold_ms' => $thresholdMs,
            ]),
            'source' => 'report-query',
            'dedupe_key' => 'report_query_slow:'.$tenantId.':'.$endpoint,
        ]);
    }
}
