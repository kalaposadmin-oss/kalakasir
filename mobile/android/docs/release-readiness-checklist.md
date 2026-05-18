# Android Release Readiness Checklist

## Build And Test Gate
- [x] `:core-database:testDebugUnitTest` passed.
- [x] `:app:testDebugUnitTest` passed.
- [x] `:app:assembleDebug` passed.
- [x] Backend regression `tests: write-flow` passed.

## Purchase Return Hardening Gate
- [x] Staged return multi-product transitions to `returned_full` covered by test.
- [x] Decimal cumulative return guarded with floating-point tolerance.
- [x] Duplicate retry within policy window is idempotent.
- [x] Outbox payload contract for purchase return has snapshot test.
- [x] Local observability metrics emitted for `return_created`, `return_deduplicated`, and `return_overflow_blocked`.

## Schema And Migration Contract Gate
- [x] Room schema v6 contains `purchase_return_items`.
- [x] Schema contract verifies v5 to v6 introduces `purchase_return_items` while preserving purchase core tables.

## Manual Smoke Gate (Recommended Before QA Sign-Off)
- [ ] Finalize purchase offline.
- [ ] Mark receive and verify status update.
- [ ] Execute two staged returns and verify `returned_full`.
- [ ] Trigger exact retry to confirm no duplicate return row/outbox item.

## Rollback Notes
- If release rollback is required, pin to previous APK and avoid opening DB with schema v6 migration path until patch build is ready.
- Preserve local outbox queue before rollback diagnostics to avoid losing sync evidence.
