# PR Summary POS Variant Register Schema Foundation 2026-04-09

## Scope

PR ini hanya membawa fondasi schema untuk register POS, payment method, dan bucket variant-aware di inventory dan valuation tables. Runtime product/create/checkout/void variant tetap di luar scope dan akan masuk batch lanjutan.

## Included Files

- `database/migrations/2026_03_09_100000_create_registers_table.php`
- `database/migrations/2026_03_09_100010_create_product_variants_table.php`
- `database/migrations/2026_03_09_100040_create_payment_methods_table.php`
- `database/migrations/2026_03_09_100050_add_payment_method_id_to_payments_table.php`
- `database/migrations/2026_03_09_100060_add_register_id_to_sales_table.php`
- `database/migrations/2026_03_09_110000_add_product_variant_id_to_sale_items_table.php`
- `database/migrations/2026_03_09_110010_add_product_variant_id_to_purchase_items_table.php`
- `database/migrations/2026_03_09_110020_add_product_variant_id_to_inventory_balances_table.php`
- `database/migrations/2026_03_09_110030_add_product_variant_id_to_stock_ledger_table.php`
- `database/migrations/2026_03_10_090000_add_pos_indexes.php`
- `database/migrations/2026_04_06_120000_add_product_variant_id_to_valuation_tables.php`
- `database/migrations/2026_04_06_130000_add_product_variant_id_to_item_source_tables.php`
- `database/migrations/2026_04_06_140000_fix_inventory_balance_variant_bucket_uniqueness.php`

## What Changed

- menambahkan tabel `registers`, `product_variants`, dan `payment_methods`
- menambahkan foreign key `register_id` dan `payment_method_id` pada flow transaksi yang relevan
- menambahkan `product_variant_id` ke sale, purchase, stock ledger, inventory, valuation, dan source-item tables
- menambahkan index POS/variant untuk lookup transaksi dan bucket inventory
- memperbaiki uniqueness bucket `inventory_balances` agar null variant dan variant spesifik tidak saling bentrok

## Validation

- seluruh migration file lolos parse PHP
- `php artisan test tests/Feature/VariantProductInitialStockLifecycleTest.php` passed pada worktree saat ini sebagai sinyal kompatibilitas foundation dengan runtime variant yang masih akan diisolasi pada batch berikutnya

## Explicitly Excluded

- runtime variant handling di product create/update
- checkout/void/purchase variant behavior
- price list, pricing rules, voucher, dan loyalty runtime