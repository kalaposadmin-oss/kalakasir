# Superadmin PR Title and Commit Bundle

Tanggal: 2026-03-12
Status: Ready to use

## 1) Recommended PR Titles

Primary:
- feat(platform): complete superadmin endpoints, idempotency hardening, and release docs

Alternative:
- feat(superadmin): finalize platform API coverage with payments/settings and danger-action safeguards
- hardening(platform): enforce idempotency and configurable rate limits for superadmin operations
- docs(release): ship superadmin endpoint spec verification, checklist, and PR-ready package

## 2) Conventional Commit Bundle (Squash Strategy)

Use one of the following as squash commit message:

Option A (balanced):

Title:
- feat(platform): complete superadmin backend coverage and operational hardening

Body:
- add missing platform payments and settings endpoints
- normalize platform datetime fields to ISO 8601 UTC
- restrict support action_type filters to allowed enum values
- harden danger-action idempotency with lock + cache store (Redis preferred in production)
- make api/sensitive/danger rate limits configurable via platform config
- add and update tests for read APIs, danger actions, valuation, and write-flow regressions
- update endpoint spec and add changelog, release checklist, release note, and PR draft artifacts

Footer:
- test: php artisan test (127 passed, 524 assertions)

Option B (security-forward):

Title:
- hardening(platform): enforce idempotent danger actions and configurable throttling

Body:
- enforce idempotency behavior across sensitive platform actions
- centralize lock/ttl configuration for platform idempotency store
- align danger-action and sensitive endpoint rate limits with config policy
- keep endpoint contract consistent with spec data/meta envelope
- validate end-to-end with platform and full regression test suites

Footer:
- test: php artisan test --filter=PlatformSuperadminReadApiTest
- test: php artisan test --filter=PlatformSuperadminDangerActionsTest
- test: php artisan test

## 3) Non-Squash Commit Sequence (If Needed)

Suggested logical split:
1. feat(platform): add payments list endpoint for superadmin
2. feat(platform): add settings feature-flags and rate-limits endpoints
3. refactor(platform): normalize datetime responses to ISO 8601 UTC
4. hardening(platform): add idempotency store with lock and Redis preference
5. chore(platform): make rate limit and idempotency values configurable
6. test(platform): add coverage for payments/settings and danger action flows
7. docs(platform): update spec and release artifacts for superadmin rollout

## 4) Suggested Branch Name

- feat/superadmin-platform-hardening-2026-03-12

## 5) Suggested PR Labels

- backend
- api
- security
- platform
- docs
- ready-for-uat
