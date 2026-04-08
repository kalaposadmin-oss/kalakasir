<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccountMapping;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Location;
use App\Models\Shift;
use App\Services\TenantDefaultAccountingSetupService;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class BootstrapController extends Controller
{
    public function __invoke(Request $request)
    {
        $startedAt = microtime(true);
        $tenant = TenantContext::get();
        $tenantId = $tenant?->id;

        if ($tenantId && app()->environment('local')) {
            app(TenantDefaultAccountingSetupService::class)->ensure($tenantId, $request->user()?->id);
        }

        $registers = collect();
        if (Schema::hasTable('registers')) {
            $registers = DB::table('registers')
                ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
                ->where('status', 'active')
                ->get(['id', 'branch_id', 'location_id', 'name']);
        }

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
                ->get([
                    'shifts.id',
                    'shifts.branch_id',
                    'shifts.user_id',
                    'shifts.opened_at',
                    'shifts.status',
                    'users.name as cashier_name',
                ]),
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
            'catalog' => [
                'products_endpoint' => '/api/v1/products?lite=1',
                'customers_endpoint' => '/api/v1/customers?lite=1',
                'default_product_page_size' => 24,
                'default_customer_limit' => 100,
            ],
        ];

        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        $durationMs = number_format((microtime(true) - $startedAt) * 1000, 2, '.', '');

        return response($json, 200, [
            'Content-Type' => 'application/json',
            'X-Pos-Bootstrap-Bytes' => (string) strlen($json),
            'X-Pos-Catalog-Lazy' => '1',
            'Server-Timing' => 'bootstrap;dur='.$durationMs,
        ]);
    }
}
