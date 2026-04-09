## Summary
- harden stock adjustment and stock transfer APIs with tenant-scoped validation for locations, branches, products, variants, and production batch references
- extend stock adjustment posting to capture variant-aware item context, waste metadata, and batch-linked draft fields consistently
- add stock card summary totals and tighten inventory alert aggregate grouping while keeping backdated write-flow behavior green

## Testing
- php artisan test tests/Feature/StockAdjustmentWasteTaggingTest.php tests/Feature/StockAdjustmentTransactionDateValidationTest.php tests/Feature/StockTransferTransactionDateValidationTest.php tests/Feature/StockTransferTenantValidationTest.php tests/Feature/WriteFlowTransactionDateHappyPathTest.php --compact --colors=never
