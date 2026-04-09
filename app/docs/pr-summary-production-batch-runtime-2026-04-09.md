## Summary
- complete production batch runtime flow for draft update, cancel, post, anomaly summary, and history/export views
- persist actual yield, variance metadata, and audit/anomaly side effects without duplicating ledger or observability records on replay
- cover production batch lifecycle, stock movement, anomaly thresholds, and history filtering with the focused quick-flow suite

## Testing
- php artisan test tests/Feature/ProductionBatchQuickFlowTest.php --compact
