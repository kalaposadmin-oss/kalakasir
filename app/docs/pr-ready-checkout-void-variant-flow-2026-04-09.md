## What Changed
- AutoConsumeOnSellService now resolves raw material product variants explicitly, requires variant ids for variant raw materials, and records sale_recipe_input rows with reference_item_id and variant-aware ledger/balance updates
- VoidService and VoidSaleController now reverse sale and recipe consumption against the correct variant buckets, preserve reference_item_id context, and keep request-id aware audit payloads
- SaleCompleted and SaleVoided events plus audit listeners now carry request_id so checkout and void audit logs stay correlated with API requests
- focused tests cover variant raw material checkout, exact bucket isolation through checkout plus void, and manager PIN / cross-tenant void guards

## Validation
- focused suite passes:
  - php artisan test tests/Feature/AutoConsumeOnSellCheckoutFlowTest.php tests/Feature/VariantProductInitialStockLifecycleTest.php tests/Feature/VoidManagerPinEnforcementTest.php --compact

## Out Of Scope
- broader web UI changes
- non-checkout valuation and purchase-return flows already isolated in the previous commit
