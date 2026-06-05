<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccountMapping;
use App\Models\Branch;
use App\Models\Category;
use App\Models\InventoryBalance;
use App\Models\Location;
use App\Models\Product;
use App\Models\Shift;
use App\Services\ProductionEligibilityService;
use App\Services\ShiftLifecycleService;
use App\Services\TenantDefaultAccountingSetupService;
use App\Services\TenantDefaultRegisterSetupService;
use App\Support\Decimal;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BootstrapController extends Controller
{
    public const INCLUDE_INVENTORY = 'inventory';

    public function __invoke(Request $request)
    {
        $startedAt = microtime(true);
        $tenant = TenantContext::get();
        $tenantId = $tenant?->id;

        if ($tenantId && app()->environment('local')) {
            app(TenantDefaultAccountingSetupService::class)->ensure($tenantId, $request->user()?->id);
            app(TenantDefaultRegisterSetupService::class)->ensure($tenant);
        }

        app(ShiftLifecycleService::class)->autoCloseExpiredOpenShifts($tenant);
        $pendingShiftReconciliation = app(ShiftLifecycleService::class)->pendingReconciliation($tenant, $request->user()?->id);

        $registers = collect();
        if (Schema::hasTable('registers')) {
            $registers = DB::table('registers')
                ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
                ->where('status', 'active')
                ->get(['id', 'branch_id', 'location_id', 'name']);
        }

        $eligibilityService = app(ProductionEligibilityService::class);

        $payload = [
            'user' => $request->user() ? [
                'id' => $request->user()->id,
                'name' => $request->user()->name,
                'role' => $request->user()->role,
            ] : null,
            'tenant' => $tenant ? [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'timezone' => $tenant->timezone,
                'inventory_valuation_method' => $tenant->inventory_valuation_method ?? 'average',
            ] : null,
            'branches' => Branch::query()
                ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
                ->get(['id', 'name']),
            'categories' => Category::query()
                ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
                ->get(['id', 'name', 'slug']),
            'locations' => Location::query()
                ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
                ->get(['id', 'branch_id', 'name', 'is_default']),
            'registers' => $registers,
            'shifts' => Shift::query()
                ->leftJoin('users', function ($join) {
                    $join->on('users.id', '=', 'shifts.user_id')
                        ->on('users.tenant_id', '=', 'shifts.tenant_id');
                })
                ->when($tenantId, fn ($query) => $query->where('shifts.tenant_id', $tenantId))
                ->where('shifts.status', 'open')
                ->orderByDesc('shifts.opened_at')
                ->orderByDesc('shifts.id')
                ->get([
                    'shifts.id',
                    'shifts.branch_id',
                    'shifts.user_id',
                    'shifts.opened_at',
                    'shifts.status',
                    'users.name as cashier_name',
                ])
                ->map(fn ($shift) => [
                    'id' => $shift->id,
                    'branch_id' => $shift->branch_id,
                    'user_id' => $shift->user_id,
                    'opened_at' => $this->toUtcIso8601($shift->opened_at),
                    'status' => $shift->status,
                    'cashier_name' => $shift->cashier_name,
                ])
                ->values(),
            'account_mapping' => AccountMapping::query()
                ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
                ->first([
                    'sale_account_id',
                    'cash_account_id',
                    'bank_account_id',
                    'inventory_account_id',
                    'cogs_account_id',
                    'payable_account_id',
                    'receivable_account_id',
                    'expense_adjustment_account_id',
                ]),
            'pending_shift_reconciliation' => $pendingShiftReconciliation ? [
                'shift_id' => $pendingShiftReconciliation->id,
                'branch_id' => $pendingShiftReconciliation->branch_id,
                'opened_at' => $this->toUtcIso8601($pendingShiftReconciliation->opened_at),
                'closed_at' => $this->toUtcIso8601($pendingShiftReconciliation->closed_at),
                'expected_cash' => (float) $pendingShiftReconciliation->system_cash_total,
                'message' => 'Selesaikan kas akhir shift kemarin terlebih dahulu.',
            ] : null,
            'catalog' => [
                'products_endpoint' => '/api/v1/products?lite=1',
                'customers_endpoint' => '/api/v1/customers?lite=1',
                'default_product_page_size' => 24,
                'default_customer_limit' => 100,
            ],
        ];

        $include = (string) $request->query('include', '');
        $includeInventory = in_array(self::INCLUDE_INVENTORY, array_filter(array_map('trim', explode(',', $include))), true);

        if ($includeInventory && $tenantId) {
            $products = Product::query()
                ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
                ->where('is_active', true)
                ->orderBy('name')
                ->get([
                    'id',
                    'sku',
                    'name',
                    'unit',
                    'image_url',
                    'is_active',
                    'product_mode',
                    'track_stock',
                    'production_mode',
                    'item_type',
                    'bom_items',
                    'recipe_output_qty',
                ])
                ->map(function ($product) use ($tenantId, $eligibilityService) {
                    $eligibility = $eligibilityService->summarizeBatchEligibility($tenantId, $product);

                    return [
                        'id' => $product->id,
                        'sku' => $product->sku,
                        'name' => $product->name,
                        'unit' => $product->unit,
                        'image_url' => $product->image_url,
                        'is_active' => (bool) $product->is_active,
                        'product_mode' => $product->product_mode,
                        'track_stock' => (bool) $product->track_stock,
                        'production_mode' => $product->production_mode,
                        'item_type' => $product->item_type,
                        'bom_items' => is_array($product->bom_items) ? $product->bom_items : [],
                        'recipe_output_qty' => $product->recipe_output_qty !== null
                            ? Decimal::float(Decimal::snappedQty($product->recipe_output_qty), Decimal::QTY_SCALE)
                            : null,
                        'production_batch_eligible' => (bool) ($eligibility['eligible'] ?? false),
                        'production_batch_eligibility' => $eligibility,
                    ];
                })
                ->values();

            $inventory = InventoryBalance::query()
                ->where('tenant_id', $tenantId)
                ->whereIn('product_id', $products->pluck('id')->all())
                ->select('product_id', 'location_id')
                ->selectRaw('sum(qty_on_hand) as qty_on_hand')
                ->groupBy('product_id', 'location_id')
                ->orderBy('location_id')
                ->orderBy('product_id')
                ->get()
                ->map(fn ($row) => [
                    'product_id' => $row->product_id,
                    'location_id' => $row->location_id,
                    'qty_on_hand' => (float) $row->qty_on_hand,
                ])
                ->values();

            $payload['products'] = $products;
            $payload['inventory'] = $inventory;
        }

        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        $durationMs = number_format((microtime(true) - $startedAt) * 1000, 2, '.', '');

        return response($json, 200, [
            'Content-Type' => 'application/json',
            'X-Pos-Bootstrap-Bytes' => (string) strlen($json),
            'X-Pos-Catalog-Lazy' => '1',
            'Server-Timing' => 'bootstrap;dur='.$durationMs,
        ]);
    }

    private function toUtcIso8601(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return CarbonImmutable::parse($value, 'UTC')->toIso8601String();
    }
}
