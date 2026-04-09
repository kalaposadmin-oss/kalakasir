# PR Ready Ops Commands Deploy 2026-04-09

## PR Title

Add ops console commands and staging deploy workflow

## Commit Message

```text
feat(ops): add console audit commands and staging deploy workflow

Add the remaining operational slice for console maintenance commands and the
manual staging deployment workflow.

- add POS consistency audit command for product mode and item_type readiness
- add purchase layer source item audit/backfill command for valuation history cleanup
- add manual deploy-staging GitHub Actions workflow with rollback and health checks

Validated by loading Artisan command registration and scheduler wiring.
```

## PR Description

```markdown
## Scope

This PR isolates the remaining operational slice: console command wiring and the manual staging deploy workflow. It intentionally keeps middleware, platform admin API, and feature controllers out of scope.

## Included Files

- routes/console.php
- .github/workflows/deploy-staging.yml
- docs for PR summary / ready / commit message

## What Changed

- add pos:audit-consistency command for POS catalog migration readiness checks
- add valuation:backfill-purchase-layer-source-items command for safe purchase history backfill audit/apply flow
- add manual deploy-staging workflow with SSH deploy, build/cache steps, migration, queue restart, smoke checks, and rollback trap

## Validation

- php artisan list --raw passed and shows the new console commands
- php artisan schedule:list passed
- deploy workflow reviewed manually for dry-run gating, rollback, and healthcheck flow

## Explicitly Excluded

- middleware files
- feature controller batches
- unchanged command classes
```

## Exact Stage List

Stage hanya file berikut jika ingin slice operasional tetap bersih:

- `app/routes/console.php`
- `.github/workflows/deploy-staging.yml`
- `app/docs/pr-summary-ops-commands-deploy-2026-04-09.md`
- `app/docs/pr-ready-ops-commands-deploy-2026-04-09.md`
- `app/docs/commit-message-ops-commands-deploy-2026-04-09.md`

## Keep Out Of This PR

Jangan ikutkan:

- `app/app/Console/Commands/*` yang tidak berubah
- middleware dan bootstrap files
- sisa feature tests yang tidak khusus command/deploy

## Exact Git Commands

```powershell
git add app/routes/console.php .github/workflows/deploy-staging.yml app/docs/pr-summary-ops-commands-deploy-2026-04-09.md app/docs/pr-ready-ops-commands-deploy-2026-04-09.md app/docs/commit-message-ops-commands-deploy-2026-04-09.md
```

Setelah itu commit dengan:

```powershell
git commit -m "feat(ops): add console audit commands and staging deploy workflow"
```