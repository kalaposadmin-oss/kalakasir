# Release Note Internal — Valuation Ops Hardening (2026-02-27)

## Ringkasan
Rilis ini menyelesaikan paket operasional valuation end-to-end untuk metode FIFO/Average per tenant, termasuk kontrol async, audit, retry, cancel, observability, dan guard UI.

## Scope Perubahan
- Metode valuation tenant: `average` / `fifo`.
- Rebuild valuation historis: sync + async.
- Export laporan valuation comparison: async + status.
- Cancel dan retry untuk rebuild/export.
- Idempotency untuk trigger async agar tidak duplicate request.
- Tenant-scoped report file list/download.
- Role hardening endpoint valuation: `owner` / `admin`.
- Audit trail aksi valuation kritikal (`valuation_audit_logs`).
- Alerting ops untuk anomaly valuation + threshold kontrol.
- Retention report + cache pruning terjadwal.
- Dashboard UI valuation + halaman audit valuation.

## Endpoint Baru / Diperluas
- `POST /api/v1/settings/valuation-rebuild`
- `GET /api/v1/settings/valuation-rebuild-status`
- `POST /api/v1/settings/valuation-rebuild-cancel`
- `POST /api/v1/settings/valuation-rebuild-retry`
- `POST /api/v1/settings/valuation-reports/export`
- `GET /api/v1/settings/valuation-reports/export-status/{requestId}`
- `POST /api/v1/settings/valuation-reports/export-cancel`
- `POST /api/v1/settings/valuation-reports/export-retry`
- `GET /api/v1/settings/valuation-audit-logs`
- `GET /api/v1/valuation/reconciliation`

## Command Operasional
- `php artisan inventory:rebuild-valuation --tenant_id=<UUID>`
- `php artisan inventory:rollback-valuation-method --tenant_id=<UUID> --to=average|fifo`
- `php artisan inventory:export-valuation-comparison --tenant_id=<UUID> --limit=200`
- `php artisan inventory:prune-valuation-reports --days=14`
- `php artisan inventory:prune-valuation-cache --hours=168`

## Scheduler Tambahan
- Prune report valuation: harian.
- Prune cache valuation: harian.
- Drift detection valuation: hourly.

## Konfigurasi Environment
- `OPS_ALERTS_ENABLED`
- `OPS_ALERTS_WEBHOOK_URL`
- `OPS_ALERTS_BEARER_TOKEN` (opsional)
- `OPS_ALERTS_TIMEOUT_SECONDS`
- `OPS_ALERTS_VALUATION_MIN_SEVERITY`
- `OPS_ALERTS_VALUATION_MIN_COUNT`
- `VALUATION_REPORT_RETENTION_DAYS`

## Hasil Validasi
- Full test suite: pass.
- Feature valuation auth/async: pass.
- Regression write-flow existing: pass.

## Risiko Residual
- Cancel/Retry bersifat best-effort pada operasi queue yang sangat cepat selesai.
- Monitoring webhook ops tergantung ketersediaan endpoint eksternal.

## Rekomendasi Deploy
1. Jalankan migrate.
2. Pastikan worker queue aktif.
3. Verifikasi endpoint status/reconciliation.
4. Jalankan smoke test checklist (dokumen terpisah).
