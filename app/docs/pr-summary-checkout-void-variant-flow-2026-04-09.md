## Summary
- add variant-aware raw material auto-consume on checkout and variant-safe void reversal paths
- propagate request_id through sale completed/voided events so audit logs keep request correlation for checkout and void actions
- cover variant bucket isolation across checkout and void plus manager-pin void regressions with focused feature tests

## Testing
- php artisan test tests/Feature/AutoConsumeOnSellCheckoutFlowTest.php tests/Feature/VariantProductInitialStockLifecycleTest.php tests/Feature/VoidManagerPinEnforcementTest.php --compact
