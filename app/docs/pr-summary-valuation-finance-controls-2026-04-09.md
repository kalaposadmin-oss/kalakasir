## Summary
- tighten valuation report and finance control API responses, including async rebuild/export status handling and runtime metadata
- extend finance control dashboard coverage for daily KPIs, snapshots, exports, and backdated sales treatment
- keep valuation settings and audit web pages aligned with the backend contract through focused smoke and async tests

## Testing
- php artisan test tests/Feature/FinanceControlDashboardTest.php tests/Feature/FinancialControlsPhase1Test.php tests/Feature/ValuationAsyncOperationsTest.php tests/Feature/ValuationWebSmokeTest.php --compact --colors=never
