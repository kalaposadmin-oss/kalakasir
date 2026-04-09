## Summary
- add the operational backoffice and POS page set for dashboards, reports, products, purchasing, receiving, inventory, stock operations, and production batch monitoring
- align operational pages with canonical portal navigation and the newer shared shell established in the earlier portal foundation commit
- validate the slice with the existing dashboard and operational smoke suites

## Testing
- php artisan test tests/Feature/WebDashboardSmokeTest.php tests/Feature/WebOperationalPagesSmokeTest.php --compact --colors=never
