## Summary
- add the settings, finance, analytics, valuation audit/settings, tax promo, users, and chart-of-accounts admin surfaces needed by the backoffice web flow
- align supporting analytics and finance-related controllers with the current admin page contracts
- validate the slice with the focused settings and finance smoke suite

## Testing
- php artisan test tests/Feature/WebSettingsFinanceSmokeTest.php --compact --colors=never
