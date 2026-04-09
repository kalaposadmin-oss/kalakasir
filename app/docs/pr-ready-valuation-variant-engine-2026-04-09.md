## What Changed
- threads product_variant_id through valuation replay and cost lookup paths so variant products rebuild exact layer, allocation, and cost-state buckets
- rejects ambiguous legacy replay rows and missing variant context for variant products instead of silently mapping to null buckets
- adds purchase_item_id and source_item_id support for purchase-return valuation so FIFO can resolve the correct originating purchase item or fail safely
- adds PurchaseHistoryBackfillService and PurchaseReturnValuationException to separate safe backfill resolution from runtime failure handling

## Validation
- focused suite passes:
  - php artisan test tests/Feature/ValuationGapCoverageTest.php tests/Feature/PurchaseReturnLegacyBackfillTest.php tests/Feature/PurchaseReturnConsistencyTest.php --compact

## Out Of Scope
- checkout and void runtime variant flows
- auto-consume on sell variant posting
- broader web/API parity work outside valuation and purchase-return support
