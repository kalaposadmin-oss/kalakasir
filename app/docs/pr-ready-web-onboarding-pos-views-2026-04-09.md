## What Changed
- onboarding pages now align with the multi-step registration and first-product flow expected by the current portal experience
- POS-adjacent wrapper pages such as checkout, shift open/close, receipt preview, payment method, sales detail, and void/refund now render the portal-aware management shortcuts and canonical navigation patterns
- the legacy welcome wrapper is removed in favor of the newer portal entry behavior already covered by the smoke suite

## Validation
- focused suite passes:
  - php artisan test tests/Feature/WebAuthOnboardingSmokeTest.php tests/Feature/PortalWebAccessTest.php tests/Feature/WebDashboardSmokeTest.php --compact --colors=never

## Out Of Scope
- backoffice operational pages such as products, receiving, inventory, and sales history
- settings and finance backoffice views
