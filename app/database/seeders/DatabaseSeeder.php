<?php

namespace Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $now = CarbonImmutable::now('UTC');

        if (DB::table('tenants')->exists()) {
            return;
        }

        $tenantId = (string) Str::uuid();
        $branchId = (string) Str::uuid();
        $branchSecondaryId = (string) Str::uuid();
        $locationFrontId = (string) Str::uuid();
        $locationWarehouseId = (string) Str::uuid();
        $shiftId = (string) Str::uuid();
        $userId = (string) Str::uuid();
        $userIds = [
            'andi' => (string) Str::uuid(),
            'siska' => (string) Str::uuid(),
            'rendi' => (string) Str::uuid(),
            'maya' => (string) Str::uuid(),
        ];

        DB::table('tenants')->insert([
            'id' => $tenantId,
            'name' => 'Kalaka ID',
            'timezone' => 'Asia/Jakarta',
            'domain' => null,
            'subdomain' => 'kalaka',
            'logo_url' => null,
            'created_at' => $now,
        ]);

        DB::table('users')->insert([
            'id' => $userId,
            'tenant_id' => $tenantId,
            'name' => 'Budi Santoso',
            'email' => 'budi@kalaka.test',
            'email_verified_at' => $now,
            'password' => bcrypt('password'),
            'failed_login_attempts' => 0,
            'remember_token' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        DB::table('users')->insert([
            [
                'id' => $userIds['andi'],
                'tenant_id' => $tenantId,
                'name' => 'Andi Saputra',
                'email' => 'andi@kalaka.test',
                'email_verified_at' => $now,
                'password' => bcrypt('password'),
                'failed_login_attempts' => 0,
                'remember_token' => null,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ],
            [
                'id' => $userIds['siska'],
                'tenant_id' => $tenantId,
                'name' => 'Siska Amalia',
                'email' => 'siska@kalaka.test',
                'email_verified_at' => $now,
                'password' => bcrypt('password'),
                'failed_login_attempts' => 0,
                'remember_token' => null,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => $now,
            ],
            [
                'id' => $userIds['rendi'],
                'tenant_id' => $tenantId,
                'name' => 'Rendi Wijaya',
                'email' => 'rendi@kalaka.test',
                'email_verified_at' => $now,
                'password' => bcrypt('password'),
                'failed_login_attempts' => 0,
                'remember_token' => null,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ],
            [
                'id' => $userIds['maya'],
                'tenant_id' => $tenantId,
                'name' => 'Maya Putri',
                'email' => 'maya@kalaka.test',
                'email_verified_at' => $now,
                'password' => bcrypt('password'),
                'failed_login_attempts' => 0,
                'remember_token' => null,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ],
        ]);

        DB::table('branches')->insert([
            [
                'id' => $branchId,
                'tenant_id' => $tenantId,
                'name' => 'Branch Sudirman',
            ],
            [
                'id' => $branchSecondaryId,
                'tenant_id' => $tenantId,
                'name' => 'Branch Setiabudi',
            ],
        ]);

        DB::table('locations')->insert([
            [
                'id' => $locationFrontId,
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'name' => 'Front Display',
                'is_default' => true,
            ],
            [
                'id' => $locationWarehouseId,
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'name' => 'Gudang Utama',
                'is_default' => false,
            ],
        ]);

        DB::table('shifts')->insert([
            'id' => $shiftId,
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'user_id' => $userId,
            'opened_at' => $now,
            'closed_at' => null,
            'opening_cash' => 250000,
            'closing_cash' => 0,
            'system_cash_total' => 0,
            'difference' => 0,
            'status' => 'open',
        ]);

        DB::table('shifts')->insert([
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'user_id' => $userIds['andi'],
                'opened_at' => $now->subHours(1),
                'closed_at' => null,
                'opening_cash' => 150000,
                'closing_cash' => 0,
                'system_cash_total' => 0,
                'difference' => 0,
                'status' => 'open',
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'user_id' => $userIds['rendi'],
                'opened_at' => $now->subHours(3),
                'closed_at' => null,
                'opening_cash' => 100000,
                'closing_cash' => 0,
                'system_cash_total' => 0,
                'difference' => 0,
                'status' => 'open',
            ],
        ]);

        $categoryIds = [
            'minuman' => (string) Str::uuid(),
            'bakery' => (string) Str::uuid(),
            'merchandise' => (string) Str::uuid(),
            'camilan' => (string) Str::uuid(),
        ];

        DB::table('categories')->insert([
            [
                'id' => $categoryIds['minuman'],
                'tenant_id' => $tenantId,
                'name' => 'Minuman',
                'slug' => 'minuman',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => $categoryIds['bakery'],
                'tenant_id' => $tenantId,
                'name' => 'Bakery',
                'slug' => 'bakery',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => $categoryIds['merchandise'],
                'tenant_id' => $tenantId,
                'name' => 'Merchandise',
                'slug' => 'merchandise',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => $categoryIds['camilan'],
                'tenant_id' => $tenantId,
                'name' => 'Camilan',
                'slug' => 'camilan',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $products = [
            [
                'id' => (string) Str::uuid(),
                'sku' => 'COF-ESP-001',
                'name' => 'Espresso Organik',
                'category_id' => $categoryIds['minuman'],
                'price' => 45000,
                'hpp_average' => 22000,
                'min_stock' => 12,
            ],
            [
                'id' => (string) Str::uuid(),
                'sku' => 'BAK-SOUR-002',
                'name' => 'Roti Sourdough',
                'category_id' => $categoryIds['bakery'],
                'price' => 72000,
                'hpp_average' => 38000,
                'min_stock' => 10,
            ],
            [
                'id' => (string) Str::uuid(),
                'sku' => 'MER-TUMB-003',
                'name' => 'Tumbler Travel',
                'category_id' => $categoryIds['merchandise'],
                'price' => 240000,
                'hpp_average' => 150000,
                'min_stock' => 5,
            ],
            [
                'id' => (string) Str::uuid(),
                'sku' => 'SNK-CHO-004',
                'name' => 'Cokelat Hitam',
                'category_id' => $categoryIds['camilan'],
                'price' => 55000,
                'hpp_average' => 26000,
                'min_stock' => 15,
            ],
        ];

        foreach ($products as $product) {
            DB::table('products')->insert([
                'id' => $product['id'],
                'tenant_id' => $tenantId,
                'category_id' => $product['category_id'],
                'sku' => $product['sku'],
                'name' => $product['name'],
                'price' => $product['price'],
                'hpp_average' => $product['hpp_average'],
                'min_stock' => $product['min_stock'],
                'image_url' => null,
                'is_active' => true,
                'deleted_at' => null,
                'created_by' => $userId,
                'updated_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $roleIds = [
            'kasir' => (string) Str::uuid(),
            'manager' => (string) Str::uuid(),
            'gudang' => (string) Str::uuid(),
            'admin' => (string) Str::uuid(),
        ];

        DB::table('roles')->insert([
            [
                'id' => $roleIds['kasir'],
                'tenant_id' => $tenantId,
                'name' => 'Kasir',
            ],
            [
                'id' => $roleIds['manager'],
                'tenant_id' => $tenantId,
                'name' => 'Manager',
            ],
            [
                'id' => $roleIds['gudang'],
                'tenant_id' => $tenantId,
                'name' => 'Staf Gudang',
            ],
            [
                'id' => $roleIds['admin'],
                'tenant_id' => $tenantId,
                'name' => 'Admin',
            ],
        ]);

        DB::table('user_roles')->insert([
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'role_id' => $roleIds['manager'],
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'user_id' => $userIds['andi'],
                'role_id' => $roleIds['kasir'],
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'user_id' => $userIds['siska'],
                'role_id' => $roleIds['kasir'],
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'user_id' => $userIds['rendi'],
                'role_id' => $roleIds['gudang'],
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'user_id' => $userIds['maya'],
                'role_id' => $roleIds['kasir'],
            ],
        ]);

        DB::table('inventory_balances')->insert([
            [
                'tenant_id' => $tenantId,
                'location_id' => $locationFrontId,
                'product_id' => $products[0]['id'],
                'qty_on_hand' => 42,
            ],
            [
                'tenant_id' => $tenantId,
                'location_id' => $locationFrontId,
                'product_id' => $products[1]['id'],
                'qty_on_hand' => 12,
            ],
            [
                'tenant_id' => $tenantId,
                'location_id' => $locationFrontId,
                'product_id' => $products[2]['id'],
                'qty_on_hand' => 8,
            ],
            [
                'tenant_id' => $tenantId,
                'location_id' => $locationFrontId,
                'product_id' => $products[3]['id'],
                'qty_on_hand' => 114,
            ],
        ]);

        $customerIds = [
            'umum' => (string) Str::uuid(),
            'sarah' => (string) Str::uuid(),
            'budi' => (string) Str::uuid(),
            'ani' => (string) Str::uuid(),
            'dedi' => (string) Str::uuid(),
            'rina' => (string) Str::uuid(),
        ];

        DB::table('customers')->insert([
            [
                'id' => $customerIds['umum'],
                'tenant_id' => $tenantId,
                'name' => 'Pelanggan Umum',
                'phone' => null,
                'email' => null,
                'address' => null,
                'created_at' => $now,
            ],
            [
                'id' => $customerIds['sarah'],
                'tenant_id' => $tenantId,
                'name' => 'Sarah Williams',
                'phone' => '08123456789',
                'email' => 'sarah@kalaka.test',
                'address' => null,
                'created_at' => $now,
            ],
            [
                'id' => $customerIds['budi'],
                'tenant_id' => $tenantId,
                'name' => 'Budi Santoso',
                'phone' => '08123456780',
                'email' => 'budi.customer@kalaka.test',
                'address' => 'Jl. Merdeka No. 10',
                'created_at' => $now,
            ],
            [
                'id' => $customerIds['ani'],
                'tenant_id' => $tenantId,
                'name' => 'Ani Lestari',
                'phone' => '081922334455',
                'email' => 'ani@kalaka.test',
                'address' => 'Jl. Melati No. 5',
                'created_at' => $now,
            ],
            [
                'id' => $customerIds['dedi'],
                'tenant_id' => $tenantId,
                'name' => 'Dedi Wijaya',
                'phone' => '085677889900',
                'email' => 'dedi@kalaka.test',
                'address' => 'Jl. Kenanga No. 3',
                'created_at' => $now,
            ],
            [
                'id' => $customerIds['rina'],
                'tenant_id' => $tenantId,
                'name' => 'Rina Kusuma',
                'phone' => '081133221100',
                'email' => 'rina@kalaka.test',
                'address' => 'Jl. Mawar No. 11',
                'created_at' => $now,
            ],
        ]);

        $supplierIds = [
            'makmur' => (string) Str::uuid(),
            'indo' => (string) Str::uuid(),
            'grosir' => (string) Str::uuid(),
            'logistik' => (string) Str::uuid(),
        ];

        DB::table('suppliers')->insert([
            [
                'id' => $supplierIds['makmur'],
                'tenant_id' => $tenantId,
                'name' => 'PT. Sumber Makmur',
                'balance' => 12450000,
                'created_at' => $now,
            ],
            [
                'id' => $supplierIds['indo'],
                'tenant_id' => $tenantId,
                'name' => 'Distributor Indo Jaya',
                'balance' => 5200000,
                'created_at' => $now,
            ],
            [
                'id' => $supplierIds['grosir'],
                'tenant_id' => $tenantId,
                'name' => 'Grosir Sembako Kita',
                'balance' => 0,
                'created_at' => $now,
            ],
            [
                'id' => $supplierIds['logistik'],
                'tenant_id' => $tenantId,
                'name' => 'Logistik Utama',
                'balance' => 27550000,
                'created_at' => $now,
            ],
        ]);

        $purchaseIds = [
            'po1' => (string) Str::uuid(),
            'po2' => (string) Str::uuid(),
        ];

        DB::table('purchases')->insert([
            [
                'id' => $purchaseIds['po1'],
                'tenant_id' => $tenantId,
                'supplier_id' => $supplierIds['makmur'],
                'total' => 3450000,
                'status' => 'menunggu',
                'created_at' => $now,
            ],
            [
                'id' => $purchaseIds['po2'],
                'tenant_id' => $tenantId,
                'supplier_id' => $supplierIds['indo'],
                'total' => 1250000,
                'status' => 'diterima',
                'created_at' => $now->subDays(2),
            ],
        ]);

        DB::table('purchase_items')->insert([
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'purchase_id' => $purchaseIds['po1'],
                'product_id' => $products[0]['id'],
                'qty' => 24,
                'price' => 25000,
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'purchase_id' => $purchaseIds['po1'],
                'product_id' => $products[1]['id'],
                'qty' => 12,
                'price' => 42000,
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'purchase_id' => $purchaseIds['po1'],
                'product_id' => $products[2]['id'],
                'qty' => 8,
                'price' => 90000,
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'purchase_id' => $purchaseIds['po2'],
                'product_id' => $products[3]['id'],
                'qty' => 30,
                'price' => 28000,
            ],
        ]);

        $accounts = [
            'sale' => ['code' => '401-001', 'name' => 'Penjualan', 'type' => 'revenue'],
            'cash' => ['code' => '101-001', 'name' => 'Kas', 'type' => 'asset'],
            'bank' => ['code' => '102-010', 'name' => 'Bank', 'type' => 'asset'],
            'inventory' => ['code' => '105-010', 'name' => 'Persediaan', 'type' => 'asset'],
            'cogs' => ['code' => '501-001', 'name' => 'HPP', 'type' => 'expense'],
            'payable' => ['code' => '201-001', 'name' => 'Hutang', 'type' => 'liability'],
            'receivable' => ['code' => '103-001', 'name' => 'Piutang', 'type' => 'asset'],
            'expense_adj' => ['code' => '509-001', 'name' => 'Penyesuaian', 'type' => 'expense'],
        ];

        $accountIds = [];

        foreach ($accounts as $key => $account) {
            $accountIds[$key] = (string) Str::uuid();

            DB::table('chart_of_accounts')->insert([
                'id' => $accountIds[$key],
                'tenant_id' => $tenantId,
                'code' => $account['code'],
                'name' => $account['name'],
                'type' => $account['type'],
                'parent_id' => null,
            ]);
        }

        DB::table('account_mappings')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'sale_account_id' => $accountIds['sale'],
            'cash_account_id' => $accountIds['cash'],
            'bank_account_id' => $accountIds['bank'],
            'inventory_account_id' => $accountIds['inventory'],
            'cogs_account_id' => $accountIds['cogs'],
            'payable_account_id' => $accountIds['payable'],
            'receivable_account_id' => $accountIds['receivable'],
            'expense_adjustment_account_id' => $accountIds['expense_adj'],
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        $saleIds = [
            'sale1' => (string) Str::uuid(),
            'sale2' => (string) Str::uuid(),
            'sale3' => (string) Str::uuid(),
        ];

        DB::table('sales')->insert([
            [
                'id' => $saleIds['sale1'],
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'shift_id' => $shiftId,
                'customer_id' => $customerIds['sarah'],
                'subtotal' => 162000,
                'discount' => 0,
                'tax' => 0,
                'total' => 162000,
                'status' => 'paid',
                'idempotency_key' => (string) Str::uuid(),
                'created_by' => $userId,
                'voided_by' => null,
                'created_at' => $now,
            ],
            [
                'id' => $saleIds['sale2'],
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'shift_id' => $shiftId,
                'customer_id' => $customerIds['budi'],
                'subtotal' => 405000,
                'discount' => 0,
                'tax' => 0,
                'total' => 405000,
                'status' => 'paid',
                'idempotency_key' => (string) Str::uuid(),
                'created_by' => $userId,
                'voided_by' => null,
                'created_at' => $now->subHours(2),
            ],
            [
                'id' => $saleIds['sale3'],
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'shift_id' => $shiftId,
                'customer_id' => $customerIds['rina'],
                'subtotal' => 155000,
                'discount' => 0,
                'tax' => 0,
                'total' => 155000,
                'status' => 'paid',
                'idempotency_key' => (string) Str::uuid(),
                'created_by' => $userId,
                'voided_by' => null,
                'created_at' => $now->subDay(),
            ],
        ]);

        DB::table('sale_items')->insert([
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'sale_id' => $saleIds['sale1'],
                'product_id' => $products[0]['id'],
                'qty' => 2,
                'price' => 45000,
                'discount' => 0,
                'hpp_snapshot' => 22000,
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'sale_id' => $saleIds['sale1'],
                'product_id' => $products[1]['id'],
                'qty' => 1,
                'price' => 72000,
                'discount' => 0,
                'hpp_snapshot' => 38000,
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'sale_id' => $saleIds['sale2'],
                'product_id' => $products[2]['id'],
                'qty' => 1,
                'price' => 240000,
                'discount' => 0,
                'hpp_snapshot' => 150000,
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'sale_id' => $saleIds['sale2'],
                'product_id' => $products[3]['id'],
                'qty' => 3,
                'price' => 55000,
                'discount' => 0,
                'hpp_snapshot' => 26000,
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'sale_id' => $saleIds['sale3'],
                'product_id' => $products[0]['id'],
                'qty' => 1,
                'price' => 45000,
                'discount' => 0,
                'hpp_snapshot' => 22000,
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'sale_id' => $saleIds['sale3'],
                'product_id' => $products[3]['id'],
                'qty' => 2,
                'price' => 55000,
                'discount' => 0,
                'hpp_snapshot' => 26000,
            ],
        ]);

        DB::table('payments')->insert([
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'sale_id' => $saleIds['sale1'],
                'account_id' => $accountIds['bank'],
                'method' => 'qris',
                'amount' => 162000,
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'sale_id' => $saleIds['sale2'],
                'account_id' => $accountIds['cash'],
                'method' => 'cash',
                'amount' => 200000,
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'sale_id' => $saleIds['sale2'],
                'account_id' => $accountIds['bank'],
                'method' => 'bank',
                'amount' => 205000,
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'sale_id' => $saleIds['sale3'],
                'account_id' => $accountIds['bank'],
                'method' => 'bank',
                'amount' => 155000,
            ],
        ]);

        DB::table('lock_periods')->insert([
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'start_date' => $now->startOfMonth()->subMonths(2)->toDateString(),
                'end_date' => $now->startOfMonth()->subMonth()->subDay()->toDateString(),
                'is_locked' => true,
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'start_date' => $now->startOfMonth()->subMonth()->toDateString(),
                'end_date' => $now->startOfMonth()->subDay()->toDateString(),
                'is_locked' => true,
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'start_date' => $now->startOfMonth()->toDateString(),
                'end_date' => $now->endOfMonth()->toDateString(),
                'is_locked' => false,
            ],
        ]);

        DB::table('loyalty_tiers')->insert([
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'name' => 'Silver',
                'min_points' => 0,
                'color' => 'silver',
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'name' => 'Gold',
                'min_points' => 250,
                'color' => 'gold',
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'name' => 'Platinum',
                'min_points' => 500,
                'color' => 'platinum',
            ],
        ]);

        $stockTransferIds = [
            'trf1' => (string) Str::uuid(),
            'trf2' => (string) Str::uuid(),
            'trf3' => (string) Str::uuid(),
        ];

        DB::table('stock_transfers')->insert([
            [
                'id' => $stockTransferIds['trf1'],
                'tenant_id' => $tenantId,
                'from_branch_id' => $branchId,
                'to_branch_id' => $branchSecondaryId,
                'status' => 'in_transit',
                'reference' => 'TRF-2404-001',
                'created_by' => $userId,
                'created_at' => $now->subHours(6),
            ],
            [
                'id' => $stockTransferIds['trf2'],
                'tenant_id' => $tenantId,
                'from_branch_id' => $branchSecondaryId,
                'to_branch_id' => $branchId,
                'status' => 'pending_receive',
                'reference' => 'TRF-2404-002',
                'created_by' => $userId,
                'created_at' => $now->subHours(3),
            ],
            [
                'id' => $stockTransferIds['trf3'],
                'tenant_id' => $tenantId,
                'from_branch_id' => $branchId,
                'to_branch_id' => $branchSecondaryId,
                'status' => 'draft',
                'reference' => 'TRF-2404-003',
                'created_by' => $userId,
                'created_at' => $now->subHour(),
            ],
        ]);

        DB::table('stock_transfer_items')->insert([
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'stock_transfer_id' => $stockTransferIds['trf1'],
                'product_id' => $products[0]['id'],
                'qty' => 8,
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'stock_transfer_id' => $stockTransferIds['trf1'],
                'product_id' => $products[3]['id'],
                'qty' => 12,
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'stock_transfer_id' => $stockTransferIds['trf2'],
                'product_id' => $products[1]['id'],
                'qty' => 5,
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'stock_transfer_id' => $stockTransferIds['trf2'],
                'product_id' => $products[2]['id'],
                'qty' => 2,
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'stock_transfer_id' => $stockTransferIds['trf3'],
                'product_id' => $products[0]['id'],
                'qty' => 4,
            ],
        ]);

        DB::table('taxes')->insert([
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'name' => 'PPN',
                'description' => 'Pajak Pertambahan Nilai',
                'rate' => 11,
                'is_active' => true,
                'created_at' => $now,
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'name' => 'Service Charge',
                'description' => 'Biaya Layanan',
                'rate' => 5,
                'is_active' => false,
                'created_at' => $now,
            ],
        ]);

        DB::table('promos')->insert([
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'name' => 'Promo Gajian Ceria',
                'type' => 'percent',
                'value' => 10,
                'min_purchase' => 100000,
                'category' => null,
                'code' => null,
                'status' => 'active',
                'ends_at' => $now->addDays(30)->toDateString(),
                'created_at' => $now,
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'name' => 'Potongan Flash Sale',
                'type' => 'amount',
                'value' => 5000,
                'min_purchase' => null,
                'category' => 'Minuman Dingin',
                'code' => null,
                'status' => 'active',
                'ends_at' => $now->addDays(40)->toDateString(),
                'created_at' => $now,
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'name' => 'Voucher Member Baru',
                'type' => 'amount',
                'value' => 15000,
                'min_purchase' => null,
                'category' => null,
                'code' => 'NEWUSER15',
                'status' => 'expired',
                'ends_at' => $now->subDays(10)->toDateString(),
                'created_at' => $now,
            ],
        ]);
    }
}
