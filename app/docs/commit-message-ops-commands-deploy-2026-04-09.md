# Commit Message Ops Commands Deploy 2026-04-09

```text
feat(ops): add console audit commands and staging deploy workflow

Add the remaining operational slice for console maintenance commands and the
manual staging deployment workflow.

- add POS consistency audit command for product mode and item_type readiness
- add purchase layer source item audit/backfill command for valuation history cleanup
- add manual deploy-staging GitHub Actions workflow with rollback and health checks

Validated by loading Artisan command registration and scheduler wiring.
```