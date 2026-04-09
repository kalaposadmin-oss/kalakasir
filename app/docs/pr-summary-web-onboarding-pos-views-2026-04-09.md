## Summary
- refresh onboarding and POS flow Blade views around canonical portal navigation, management shortcuts, and first-product onboarding UX
- replace legacy wrapper-heavy POS view structures with portal-aware versions for checkout-adjacent pages like shift, receipt, payment method, sales detail, and void/refund
- validate the slice with the existing portal, onboarding, and dashboard smoke suites

## Testing
- php artisan test tests/Feature/WebAuthOnboardingSmokeTest.php tests/Feature/PortalWebAccessTest.php tests/Feature/WebDashboardSmokeTest.php --compact --colors=never
