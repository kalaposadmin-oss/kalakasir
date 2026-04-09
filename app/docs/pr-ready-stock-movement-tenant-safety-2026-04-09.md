## What Changed
- StockAdjustmentController and StockTransferController now validate tenant ownership for branches, locations, products, variants, and linked production batches instead of relying on global exists rules
- StockAdjustmentService and StockTransferService now carry the richer movement context needed for variant-aware inventory operations and batch-linked adjustment metadata
- StockCardController now returns summary totals for the filtered movement window, and InventoryAlertController aggregates inventory and sales per tenant-safe grouping key
- focused tests cover waste tagging, tenant validation, transaction-date validation, and happy-path backdated write flows for transfer and adjustment endpoints

## Validation
- focused suite passes:
  - php artisan test tests/Feature/StockAdjustmentWasteTaggingTest.php tests/Feature/StockAdjustmentTransactionDateValidationTest.php tests/Feature/StockTransferTransactionDateValidationTest.php tests/Feature/StockTransferTenantValidationTest.php tests/Feature/WriteFlowTransactionDateHappyPathTest.php --compact --colors=never

## Out Of Scope
- valuation async/report endpoints
- web portal and navigation refactors
- broader analytics and sales history changes
