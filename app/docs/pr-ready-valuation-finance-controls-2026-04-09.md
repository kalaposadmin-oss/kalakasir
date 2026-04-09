## What Changed
- finance and valuation controllers now return the richer payload expected by the web valuation and finance control screens, including request/runtime metadata for async operations
- DailyFinanceControlService now supports the dashboard, snapshot, export, and backdated business-date scenarios covered by the focused regression suite
- valuation async operation tests now seed an active plan/subscription context so rebuild/export queue flows are validated against the current gating rules
- valuation web smoke coverage remains aligned with the settings and audit screens exposed by the updated backend endpoints

## Validation
- focused suite passes:
  - php artisan test tests/Feature/FinanceControlDashboardTest.php tests/Feature/FinancialControlsPhase1Test.php tests/Feature/ValuationAsyncOperationsTest.php tests/Feature/ValuationWebSmokeTest.php --compact --colors=never

## Out Of Scope
- web portal/navigation shell changes
- broader analytics and sales history refactors
- ci/readme cleanup
