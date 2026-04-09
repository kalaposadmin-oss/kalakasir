# Commit Message Metrics and Subscription Cache Wiring 2026-04-09

## Subject

`feat(app): add analytics finance sales metrics services`

## Body

```text
Add the remaining metrics services used by analytics, finance summary,
and sales history APIs, and wire subscription plan change cache invalidation.

- add tenant-scoped analytics and sales history aggregate services
- add GL-based finance report aggregation service
- register subscription plan change listener to refresh feature entitlements
- add focused tenant scoping and refund netting tests

Validation:
- php artisan test tests/Feature/AnalyticsTenantScopingTest.php tests/Feature/FinanceReportRefundNettingTest.php tests/Feature/SalesHistoryTenantScopingTest.php
```