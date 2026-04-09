## What Changed
- ProductionBatchService now supports draft creation and rescaling, manual input override, guarded cancellation with reason, post idempotency, actual yield variance tracking, and anomaly/audit synchronization
- ProductionBatchController now exposes richer batch detail, history filters, anomaly history, and problematic batch export behavior aligned with the new runtime contract
- ProductionBatch and ProductionBatchItem models carry the additional runtime metadata required for actual yield and variance-aware workflows
- focused tests cover the full batch lifecycle, inventory posting, idempotent replay, anomaly generation, anomaly resolution, and CSV export behavior

## Validation
- focused suite passes:
  - php artisan test tests/Feature/ProductionBatchQuickFlowTest.php --compact

## Out Of Scope
- stock adjustment and stock transfer runtime
- broader web navigation and portal work
- valuation async/report UI changes outside batch production
