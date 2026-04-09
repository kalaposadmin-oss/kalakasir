# PR Ready POS Variant Register Schema Foundation 2026-04-09

## PR Title

Add POS register and variant schema foundation

## Commit Message

```text
feat(app): add pos register and variant schema foundation

Add the schema layer required for POS registers, payment methods, and
variant-aware inventory buckets.

- add registers, product_variants, and payment_methods tables
- add register_id and payment_method_id references for transaction flows
- add product_variant_id across transactional, inventory, and valuation tables
- add supporting POS and variant indexes plus null-safe inventory bucket uniqueness

Validated with migration syntax checks and the existing variant lifecycle test in the current worktree.
```

## PR Description

```markdown
## Scope

This PR isolates the schema foundation for POS registers, payment methods, and variant-aware inventory buckets. It intentionally keeps runtime product, checkout, void, and purchase variant handling out of scope.

## Included Files

- database/migrations/2026_03_09_100000_create_registers_table.php
- database/migrations/2026_03_09_100010_create_product_variants_table.php
- database/migrations/2026_03_09_100040_create_payment_methods_table.php
- database/migrations/2026_03_09_100050_add_payment_method_id_to_payments_table.php
- database/migrations/2026_03_09_100060_add_register_id_to_sales_table.php
- database/migrations/2026_03_09_110000_add_product_variant_id_to_sale_items_table.php
- database/migrations/2026_03_09_110010_add_product_variant_id_to_purchase_items_table.php
- database/migrations/2026_03_09_110020_add_product_variant_id_to_inventory_balances_table.php
- database/migrations/2026_03_09_110030_add_product_variant_id_to_stock_ledger_table.php
- database/migrations/2026_03_10_090000_add_pos_indexes.php
- database/migrations/2026_04_06_120000_add_product_variant_id_to_valuation_tables.php
- database/migrations/2026_04_06_130000_add_product_variant_id_to_item_source_tables.php
- database/migrations/2026_04_06_140000_fix_inventory_balance_variant_bucket_uniqueness.php

## What Changed

- add register, product variant, and payment method schema primitives
- add variant references across transactional, inventory, and valuation tables
- add POS and variant indexes for query performance
- switch inventory balance uniqueness to a null-safe variant bucket index

## Validation

- migration files pass PHP syntax checks
- php artisan test tests/Feature/VariantProductInitialStockLifecycleTest.php passed in the current worktree

## Explicitly Excluded

- runtime product and variant CRUD behavior
- checkout, void, and purchase variant execution logic
- pricing rules, vouchers, and loyalty runtime
```