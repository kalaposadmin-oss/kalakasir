# Smoke Test Checklist — Valuation (Owner/Admin)

## Prasyarat
- User role: `owner` atau `admin`.
- Header tenant valid (`X-Tenant-Id`) untuk API.
- Queue worker aktif.

## A. UI Smoke (Web)
1. Buka halaman `Pengaturan > Valuation Persediaan`.
2. Pastikan status rebuild tampil (`idle/running/ok/failed/cancelled`).
3. Klik `Mulai Rebuild Background`.
4. Verifikasi request id tampil dan status berubah ke `running`/`queued`.
5. Saat lock aktif, tombol aksi utama nonaktif.
6. Klik `Batalkan Rebuild` lalu verifikasi status `cancelled`.
7. Klik `Retry Rebuild` (setelah cancelled/failed) lalu verifikasi request id baru.
8. Trigger `Generate Report Baru` lalu cek request export terakhir tampil.
9. Klik `Batalkan Export` lalu `Retry Export`.
10. Buka halaman `Audit Valuation` dan pastikan event terbaru terlihat.

## B. API Smoke (Ops)
1. Trigger rebuild:
   - `POST /api/v1/settings/valuation-rebuild`
2. Poll status:
   - `GET /api/v1/settings/valuation-rebuild-status`
3. Cancel rebuild:
   - `POST /api/v1/settings/valuation-rebuild-cancel`
4. Retry rebuild:
   - `POST /api/v1/settings/valuation-rebuild-retry`
5. Trigger export:
   - `POST /api/v1/settings/valuation-reports/export`
6. Poll export:
   - `GET /api/v1/settings/valuation-reports/export-status/{requestId}`
7. Cancel export:
   - `POST /api/v1/settings/valuation-reports/export-cancel`
8. Retry export:
   - `POST /api/v1/settings/valuation-reports/export-retry`
9. Cek reconciliation:
   - `GET /api/v1/valuation/reconciliation`
10. Cek audit logs:
   - `GET /api/v1/settings/valuation-audit-logs?limit=50`

## C. Acceptance Criteria
- Endpoint role-protected (`owner/admin`) berjalan sesuai policy.
- Tidak ada lintas-tenant report leakage.
- `runtime_ms` terisi pada status rebuild/export.
- Audit log tercatat untuk queue/cancel/retry/success/failure.
- Retry hanya bisa untuk status `failed`/`cancelled`.

## D. Error Handling Quick Check
- `423` saat lock rebuild aktif.
- `404 REQUEST_NOT_FOUND` untuk request id invalid/expired.
- `403 FORBIDDEN` untuk role non owner/admin.
- `400 TENANT_NOT_RESOLVED` saat tenant context invalid.
- `422 RETRY_NOT_ALLOWED` saat retry tidak memenuhi syarat.
