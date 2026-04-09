<?php

namespace Tests\Concerns;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

trait BuildsCheckoutRequestFixture
{
    protected function seedCheckoutFixture(string $tenantId, string $userId): array
    {
        $branchId = (string) Str::uuid();
        \DB::table('branches')->insert([
            'id' => $branchId,
            'tenant_id' => $tenantId,
            'name' => 'Main Branch',
        ]);

        $shiftId = (string) Str::uuid();
        \DB::table('shifts')->insert([
            'id' => $shiftId,
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'user_id' => $userId,
            'opened_at' => CarbonImmutable::now('UTC')->subHour(),
            'closed_at' => null,
            'opening_cash' => 0,
            'closing_cash' => 0,
            'system_cash_total' => 0,
            'difference' => 0,
            'status' => 'open',
        ]);

        $locationId = (string) Str::uuid();
        \DB::table('locations')->insert([
            'id' => $locationId,
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'name' => 'Main Warehouse',
            'is_default' => true,
        ]);

        $registerId = (string) Str::uuid();

        if (Schema::hasTable('registers')) {
            \DB::table('registers')->insert([
                'id' => $registerId,
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'location_id' => $locationId,
                'name' => 'Register A',
                'device_uid' => 'fixture-'.substr(str_replace('-', '', $registerId), 0, 12),
                'status' => 'active',
                'created_at' => CarbonImmutable::now('UTC'),
                'updated_at' => CarbonImmutable::now('UTC'),
            ]);
        }

        $productId = (string) Str::uuid();
        \DB::table('products')->insert([
            'id' => $productId,
            'tenant_id' => $tenantId,
            'sku' => 'SKU-001',
            'name' => 'Produk A',
            'price' => 10000,
            'hpp_average' => 5000,
            'image_url' => null,
            'is_active' => true,
            'track_stock' => true,
            'created_by' => $userId,
            'updated_by' => $userId,
            'created_at' => CarbonImmutable::now('UTC'),
            'updated_at' => CarbonImmutable::now('UTC'),
            'deleted_at' => null,
        ]);

        \DB::table('inventory_balances')->insert([
            'tenant_id' => $tenantId,
            'location_id' => $locationId,
            'product_id' => $productId,
            'qty_on_hand' => 100,
        ]);

        $accountIds = [
            'sale_account_id' => (string) Str::uuid(),
            'cash_account_id' => (string) Str::uuid(),
            'bank_account_id' => (string) Str::uuid(),
            'inventory_account_id' => (string) Str::uuid(),
            'cogs_account_id' => (string) Str::uuid(),
            'payable_account_id' => (string) Str::uuid(),
            'receivable_account_id' => (string) Str::uuid(),
            'expense_adjustment_account_id' => (string) Str::uuid(),
        ];

        $accounts = [];
        foreach ($accountIds as $key => $accountId) {
            $accounts[] = [
                'id' => $accountId,
                'tenant_id' => $tenantId,
                'code' => strtoupper(substr($key, 0, 3)).'-'.substr(str_replace('-', '', $accountId), 0, 6),
                'name' => $key,
                'type' => 'asset',
                'parent_id' => null,
            ];
        }

        \DB::table('chart_of_accounts')->insert($accounts);

        \DB::table('account_mappings')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'sale_account_id' => $accountIds['sale_account_id'],
            'cash_account_id' => $accountIds['cash_account_id'],
            'bank_account_id' => $accountIds['bank_account_id'],
            'inventory_account_id' => $accountIds['inventory_account_id'],
            'cogs_account_id' => $accountIds['cogs_account_id'],
            'payable_account_id' => $accountIds['payable_account_id'],
            'receivable_account_id' => $accountIds['receivable_account_id'],
            'expense_adjustment_account_id' => $accountIds['expense_adjustment_account_id'],
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        $paymentMethodId = (string) Str::uuid();
        if (Schema::hasTable('payment_methods')) {
            \DB::table('payment_methods')->insert([
                'id' => $paymentMethodId,
                'tenant_id' => $tenantId,
                'name' => 'Cash Fixture',
                'method_type' => 'cash',
                'is_active' => true,
                'created_at' => CarbonImmutable::now('UTC'),
                'updated_at' => CarbonImmutable::now('UTC'),
            ]);
        }

        return [
            'branch_id' => $branchId,
            'shift_id' => $shiftId,
            'register_id' => $registerId,
            'location_id' => $locationId,
            'product_id' => $productId,
            'cash_account_id' => $accountIds['cash_account_id'],
            'payment_method_id' => $paymentMethodId,
        ];
    }

    protected function checkoutPayload(array $fixture, string $transactionDate): array
    {
        return [
            'idempotency_key' => (string) Str::uuid(),
            'branch_id' => $fixture['branch_id'],
            'shift_id' => $fixture['shift_id'],
            'register_id' => $fixture['register_id'],
            'transaction_date' => $transactionDate,
            'subtotal' => 10000,
            'discount' => 0,
            'tax' => 0,
            'total' => 10000,
            'items' => [
                [
                    'product_id' => $fixture['product_id'],
                    'location_id' => $fixture['location_id'],
                    'qty' => 1,
                    'price' => 10000,
                    'discount' => 0,
                ],
            ],
            'payments' => [
                [
                    'account_id' => $fixture['cash_account_id'],
                    'method' => 'cash',
                    'amount' => 10000,
                    'payment_method_id' => $fixture['payment_method_id'] ?? null,
                ],
            ],
        ];
    }
}
