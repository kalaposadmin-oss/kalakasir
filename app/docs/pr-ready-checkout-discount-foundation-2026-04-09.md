# PR Ready Checkout Discount Foundation 2026-04-09

## PR Title

Add checkout discount calculation and persistence foundation

## Commit Message

```text
feat(app): add checkout discount calculation foundation

Add the foundation for calculating, storing, and reading checkout discount
breakdown data.

- add item and transaction discount calculation service
- persist discount breakdown rows during checkout
- expose persisted discounts in sale detail responses
- include focused checkout and discount calculation coverage

Validated with focused discount and sale detail tests.
```

## PR Description

```markdown
## Scope

This PR isolates the checkout discount foundation used to calculate stacked discounts, persist discount rows, and expose those rows in sale detail responses. It keeps variants, vouchers, loyalty, and production flows out of scope.

## Included Files

- app/Http/Controllers/Api/SaleDetailController.php
- app/Models/Discount.php
- app/Models/Sale.php
- app/Services/CheckoutService.php
- app/Services/DiscountCalculationService.php
- database/migrations/2026_03_09_100070_create_discounts_table.php
- tests/Concerns/BuildsCheckoutRequestFixture.php
- tests/Feature/CheckoutDiscountFlowTest.php
- tests/Unit/DiscountCalculationServiceTest.php

## What Changed

- add normalized item and transaction discount calculation
- persist discount breakdown rows on successful checkout
- return persisted discounts from sale detail
- update checkout fixtures for the current POS schema used by focused tests

## Validation

- php artisan test tests/Unit/DiscountCalculationServiceTest.php tests/Feature/CheckoutDiscountFlowTest.php tests/Feature/SaleDetailTenantScopingTest.php passed

## Explicitly Excluded

- voucher and loyalty flows
- pricing rules and price list runtime
- variant and production batch feature work
```