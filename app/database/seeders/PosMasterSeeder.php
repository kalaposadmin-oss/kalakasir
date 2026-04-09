<?php

namespace Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PosMasterSeeder extends Seeder
{
    public function run(): void
    {
        $now = CarbonImmutable::now('UTC');

        $tenant = DB::table('tenants')->orderBy('created_at')->first();
        if (!$tenant) {
            return;
        }

        $branch = DB::table('branches')->where('tenant_id', $tenant->id)->orderBy('name')->first();
        $location = DB::table('locations')->where('tenant_id', $tenant->id)->orderByDesc('is_default')->first();

        $products = DB::table('products')
            ->where('tenant_id', $tenant->id)
            ->orderBy('created_at')
            ->limit(4)
            ->get();

        $allProducts = DB::table('products')
            ->where('tenant_id', $tenant->id)
            ->orderBy('created_at')
            ->get();

        $customers = DB::table('customers')
            ->where('tenant_id', $tenant->id)
            ->orderBy('created_at')
            ->limit(2)
            ->get();

        if (!$branch || !$location || $products->isEmpty()) {
            return;
        }

        if (!DB::table('registers')->where('tenant_id', $tenant->id)->exists()) {
            DB::table('registers')->insert([
                [
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'branch_id' => $branch->id,
                    'location_id' => $location->id,
                    'name' => 'Kasir Utama',
                    'device_uid' => 'POS-DEVICE-001',
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'branch_id' => $branch->id,
                    'location_id' => $location->id,
                    'name' => 'Kasir Cadangan',
                    'device_uid' => 'POS-DEVICE-002',
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);
        }

        $register = DB::table('registers')->where('tenant_id', $tenant->id)->orderBy('created_at')->first();

        $seedUser = DB::table('users')->where('tenant_id', $tenant->id)->orderBy('created_at')->first();
        $shiftSeed = DB::table('shifts')
            ->where('tenant_id', $tenant->id)
            ->where('branch_id', $branch->id)
            ->orderByDesc('opened_at')
            ->first();

        if (!$shiftSeed && $seedUser) {
            $shiftId = (string) Str::uuid();
            DB::table('shifts')->insert([
                'id' => $shiftId,
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'user_id' => $seedUser->id,
                'opened_at' => $now->subHours(8),
                'closed_at' => $now->subHours(1),
                'opening_cash' => 100000,
                'closing_cash' => 120000,
                'system_cash_total' => 120000,
                'difference' => 0,
                'status' => 'closed',
            ]);

            $shiftSeed = DB::table('shifts')->where('id', $shiftId)->first();
        }

        if (!DB::table('payment_methods')->where('tenant_id', $tenant->id)->exists()) {
            DB::table('payment_methods')->insert([
                [
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'name' => 'Cash',
                    'method_type' => 'cash',
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'name' => 'Debit',
                    'method_type' => 'card',
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'name' => 'QRIS',
                    'method_type' => 'qr',
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);
        }

        $existingVariantProductIds = DB::table('product_variants')
            ->where('tenant_id', $tenant->id)
            ->pluck('product_id')
            ->all();

        $existingVariantMap = array_fill_keys($existingVariantProductIds, true);
        $variants = [];

        foreach ($allProducts as $product) {
            if (isset($existingVariantMap[$product->id])) {
                continue;
            }
            $sku = $product->sku ? $product->sku . '-V1' : 'SKU-' . strtoupper(Str::random(6)) . '-V1';
            $variants[] = [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'product_id' => $product->id,
                'sku' => $sku,
                'barcode' => null,
                'name' => $product->name . ' Variant',
                'attributes' => json_encode(['size' => 'standard']),
                'price_override' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (!empty($variants)) {
            DB::table('product_variants')->insert($variants);
        }

        $variantsByProduct = DB::table('product_variants')
            ->where('tenant_id', $tenant->id)
            ->get()
            ->keyBy('product_id');

        if ($shiftSeed && !DB::table('cash_flows')->where('tenant_id', $tenant->id)->exists()) {
            DB::table('cash_flows')->insert([
                [
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'shift_id' => $shiftSeed->id,
                    'branch_id' => $branch->id,
                    'type' => 'cash_in',
                    'amount' => 50000,
                    'note' => 'Kas masuk seed data',
                    'created_at' => $now,
                ],
                [
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'shift_id' => $shiftSeed->id,
                    'branch_id' => $branch->id,
                    'type' => 'cash_out',
                    'amount' => 10000,
                    'note' => 'Kas keluar seed data',
                    'created_at' => $now,
                ],
            ]);
        }

        if ($register) {
            DB::table('sales')
                ->where('tenant_id', $tenant->id)
                ->whereNull('register_id')
                ->update(['register_id' => $register->id]);
        }

        $methodMap = DB::table('payment_methods')
            ->where('tenant_id', $tenant->id)
            ->pluck('id', 'method_type');

        $paymentsNeedingMethod = DB::table('payments')
            ->where('tenant_id', $tenant->id)
            ->whereNull('payment_method_id')
            ->get(['id', 'method']);

        foreach ($paymentsNeedingMethod as $payment) {
            $methodId = $methodMap[$payment->method] ?? $methodMap->first();
            if ($methodId) {
                DB::table('payments')
                    ->where('id', $payment->id)
                    ->update(['payment_method_id' => $methodId]);
            }
        }

        $saleItemsMissingVariant = DB::table('sale_items')
            ->where('tenant_id', $tenant->id)
            ->whereNull('product_variant_id')
            ->get(['id', 'product_id']);

        foreach ($saleItemsMissingVariant as $saleItem) {
            $variantId = $variantsByProduct->get($saleItem->product_id)?->id;
            if ($variantId) {
                DB::table('sale_items')
                    ->where('id', $saleItem->id)
                    ->update(['product_variant_id' => $variantId]);
            }
        }

        $inventoryMissingVariant = DB::table('inventory_balances')
            ->where('tenant_id', $tenant->id)
            ->whereNull('product_variant_id')
            ->get(['tenant_id', 'location_id', 'product_id']);

        foreach ($inventoryMissingVariant as $row) {
            $variantId = $variantsByProduct->get($row->product_id)?->id;
            if ($variantId) {
                DB::table('inventory_balances')
                    ->where('tenant_id', $tenant->id)
                    ->where('location_id', $row->location_id)
                    ->where('product_id', $row->product_id)
                    ->whereNull('product_variant_id')
                    ->update(['product_variant_id' => $variantId]);
            }
        }

        $ledgerMissingVariant = DB::table('stock_ledger')
            ->where('tenant_id', $tenant->id)
            ->whereNull('product_variant_id')
            ->get(['id', 'product_id']);

        foreach ($ledgerMissingVariant as $row) {
            $variantId = $variantsByProduct->get($row->product_id)?->id;
            if ($variantId) {
                DB::table('stock_ledger')
                    ->where('id', $row->id)
                    ->update(['product_variant_id' => $variantId]);
            }
        }

        $priceListId = DB::table('price_lists')
            ->where('tenant_id', $tenant->id)
            ->value('id');

        if (!$priceListId) {
            $priceListId = (string) Str::uuid();
            DB::table('price_lists')->insert([
                'id' => $priceListId,
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'name' => 'Harga Reguler',
                'is_active' => true,
                'starts_at' => $now,
                'ends_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (!DB::table('pricing_rules')->where('tenant_id', $tenant->id)->exists()) {
            $rules = [];
            foreach ($products as $product) {
                $variant = $variantsByProduct->get($product->id);
                $rules[] = [
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'price_list_id' => $priceListId,
                    'product_id' => $product->id,
                    'product_variant_id' => $variant?->id,
                    'price' => $product->price,
                    'min_qty' => 1,
                    'starts_at' => $now,
                    'ends_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('pricing_rules')->insert($rules);
        }

        if (!DB::table('vouchers')->where('tenant_id', $tenant->id)->exists()) {
            DB::table('vouchers')->insert([
                [
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'code' => 'WELCOME10',
                    'value' => 10000,
                    'status' => 'active',
                    'starts_at' => $now->toDateString(),
                    'ends_at' => $now->addDays(90)->toDateString(),
                    'created_at' => $now,
                ],
                [
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'code' => 'HEMAT5',
                    'value' => 5000,
                    'status' => 'active',
                    'starts_at' => $now->toDateString(),
                    'ends_at' => $now->addDays(30)->toDateString(),
                    'created_at' => $now,
                ],
            ]);
        }

        if ($customers->isEmpty()) {
            $seedCustomers = [
                [
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'name' => 'Customer Umum',
                    'phone' => null,
                    'email' => null,
                    'address' => null,
                    'created_at' => $now,
                ],
                [
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'name' => 'Customer Member',
                    'phone' => '081200000001',
                    'email' => 'member@kalaka.test',
                    'address' => 'Jakarta',
                    'created_at' => $now,
                ],
            ];

            DB::table('customers')->insert($seedCustomers);

            $customers = DB::table('customers')
                ->where('tenant_id', $tenant->id)
                ->orderBy('created_at')
                ->limit(2)
                ->get();
        }

        if ($customers->isNotEmpty() && !DB::table('loyalty_accounts')->where('tenant_id', $tenant->id)->exists()) {
            $loyaltyRows = [];
            foreach ($customers as $customer) {
                $loyaltyRows[] = [
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'customer_id' => $customer->id,
                    'loyalty_tier_id' => null,
                    'points' => 100,
                    'created_at' => $now,
                ];
            }
            DB::table('loyalty_accounts')->insert($loyaltyRows);
        }

        if (!DB::table('inventory_balances')->where('tenant_id', $tenant->id)->exists()) {
            $inventoryRows = [];
            foreach ($products as $product) {
                $variant = $variantsByProduct->get($product->id);
                $inventoryRows[] = [
                    'tenant_id' => $tenant->id,
                    'location_id' => $location->id,
                    'product_id' => $product->id,
                    'product_variant_id' => $variant?->id,
                    'qty_on_hand' => 50,
                ];
            }
            DB::table('inventory_balances')->insert($inventoryRows);
        }

        if (!DB::table('stock_ledger')->where('tenant_id', $tenant->id)->exists()) {
            $ledgerRows = [];
            foreach ($products as $product) {
                $ledgerRows[] = [
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'product_id' => $product->id,
                    'product_variant_id' => $variantsByProduct->get($product->id)?->id,
                    'location_id' => $location->id,
                    'reference_type' => 'OPENING',
                    'reference_id' => (string) Str::uuid(),
                    'qty_in' => 50,
                    'qty_out' => 0,
                    'balance_after' => 50,
                    'created_at' => $now,
                    'created_by' => null,
                ];
            }
            DB::table('stock_ledger')->insert($ledgerRows);
        }

        $seedSaleId = null;
        if (!DB::table('sales')->where('tenant_id', $tenant->id)->exists()) {
            $shift = DB::table('shifts')
                ->where('tenant_id', $tenant->id)
                ->where('branch_id', $branch->id)
                ->orderByDesc('opened_at')
                ->first();

            $user = DB::table('users')->where('tenant_id', $tenant->id)->orderBy('created_at')->first();
            $customer = $customers->first();

            if ($shift && $user && $register) {
                $saleId = (string) Str::uuid();
                $saleSubtotal = 2 * $products[0]['price'];
                $saleTax = round($saleSubtotal * 0.11, 2);
                $saleTotal = $saleSubtotal + $saleTax;

                $seedSaleId = $saleId;

                DB::table('sales')->insert([
                    'id' => $saleId,
                    'tenant_id' => $tenant->id,
                    'branch_id' => $branch->id,
                    'shift_id' => $shift->id,
                    'customer_id' => $customer?->id,
                    'subtotal' => $saleSubtotal,
                    'discount' => 0,
                    'tax' => $saleTax,
                    'total' => $saleTotal,
                    'status' => 'paid',
                    'idempotency_key' => 'seed-' . Str::uuid(),
                    'created_by' => $user->id,
                    'voided_by' => null,
                    'created_at' => $now,
                ]);

                DB::table('sale_items')->insert([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'sale_id' => $saleId,
                    'product_id' => $products[0]['id'],
                    'product_variant_id' => $variantsByProduct->get($products[0]['id'])?->id,
                    'qty' => 2,
                    'price' => $products[0]['price'],
                    'discount' => 0,
                    'hpp_snapshot' => $products[0]['hpp_average'] ?? 0,
                ]);

                DB::table('payments')->insert([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'sale_id' => $saleId,
                    'account_id' => (string) Str::uuid(),
                    'method' => 'cash',
                    'amount' => $saleTotal,
                    'payment_method_id' => DB::table('payment_methods')
                        ->where('tenant_id', $tenant->id)
                        ->where('method_type', 'cash')
                        ->value('id'),
                ]);

                DB::table('stock_ledger')->insert([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'product_id' => $products[0]['id'],
                    'product_variant_id' => $variantsByProduct->get($products[0]['id'])?->id,
                    'location_id' => $location->id,
                    'reference_type' => 'SALE',
                    'reference_id' => $saleId,
                    'qty_in' => 0,
                    'qty_out' => 2,
                    'balance_after' => 48,
                    'created_at' => $now,
                    'created_by' => $user->id,
                ]);
            }
        }

        if (!$seedSaleId) {
            $seedSaleId = DB::table('sales')
                ->where('tenant_id', $tenant->id)
                ->orderBy('created_at')
                ->value('id');
        }

        if ($seedSaleId && !DB::table('discounts')->where('tenant_id', $tenant->id)->exists()) {
            DB::table('discounts')->insert([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'sale_id' => $seedSaleId,
                'type' => 'amount',
                'value' => 5000,
                'notes' => 'Diskon seed data',
                'created_at' => $now,
            ]);
        }

        if ($seedSaleId && DB::table('discounts')->where('tenant_id', $tenant->id)->count() < 2) {
            DB::table('discounts')->insert([
                [
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'sale_id' => $seedSaleId,
                    'type' => 'percent',
                    'value' => 10,
                    'notes' => 'Diskon 10 persen seed data',
                    'created_at' => $now,
                ],
                [
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'sale_id' => $seedSaleId,
                    'type' => 'amount',
                    'value' => 2000,
                    'notes' => 'Diskon nominal seed data',
                    'created_at' => $now,
                ],
            ]);
        }

        if ($seedSaleId && !DB::table('tax_lines')->where('tenant_id', $tenant->id)->exists()) {
            $tax = DB::table('taxes')
                ->where('tenant_id', $tenant->id)
                ->where('is_active', true)
                ->orderBy('created_at')
                ->first();

            if (!$tax) {
                $taxId = (string) Str::uuid();
                DB::table('taxes')->insert([
                    'id' => $taxId,
                    'tenant_id' => $tenant->id,
                    'name' => 'PPN',
                    'description' => 'Pajak Pertambahan Nilai',
                    'rate' => 11,
                    'is_active' => true,
                    'created_at' => $now,
                ]);
                $tax = (object) [
                    'id' => $taxId,
                    'rate' => 11,
                ];
            }

            $saleTax = DB::table('sales')
                ->where('id', $seedSaleId)
                ->value('tax') ?? 0;

            if ($tax) {
                DB::table('tax_lines')->insert([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'sale_id' => $seedSaleId,
                    'tax_id' => $tax->id,
                    'rate' => $tax->rate,
                    'amount' => $saleTax,
                    'created_at' => $now,
                ]);
            }
        }

        if ($seedSaleId && !DB::table('refunds')->where('tenant_id', $tenant->id)->exists()) {
            $saleItem = DB::table('sale_items')
                ->where('sale_id', $seedSaleId)
                ->orderBy('id')
                ->first();

            $user = DB::table('users')
                ->where('tenant_id', $tenant->id)
                ->orderBy('created_at')
                ->first();

            if ($saleItem && $user) {
                $refundId = (string) Str::uuid();

                DB::table('refunds')->insert([
                    'id' => $refundId,
                    'tenant_id' => $tenant->id,
                    'sale_id' => $seedSaleId,
                    'branch_id' => $branch->id,
                    'created_by' => $user->id,
                    'total' => $saleItem->price,
                    'status' => 'completed',
                    'reason' => 'Refund seed data',
                    'created_at' => $now,
                ]);

                DB::table('refund_items')->insert([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'refund_id' => $refundId,
                    'product_id' => $saleItem->product_id,
                    'qty' => 1,
                    'amount' => $saleItem->price,
                ]);

                DB::table('stock_ledger')->insert([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'product_id' => $saleItem->product_id,
                    'product_variant_id' => $saleItem->product_variant_id,
                    'location_id' => $location->id,
                    'reference_type' => 'REFUND',
                    'reference_id' => $refundId,
                    'qty_in' => 1,
                    'qty_out' => 0,
                    'balance_after' => 49,
                    'created_at' => $now,
                    'created_by' => $user->id,
                ]);
            }
        }

        if (DB::table('refunds')->where('tenant_id', $tenant->id)->count() < 2) {
            $shift = DB::table('shifts')
                ->where('tenant_id', $tenant->id)
                ->where('branch_id', $branch->id)
                ->orderByDesc('opened_at')
                ->first();

            $user = DB::table('users')
                ->where('tenant_id', $tenant->id)
                ->orderBy('created_at')
                ->first();

            $productA = $products[0] ?? null;
            $productB = $products[1] ?? null;

            if ($shift && $user && $register && $productA && $productB) {
                $saleId = (string) Str::uuid();
                $subtotal = $productA->price + $productB->price;
                $tax = round($subtotal * 0.11, 2);
                $total = $subtotal + $tax;

                DB::table('sales')->insert([
                    'id' => $saleId,
                    'tenant_id' => $tenant->id,
                    'branch_id' => $branch->id,
                    'shift_id' => $shift->id,
                    'register_id' => $register->id,
                    'customer_id' => $customers->first()?->id,
                    'subtotal' => $subtotal,
                    'discount' => 0,
                    'tax' => $tax,
                    'total' => $total,
                    'status' => 'paid',
                    'idempotency_key' => 'seed-partial-' . Str::uuid(),
                    'created_by' => $user->id,
                    'voided_by' => null,
                    'created_at' => $now,
                ]);

                DB::table('sale_items')->insert([
                    [
                        'id' => (string) Str::uuid(),
                        'tenant_id' => $tenant->id,
                        'sale_id' => $saleId,
                        'product_id' => $productA->id,
                        'product_variant_id' => $variantsByProduct->get($productA->id)?->id,
                        'qty' => 1,
                        'price' => $productA->price,
                        'discount' => 0,
                        'hpp_snapshot' => $productA->hpp_average ?? 0,
                    ],
                    [
                        'id' => (string) Str::uuid(),
                        'tenant_id' => $tenant->id,
                        'sale_id' => $saleId,
                        'product_id' => $productB->id,
                        'product_variant_id' => $variantsByProduct->get($productB->id)?->id,
                        'qty' => 1,
                        'price' => $productB->price,
                        'discount' => 0,
                        'hpp_snapshot' => $productB->hpp_average ?? 0,
                    ],
                ]);

                DB::table('payments')->insert([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'sale_id' => $saleId,
                    'account_id' => (string) Str::uuid(),
                    'method' => 'cash',
                    'amount' => $total,
                    'payment_method_id' => DB::table('payment_methods')
                        ->where('tenant_id', $tenant->id)
                        ->where('method_type', 'cash')
                        ->value('id'),
                ]);

                DB::table('stock_ledger')->insert([
                    [
                        'id' => (string) Str::uuid(),
                        'tenant_id' => $tenant->id,
                        'product_id' => $productA->id,
                        'product_variant_id' => $variantsByProduct->get($productA->id)?->id,
                        'location_id' => $location->id,
                        'reference_type' => 'SALE',
                        'reference_id' => $saleId,
                        'qty_in' => 0,
                        'qty_out' => 1,
                        'balance_after' => 49,
                        'created_at' => $now,
                        'created_by' => $user->id,
                    ],
                    [
                        'id' => (string) Str::uuid(),
                        'tenant_id' => $tenant->id,
                        'product_id' => $productB->id,
                        'product_variant_id' => $variantsByProduct->get($productB->id)?->id,
                        'location_id' => $location->id,
                        'reference_type' => 'SALE',
                        'reference_id' => $saleId,
                        'qty_in' => 0,
                        'qty_out' => 1,
                        'balance_after' => 49,
                        'created_at' => $now,
                        'created_by' => $user->id,
                    ],
                ]);

                $refundId = (string) Str::uuid();

                DB::table('refunds')->insert([
                    'id' => $refundId,
                    'tenant_id' => $tenant->id,
                    'sale_id' => $saleId,
                    'branch_id' => $branch->id,
                    'created_by' => $user->id,
                    'total' => $productB->price,
                    'status' => 'completed',
                    'reason' => 'Refund parsial seed data',
                    'created_at' => $now,
                ]);

                DB::table('refund_items')->insert([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'refund_id' => $refundId,
                    'product_id' => $productB->id,
                    'qty' => 1,
                    'amount' => $productB->price,
                ]);

                DB::table('stock_ledger')->insert([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'product_id' => $productB->id,
                    'product_variant_id' => $variantsByProduct->get($productB->id)?->id,
                    'location_id' => $location->id,
                    'reference_type' => 'REFUND',
                    'reference_id' => $refundId,
                    'qty_in' => 1,
                    'qty_out' => 0,
                    'balance_after' => 50,
                    'created_at' => $now,
                    'created_by' => $user->id,
                ]);
            }
        }

        if (!DB::table('daily_sales_summaries')->where('tenant_id', $tenant->id)->exists()) {
            $salesAgg = DB::table('sales')
                ->where('tenant_id', $tenant->id)
                ->selectRaw('COUNT(*) as transaction_count, COALESCE(SUM(total), 0) as total_sales, COALESCE(SUM(tax), 0) as total_tax, COALESCE(SUM(discount), 0) as total_discount')
                ->first();

            DB::table('daily_sales_summaries')->insert([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'date' => $now->toDateString(),
                'total_sales' => $salesAgg->total_sales ?? 0,
                'total_tax' => $salesAgg->total_tax ?? 0,
                'total_discount' => $salesAgg->total_discount ?? 0,
                'total_cost' => 0,
                'transaction_count' => $salesAgg->transaction_count ?? 0,
                'updated_at' => $now,
            ]);
        }

        if (!DB::table('inventory_summaries')->where('tenant_id', $tenant->id)->exists()) {
            $summaryRows = DB::table('inventory_balances')
                ->where('tenant_id', $tenant->id)
                ->get()
                ->map(function ($row) use ($tenant, $now) {
                    return [
                        'id' => (string) Str::uuid(),
                        'tenant_id' => $tenant->id,
                        'location_id' => $row->location_id,
                        'product_id' => $row->product_id,
                        'current_qty' => $row->qty_on_hand,
                        'current_value' => 0,
                        'updated_at' => $now,
                    ];
                })
                ->all();

            if (!empty($summaryRows)) {
                DB::table('inventory_summaries')->insert($summaryRows);
            }
        }
    }
}
