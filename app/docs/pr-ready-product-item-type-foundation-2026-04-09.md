# PR Ready Product Item Type Foundation 2026-04-09

## PR Title

Add product item type backfill foundation

## Commit Message

```text
feat(app): add product item type backfill foundation

Add the foundational product_mode and item_type support needed for staged
catalog cleanup and backfill.

- add product_mode and item_type schema support with backfill checkpoints
- add product item type mapper and validator helpers
- add checkpointed backfill job for staged product item type migration
- include focused tests for backfill, migration safety, mapper, and validator rules

Validated with focused product item type feature tests.
```

## PR Description

```markdown
## Scope

This PR isolates the foundational product item type schema and backfill layer. It intentionally keeps controller-level catalog API changes, material APIs, and variant lifecycle work out of scope.

## Included Files

- app/Jobs/BackfillProductItemTypeJob.php
- app/Services/ProductItemTypeMapper.php
- app/Services/ProductItemTypeValidator.php
- database/migrations/2026_03_03_000001_add_product_mode_fields_to_products_table.php
- database/migrations/2026_03_13_100000_add_item_type_to_products_table.php
- database/migrations/2026_03_13_100100_create_product_item_type_backfill_checkpoints_table.php
- tests/Feature/ProductItemTypeBackfillGateTest.php
- tests/Feature/ProductItemTypeSupportTest.php

## What Changed

- add product_mode, variants, bom_items, and bundle_items schema fields to products
- add item_type schema support and a checkpoint table for staged backfill
- add a checkpointed backfill job guarded by a cache lock per tenant scope
- add mapper and validator helpers for item type defaults and validation rules

## Validation

- php artisan test tests/Feature/ProductItemTypeBackfillGateTest.php tests/Feature/ProductItemTypeSupportTest.php passed

## Explicitly Excluded

- ProductController and catalog API contract changes
- MaterialController and material-specific schema
- variant/register/discount/loyalty schema batches
```