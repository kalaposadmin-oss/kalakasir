<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccountMapping;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Category;
use App\Models\Customer;
use App\Models\InventoryBalance;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductPriceTier;
use App\Models\Shift;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BootstrapController extends Controller
{
    public function __invoke(Request $request)
    {
        $tenant = TenantContext::get();
        $tenantId = $tenant?->id;

        if ($tenantId && app()->environment('local')) {
            $this->ensureLocalPosAccountingSetup($tenantId, $request->user()?->id);
        }

        $products = Product::query()
            ->where('is_active', true)
            ->get(['id', 'sku', 'name', 'price', 'image_url', 'category_id', 'track_stock']);

        $tierRows = ProductPriceTier::query()
            ->whereIn('product_id', $products->pluck('id'))
            ->orderBy('min_qty')
            ->get(['product_id', 'min_qty', 'price']);

        $tiersByProduct = $tierRows->groupBy('product_id');

        $products = $products->map(function ($product) use ($tiersByProduct) {
            $product->price_tiers = ($tiersByProduct[$product->id] ?? collect())->values();
            return $product;
        });

        return response()->json([
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
            'branches' => Branch::query()->get(['id', 'name']),
            'categories' => Category::query()->get(['id', 'name', 'slug']),
            'locations' => Location::query()->get(['id', 'branch_id', 'name', 'is_default']),
            'shifts' => Shift::query()
                ->leftJoin('users', function ($join) {
                    $join->on('users.id', '=', 'shifts.user_id')
                        ->on('users.tenant_id', '=', 'shifts.tenant_id');
                })
                ->where('shifts.status', 'open')
                ->get([
                    'shifts.id',
                    'shifts.branch_id',
                    'shifts.user_id',
                    'shifts.opened_at',
                    'shifts.status',
                    'users.name as cashier_name',
                ]),
            'customers' => Customer::query()->get(['id', 'name']),
            'products' => $products,
            'inventory' => InventoryBalance::query()->get(['product_id', 'location_id', 'qty_on_hand']),
            'account_mapping' => AccountMapping::query()->first([
                'sale_account_id',
                'cash_account_id',
                'bank_account_id',
                'inventory_account_id',
                'cogs_account_id',
                'payable_account_id',
                'receivable_account_id',
                'expense_adjustment_account_id',
            ]),
            'chart_accounts' => ChartOfAccount::query()->get(['id', 'code', 'name']),
        ]);
    }

    private function ensureLocalPosAccountingSetup(string $tenantId, ?string $actorId): void
    {
        $definitions = [
            '1101' => ['name' => 'Kas', 'type' => 'asset'],
            '1102' => ['name' => 'Bank', 'type' => 'asset'],
            '1201' => ['name' => 'Piutang Usaha', 'type' => 'asset'],
            '1301' => ['name' => 'Persediaan Barang', 'type' => 'asset'],
            '2101' => ['name' => 'Hutang Usaha', 'type' => 'liability'],
            '4101' => ['name' => 'Penjualan', 'type' => 'revenue'],
            '5101' => ['name' => 'Harga Pokok Penjualan', 'type' => 'expense'],
            '6101' => ['name' => 'Penyesuaian Beban', 'type' => 'expense'],
        ];

        foreach ($definitions as $code => $meta) {
            $exists = DB::table('chart_of_accounts')
                ->where('tenant_id', $tenantId)
                ->where('code', $code)
                ->exists();

            if (! $exists) {
                DB::table('chart_of_accounts')->insert([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'code' => $code,
                    'name' => $meta['name'],
                    'type' => $meta['type'],
                    'parent_id' => null,
                ]);
            }
        }

        $mapping = DB::table('account_mappings')->where('tenant_id', $tenantId)->first();

        $resolvedIds = [
            'sale_account_id' => DB::table('chart_of_accounts')->where('tenant_id', $tenantId)->where('code', '4101')->value('id'),
            'cash_account_id' => DB::table('chart_of_accounts')->where('tenant_id', $tenantId)->where('code', '1101')->value('id'),
            'bank_account_id' => DB::table('chart_of_accounts')->where('tenant_id', $tenantId)->where('code', '1102')->value('id'),
            'inventory_account_id' => DB::table('chart_of_accounts')->where('tenant_id', $tenantId)->where('code', '1301')->value('id'),
            'cogs_account_id' => DB::table('chart_of_accounts')->where('tenant_id', $tenantId)->where('code', '5101')->value('id'),
            'payable_account_id' => DB::table('chart_of_accounts')->where('tenant_id', $tenantId)->where('code', '2101')->value('id'),
            'receivable_account_id' => DB::table('chart_of_accounts')->where('tenant_id', $tenantId)->where('code', '1201')->value('id'),
            'expense_adjustment_account_id' => DB::table('chart_of_accounts')->where('tenant_id', $tenantId)->where('code', '6101')->value('id'),
        ];

        if (! $mapping) {
            DB::table('account_mappings')->insert(array_merge([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ], $resolvedIds));

            return;
        }

        $patch = ['updated_by' => $actorId];
        foreach ($resolvedIds as $column => $value) {
            if (! $mapping->{$column}) {
                $patch[$column] = $value;
            }
        }

        if (count($patch) > 1) {
            DB::table('account_mappings')
                ->where('tenant_id', $tenantId)
                ->update($patch);
        }
    }
}
