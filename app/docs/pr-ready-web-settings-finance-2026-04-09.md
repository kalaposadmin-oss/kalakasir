## What Changed
- admin settings and finance pages now exist for analytics, users, tax/promo, finance summary, valuation settings/audit, bank accounts, and chart-of-accounts detail flows
- supporting analytics, bank account, chart-of-account, and fee product controllers now align with the backoffice pages rendered by the current web experience
- focused smoke coverage confirms the settings and finance surfaces render the expected management sections

## Validation
- focused suite passes:
  - php artisan test tests/Feature/WebSettingsFinanceSmokeTest.php --compact --colors=never

## Out Of Scope
- residual backend glue, observability jobs, and ci/readme/docs sweep
