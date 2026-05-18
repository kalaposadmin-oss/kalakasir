<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Receivable;
use App\Models\Shift;
use App\Services\ReceivableService;
use App\Support\ApiResponse;
use App\Support\Decimal;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class ReceivableController extends Controller
{
    public function __construct(private readonly ReceivableService $service) {}

    public function index(Request $request)
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json(['message' => 'Tenant tidak ditemukan.'], 400);
        }

        $validated = $request->validate([
            'status' => ['nullable', 'string', Rule::in(['all', 'open', 'unpaid', 'partial', 'paid', 'overdue'])],
            'search' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $today = CarbonImmutable::now('Asia/Jakarta')->toDateString();
        $perPage = (int) ($validated['per_page'] ?? 20);
        $status = $validated['status'] ?? 'open';
        $search = trim((string) ($validated['search'] ?? ''));

        $query = Receivable::query()
            ->with([
                'customer:id,tenant_id,name,phone',
                'sale:id,tenant_id,total,status,created_at,transaction_date',
            ])
            ->where('tenant_id', $tenantId);

        $this->applyStatusFilter($query, $status, $today);

        if ($search !== '') {
            $query->whereHas('customer', function ($customerQuery) use ($search): void {
                $customerQuery
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%');
            });
        }

        $paginator = $query
            ->orderByRaw("CASE WHEN status = 'paid' THEN 1 ELSE 0 END")
            ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_date')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return ApiResponse::paginated([
            'receivables' => $paginator->getCollection()
                ->map(fn (Receivable $receivable) => $this->formatReceivable($receivable))
                ->values()
                ->all(),
            'summary' => $this->buildSummary($tenantId),
        ], $paginator->currentPage(), $paginator->perPage(), $paginator->total(), [
            'status' => $status,
            'search' => $search !== '' ? $search : null,
        ]);
    }

    public function show(Receivable $receivable)
    {
        $tenantId = TenantContext::id();
        if (! $tenantId || $receivable->tenant_id !== $tenantId) {
            abort(404);
        }

        $receivable->load([
            'customer:id,tenant_id,name,phone,email,address',
            'sale:id,tenant_id,branch_id,shift_id,register_id,total,status,created_at,transaction_date',
            'payments' => fn ($query) => $query->orderByDesc('created_at'),
        ]);

        return ApiResponse::success([
            'receivable' => $this->formatReceivable($receivable, includePayments: true),
        ]);
    }

    public function storePayment(Request $request, Receivable $receivable)
    {
        $tenantId = TenantContext::id();
        if (! $tenantId || $receivable->tenant_id !== $tenantId) {
            abort(404);
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'string', 'max:50'],
            'account_id' => ['required', 'uuid', Rule::exists('chart_of_accounts', 'id')->where('tenant_id', $tenantId)],
            'payment_method_id' => ['nullable', 'uuid', Rule::exists('payment_methods', 'id')->where('tenant_id', $tenantId)],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $activeShift = Shift::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'open')
            ->where('user_id', $request->user()?->id)
            ->orderByDesc('opened_at')
            ->first();

        $method = $validated['payment_method'];

        if ($method === 'cash' && ! $activeShift) {
            return response()->json([
                'code' => 'SHIFT_REQUIRED_FOR_CASH',
                'message' => 'Pelunasan piutang tunai membutuhkan shift aktif. Buka shift terlebih dahulu.',
                'details' => [],
            ], 422);
        }

        try {
            $result = $this->service->recordPayment(
                tenantId: $tenantId,
                receivableId: $receivable->id,
                amount: $validated['amount'],
                paymentMethod: $method,
                accountId: $validated['account_id'],
                paymentMethodId: $validated['payment_method_id'] ?? null,
                notes: $validated['notes'] ?? null,
                userId: $request->user()?->id,
                shiftId: $activeShift?->id
            );
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'code' => 'RECEIVABLE_PAYMENT_INVALID',
                'message' => $exception->getMessage(),
                'details' => [],
            ], 422);
        }

        return ApiResponse::success([
            'receivable' => $this->formatReceivable($result['receivable']),
            'payment' => $this->formatPayment($result['payment']),
        ], status: 201);
    }

    public function summary()
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json(['message' => 'Tenant tidak ditemukan.'], 400);
        }

        return ApiResponse::success($this->buildSummary($tenantId));
    }

    private function applyStatusFilter($query, string $status, string $today): void
    {
        if ($status === 'all') {
            return;
        }

        if ($status === 'open') {
            $query->where('status', '!=', Receivable::STATUS_PAID);

            return;
        }

        if ($status === 'overdue') {
            $query->where('status', '!=', Receivable::STATUS_PAID)
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', $today);

            return;
        }

        $query->where('status', $status);
    }

    private function buildSummary(string $tenantId): array
    {
        $today = CarbonImmutable::now('Asia/Jakarta')->toDateString();
        $activeCondition = "status <> 'paid' AND remaining_amount > 0";

        $row = DB::table('receivables')
            ->where('tenant_id', $tenantId)
            ->selectRaw("COALESCE(SUM(CASE WHEN {$activeCondition} THEN remaining_amount ELSE 0 END), 0) as active_total")
            ->selectRaw("COALESCE(SUM(CASE WHEN {$activeCondition} AND due_date IS NOT NULL AND due_date < ? THEN remaining_amount ELSE 0 END), 0) as overdue_total", [$today])
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'paid' THEN total_amount ELSE 0 END), 0) as paid_total")
            ->selectRaw('COALESCE(SUM(total_amount), 0) as lifetime_total')
            ->selectRaw("COUNT(CASE WHEN {$activeCondition} THEN 1 END) as active_count")
            ->selectRaw("COUNT(CASE WHEN status = 'paid' THEN 1 END) as paid_count")
            ->first();

        $perCustomer = DB::table('receivables')
            ->join('customers', function ($join): void {
                $join->on('customers.id', '=', 'receivables.customer_id')
                    ->on('customers.tenant_id', '=', 'receivables.tenant_id');
            })
            ->where('receivables.tenant_id', $tenantId)
            ->where('receivables.status', '<>', Receivable::STATUS_PAID)
            ->where('receivables.remaining_amount', '>', 0)
            ->groupBy('receivables.customer_id', 'customers.name')
            ->orderByDesc(DB::raw('SUM(receivables.remaining_amount)'))
            ->limit(10)
            ->get([
                'receivables.customer_id',
                'customers.name as customer_name',
                DB::raw('COALESCE(SUM(receivables.total_amount), 0) as total_amount'),
                DB::raw('COALESCE(SUM(receivables.paid_amount), 0) as paid_amount'),
                DB::raw('COALESCE(SUM(receivables.remaining_amount), 0) as active_remaining'),
                DB::raw('COUNT(*) as active_count'),
            ])
            ->map(fn ($customer) => [
                'customer_id' => (string) $customer->customer_id,
                'customer_name' => (string) $customer->customer_name,
                'total_amount' => Decimal::float($customer->total_amount, Decimal::MONEY_SCALE),
                'paid_amount' => Decimal::float($customer->paid_amount, Decimal::MONEY_SCALE),
                'active_remaining' => Decimal::float($customer->active_remaining, Decimal::MONEY_SCALE),
                'active_count' => (int) $customer->active_count,
            ])
            ->values()
            ->all();

        return [
            'active_total' => Decimal::float($row->active_total ?? 0, Decimal::MONEY_SCALE),
            'overdue_total' => Decimal::float($row->overdue_total ?? 0, Decimal::MONEY_SCALE),
            'paid_total' => Decimal::float($row->paid_total ?? 0, Decimal::MONEY_SCALE),
            'lifetime_total' => Decimal::float($row->lifetime_total ?? 0, Decimal::MONEY_SCALE),
            'active_count' => (int) ($row->active_count ?? 0),
            'paid_count' => (int) ($row->paid_count ?? 0),
            'per_customer' => $perCustomer,
        ];
    }

    private function formatReceivable(Receivable $receivable, bool $includePayments = false): array
    {
        $dueDate = $receivable->due_date?->toDateString();
        $today = CarbonImmutable::now('Asia/Jakarta')->toDateString();

        $row = [
            'id' => $receivable->id,
            'customer_id' => $receivable->customer_id,
            'customer_name' => $receivable->customer?->name,
            'customer_phone' => $receivable->customer?->phone,
            'sale_id' => $receivable->sale_id,
            'total_amount' => Decimal::float($receivable->total_amount, Decimal::MONEY_SCALE),
            'paid_amount' => Decimal::float($receivable->paid_amount, Decimal::MONEY_SCALE),
            'remaining_amount' => Decimal::float($receivable->remaining_amount, Decimal::MONEY_SCALE),
            'due_date' => $dueDate,
            'status' => $receivable->status,
            'is_overdue' => $receivable->status !== Receivable::STATUS_PAID && $dueDate !== null && $dueDate < $today,
            'created_at' => optional($receivable->created_at)->toIso8601String(),
            'sale' => $receivable->sale ? [
                'id' => $receivable->sale->id,
                'total' => Decimal::float($receivable->sale->total, Decimal::MONEY_SCALE),
                'status' => $receivable->sale->status,
                'transaction_date' => $receivable->sale->transaction_date,
                'created_at' => optional($receivable->sale->created_at)->toIso8601String(),
            ] : null,
        ];

        if ($includePayments || $receivable->relationLoaded('payments')) {
            $row['payments'] = $receivable->payments
                ->map(fn ($payment) => $this->formatPayment($payment))
                ->values()
                ->all();
        }

        return $row;
    }

    private function formatPayment($payment): array
    {
        return [
            'id' => $payment->id,
            'receivable_id' => $payment->receivable_id,
            'sale_id' => $payment->sale_id,
            'amount' => Decimal::float($payment->amount, Decimal::MONEY_SCALE),
            'payment_method' => $payment->payment_method,
            'payment_method_id' => $payment->payment_method_id,
            'account_id' => $payment->account_id,
            'notes' => $payment->notes,
            'created_at' => optional($payment->created_at)->toIso8601String(),
        ];
    }
}
