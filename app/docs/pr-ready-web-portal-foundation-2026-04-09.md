## What Changed
- session configuration now infers a safe shared cookie domain from configured POS and backoffice hosts when no explicit SESSION_DOMAIN override is present
- the shared Blade shell and menu drawer now adapt to portal context, role-based shortcuts, portal-aware back navigation, and backoffice layout width requirements
- menu entry pages now route users to canonical portal paths instead of legacy absolute links
- route wiring supports the canonical portal foundation exercised by portal access, auth/onboarding, and dashboard smoke tests

## Validation
- focused suite passes:
  - php artisan test tests/Feature/PortalWebAccessTest.php tests/Feature/WebAuthOnboardingSmokeTest.php tests/Feature/WebDashboardSmokeTest.php --compact --colors=never

## Out Of Scope
- individual operational web pages such as products, receiving, sales history, and void/refund wrappers
- ci/readme cleanup
