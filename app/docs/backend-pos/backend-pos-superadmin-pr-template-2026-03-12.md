# PR Template - Superadmin Backend

## 1) Ringkasan Perubahan

Isi singkat:
- Apa yang diubah.
- Kenapa perubahan diperlukan.
- Area paling berisiko dari perubahan.

## 2) Scope

Centang yang relevan:
- [ ] Endpoint baru
- [ ] Perubahan kontrak response
- [ ] Perubahan validasi request
- [ ] Perubahan middleware/security
- [ ] Perubahan idempotency/rate limit
- [ ] Perubahan docs/spec
- [ ] Perubahan test

## 3) Endpoint/Contract Impact

Daftar endpoint yang terpengaruh:
- Method + path
- Perubahan request
- Perubahan response
- Status compatibility: backward compatible / breaking

## 4) Security Review

- [ ] Role guard sudah tepat.
- [ ] Danger actions memakai Idempotency-Key.
- [ ] Rate limit diterapkan pada endpoint sensitif.
- [ ] Audit event mencatat actor, reason, request_id.
- [ ] Tidak ada secret hardcoded.

Catatan keamanan tambahan:
- ...

## 5) Konfigurasi dan Env

Tuliskan env/config baru atau berubah:
- PLATFORM_API_RATE_LIMIT_PER_MINUTE
- PLATFORM_SENSITIVE_RATE_LIMIT_PER_MINUTE
- PLATFORM_DANGER_RATE_LIMIT_PER_HOUR
- PLATFORM_IDEMPOTENCY_TTL_MINUTES
- PLATFORM_IDEMPOTENCY_LOCK_SECONDS
- AUDIT_LOG_RETENTION_DAYS

Checklist:
- [ ] Nilai default aman untuk dev.
- [ ] Nilai produksi sudah dikoordinasikan dengan DevOps.

## 6) Database dan Data

- [ ] Tidak ada migration.
- [ ] Ada migration non-breaking.
- [ ] Ada migration yang butuh maintenance window.
- [ ] Ada perubahan seed/data backfill.

Detail:
- ...

## 7) Test Evidence

Jalankan dan tempel ringkasan hasil:
- [ ] php artisan test --filter=PlatformSuperadminReadApiTest
- [ ] php artisan test --filter=PlatformSuperadminDangerActionsTest
- [ ] php artisan test --filter="ValuationAuthorizationTest^|ValuationAsyncOperationsTest^|ValuationWebSmokeTest"
- [ ] php artisan test --filter="WriteFlowLockPeriodApiEnforcementTest^|WriteFlowTransactionDateHappyPathTest^|CheckoutTransactionDateValidationTest^|PurchaseReceiveTransactionDateValidationTest^|StockTransferTransactionDateValidationTest^|StockAdjustmentTransactionDateValidationTest^|PurchaseReturnConsistencyTest"
- [ ] php artisan test

Output ringkas:
- ...

## 8) UAT Plan

Skenario UAT utama:
- [ ] Read endpoints pagination/filter/sort.
- [ ] Danger actions success + replay idempotent + conflict.
- [ ] Audit logs/export valid.
- [ ] Payments and settings endpoints valid.

## 9) Rollback Plan

- Trigger rollback jika:
  - error 5xx meningkat pada endpoint platform.
  - danger action gagal idempotent.
  - audit event critical tidak tercatat.

Langkah rollback:
1. Revert commit/PR ini.
2. Deploy kembali versi stabil terakhir.
3. Clear cache config/route bila diperlukan.
4. Re-run smoke tests platform.

## 10) Dokumentasi

- [ ] Spec endpoint diperbarui.
- [ ] Changelog teknis diperbarui.
- [ ] Checklist rilis diperbarui.
- [ ] Release note non-teknis diperbarui.

Referensi docs:
- docs/backend-pos/backend-pos-superadmin-endpoint-spec.md
- docs/backend-pos/backend-pos-superadmin-implementation-changelog-2026-03-12.md
- docs/backend-pos/backend-pos-superadmin-release-checklist-2026-03-12.md
- docs/backend-pos/backend-pos-superadmin-release-note-2026-03-12.md
