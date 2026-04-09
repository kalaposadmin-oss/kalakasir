# Commit Message Production Schema and Waste Fields 2026-04-09

## Subject

`feat(app): add production schema and waste adjustment fields`

## Body

```text
Add the production and onboarding schema fields needed by the newer
runtime flows, including waste tagging support on stock adjustments.

- add business category and precision updates for tenant and inventory data
- extend products and production batches for production mode, actual yield, and cancellation metadata
- add waste tagging fields to stock adjustments
- add production batch tenant scoping and waste tagging feature coverage

Validation:
- tests: all
```