## Summary
- make InventoryValuationService variant-aware across replay, inbound cost resolution, FIFO issue/reversal, and purchase-return source item mapping
- add purchase-return valuation support objects and schema columns needed to backfill legacy purchase item context safely
- cover variant replay ambiguity, exact variant bucket rebuilds, and purchase-return legacy backfill flows with focused feature tests

## Testing
- php artisan test tests/Feature/ValuationGapCoverageTest.php tests/Feature/PurchaseReturnLegacyBackfillTest.php tests/Feature/PurchaseReturnConsistencyTest.php --compact
