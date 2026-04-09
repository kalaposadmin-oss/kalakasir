## What Changed
- operational page views now exist for the main dashboard/report surfaces plus products, purchasing, receiving, sales history, inventory, stock operations, suppliers, and production batch monitoring
- the pages adopt canonical portal paths and the current backoffice shell/navigation conventions rather than the older wrapper structure
- focused smoke coverage confirms the operational routes render the expected controls and navigation affordances

## Validation
- focused suite passes:
  - php artisan test tests/Feature/WebDashboardSmokeTest.php tests/Feature/WebOperationalPagesSmokeTest.php --compact --colors=never

## Out Of Scope
- settings, finance, valuation audit/settings, and subscription/settings pages
- support/help/marketing informational pages
