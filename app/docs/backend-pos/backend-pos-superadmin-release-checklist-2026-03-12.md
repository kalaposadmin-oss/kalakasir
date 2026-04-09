# Backend POS - Superadmin Release Checklist

Tanggal: 2026-03-12
Audience: Engineering, QA, DevOps, Support
Status: Ready for UAT gate

## 1) Scope Lock

- [ ] Spec baseline frozen for this release.
- [ ] Endpoint additions after freeze deferred to next release train.
- [ ] Change owner and approver assigned.

## 2) Application Config

- [ ] PLATFORM_API_RATE_LIMIT_PER_MINUTE set.
- [ ] PLATFORM_SENSITIVE_RATE_LIMIT_PER_MINUTE set.
- [ ] PLATFORM_DANGER_RATE_LIMIT_PER_HOUR set.
- [ ] PLATFORM_IDEMPOTENCY_TTL_MINUTES set.
- [ ] PLATFORM_IDEMPOTENCY_LOCK_SECONDS set.
- [ ] PLATFORM_RATE_LIMIT_OVERRIDE_TOKEN rotated and stored in secret manager.
- [ ] AUDIT_LOG_RETENTION_DAYS configured according to policy.

## 3) Infrastructure Readiness

- [ ] Redis available and healthy in production.
- [ ] Cache and lock backend validated under concurrency.
- [ ] Queue worker and failed job monitoring active.
- [ ] Database backup and restore verification completed.

## 4) Security and Access

- [ ] Superadmin role assignment reviewed and least privilege applied.
- [ ] Danger actions require Idempotency-Key and are rate-limited.
- [ ] X-Platform-RateLimit-Bypass token restricted to internal path.
- [ ] Audit logs include actor, reason, request_id, and action_type.
- [ ] Impersonation expiry and termination audit event validated.

## 5) Test Gates

Mandatory pass:
- [ ] PlatformSuperadminReadApiTest
- [ ] PlatformSuperadminDangerActionsTest
- [ ] tests: valuation
- [ ] tests: write-flow
- [ ] tests: all

Reference from latest run:
- Full suite: 127 passed, 524 assertions.

## 6) UAT Scenarios

- [ ] Dashboard metrics and recent events are rendered correctly.
- [ ] Tenant filters/sort/pagination produce expected results.
- [ ] User list/detail/audit logs produce expected results.
- [ ] Billing list/detail and plan changes export are valid.
- [ ] Payments endpoint supports filter and sorting.
- [ ] Audit logs export has expected columns and data.
- [ ] Anomaly resolve supports idempotent replay and conflict handling.
- [ ] Support search resolves tenant/user/invoice references correctly.
- [ ] Settings endpoints return feature flags and rate limit snapshot.

## 7) Observability and Incident Readiness

- [ ] Error envelope consistency sampled from API logs.
- [ ] Request ID traceability verified end-to-end.
- [ ] Alerts configured for 429 spikes and 5xx spikes.
- [ ] Alerts configured for Redis/cache lock failures.
- [ ] Daily purge command for audit logs confirmed in scheduler.

## 8) Deployment Plan

- [ ] Deployment window and rollback owner confirmed.
- [ ] Deploy script includes config cache and route cache refresh.
- [ ] Post-deploy smoke checks completed within 15 minutes.
- [ ] Rollback command and criteria documented.

Rollback criteria:
- Critical superadmin endpoint returns 5xx repeatedly.
- Idempotency or rate limit behavior causes operational blocking.
- Audit event loss or malformed security logs detected.

## 9) Sign-off

Engineering:
- [ ] Approved

QA:
- [ ] Approved

DevOps:
- [ ] Approved

Product/Operations:
- [ ] Approved
