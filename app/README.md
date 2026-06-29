# Kalakasir POS

## CI Status

Workflow test matrix sudah disiapkan di `.github/workflows/ci-tests.yml`.

[![CI Tests](https://img.shields.io/badge/CI%20Tests-configured-success)](../.github/workflows/ci-tests.yml)

Saat remote GitHub sudah diset, ganti ke badge dinamis Actions:

`[![CI Tests](https://github.com/<OWNER>/<REPO>/actions/workflows/ci-tests.yml/badge.svg?branch=main)](https://github.com/<OWNER>/<REPO>/actions/workflows/ci-tests.yml)`

Lane CI yang dijalankan:
- Web build: `npm ci && npm run build`
- Web smoke: `php artisan test tests/Feature/PortalWebAccessTest.php tests/Feature/WebDashboardSmokeTest.php tests/Feature/WebOperationalPagesSmokeTest.php tests/Feature/WebSettingsFinanceSmokeTest.php tests/Feature/WebAuthOnboardingSmokeTest.php tests/Feature/WebSubscriptionSupportSmokeTest.php tests/Feature/WebSupportMarketingSmokeTest.php`
- Unit: `php artisan test --testsuite=Unit`
- Valuation: `php artisan test --filter='ValuationAuthorizationTest|ValuationAsyncOperationsTest|ValuationWebSmokeTest'`
- Write flow: `php artisan test --filter='WriteFlowLockPeriodApiEnforcementTest|WriteFlowTransactionDateHappyPathTest|CheckoutTransactionDateValidationTest|PurchaseReceiveTransactionDateValidationTest|StockTransferTransactionDateValidationTest|StockAdjustmentTransactionDateValidationTest|PurchaseReturnConsistencyTest'`

Shortcut lokal VS Code task tersedia di `.vscode/tasks.json`:
- `web: build`
- `web: dev`
- `tests: web-smoke`
- `tests: unit`
- `tests: valuation`
- `tests: write-flow`
- `tests: all`

POS multi-tenant dengan Branch + Location, checkout idempotent, immutable ledger, dan double-entry accounting. UI checkout sudah terhubung ke API bootstrap dan checkout.

## Fitur Utama
- Tenant isolation dengan resolver header dan subdomain
- Checkout engine: idempotency, stok atomic, journal otomatis
- Branch + Location untuk stok
- Split payment dan void transaksi

## Requirement
- PHP 8.3+
- Composer
- Node.js 20+
- npm

## Setup Lokal
1) Install dependency
	- composer install
	- npm install
2) Setup env
	- copy `.env.example` menjadi `.env`
	- php artisan key:generate
	- opsional untuk split portal berbasis host:
	  - `POS_PORTAL_HOSTS=pos.127.0.0.1.nip.io`
	  - `BACKOFFICE_PORTAL_HOSTS=admin.127.0.0.1.nip.io`
	  - `SESSION_COOKIE=kalakasir-portal-session`
	  - `SESSION_DOMAIN=.127.0.0.1.nip.io`
3) Migrasi dan seed demo data
	- php artisan migrate:fresh --seed

## Konfigurasi Portal Web

Generic route `/login` sekarang bisa mengikuti host request:
- jika host ada di `POS_PORTAL_HOSTS`, halaman login utama memakai copy POS dan owner/admin tetap diarahkan ke portal POS setelah login.
- jika host ada di `BACKOFFICE_PORTAL_HOSTS`, halaman login utama memakai copy Backoffice dan owner/admin diarahkan ke Backoffice.
- jika host tidak terdaftar, fallback tetap memakai session portal aktif atau role user.

Route eksplisit tetap tersedia untuk semua environment:
- `/pos/login`
- `/backoffice/login`

Contoh setup lokal Windows:
- opsi paling ringan: pakai `pos.127.0.0.1.nip.io` dan `admin.127.0.0.1.nip.io`
- opsi alternatif: pakai `pos.localhost` dan `admin.localhost` jika resolver lokal mendukung wildcard `localhost`
- opsi custom domain: tambahkan mapping hosts untuk `127.0.0.1 pos.kalakasir.test`
- opsi custom domain: tambahkan mapping hosts untuk `127.0.0.1 office.kalakasir.test`
- jalankan server Laravel dari folder `app`
- buka `http://pos.127.0.0.1.nip.io:8000/login` untuk shell POS
- buka `http://admin.127.0.0.1.nip.io:8000/login` untuk shell Backoffice
- jika host portal terasa tidak berubah walau `.env` sudah diganti, cek env shell aktif karena `POS_PORTAL_HOSTS` dan `BACKOFFICE_PORTAL_HOSTS` di terminal dapat menimpa konfigurasi `.env`
- jika login di POS hilang saat pindah ke Backoffice, isi `SESSION_DOMAIN` ke domain induk bersama, misalnya `.127.0.0.1.nip.io`, lalu login ulang sekali

Owner/admin pada host POS tetap masuk ke shell POS mobile-first, tetapi sekarang mendapat shortcut manajemen di dashboard, histori, shift, detail transaksi, pembayaran, struk, dan void/refund. Cashier tetap tidak melihat shortcut tersebut.

Untuk verifikasi manual yang konsisten, util lokal `scripts/dev/ensure_portal_qa_users.php` dapat dipakai untuk menyiapkan akun QA owner dan cashier.

## Menjalankan Aplikasi
- php artisan serve
- npm run dev

## Git dan Deployment Hosting

- Jalankan `npm ci && npm run build` sebelum commit perubahan UI.
- Folder `public/build` sengaja ikut Git karena workflow hosting produksi menyalin artifact tersebut tanpa menjalankan Node.js di server.
- Jangan commit `.env`, database lokal, log, cache, `vendor`, atau `node_modules`.
- Gunakan `.env.hosting.example` sebagai template konfigurasi server dan isi rahasia hanya melalui `.env` hosting/GitHub Secrets.
- Sebelum push, jalankan `php artisan route:cache`, `php artisan view:cache`, dan test terkait. Setelah validasi lokal, jalankan `php artisan optimize:clear` agar cache lokal tidak ikut memengaruhi pemeriksaan berikutnya.
- Deploy branch `main` menggunakan `.github/workflows/deploy.yml`; workflow akan memasang dependency produksi, migrasi, menyalin asset build, membuat cache Laravel, dan me-restart queue.

## Validasi Web Frontend

- Build production frontend:
	- `npm run build`
- Smoke suite halaman web:
	- `tests: web-smoke`
	- atau `php artisan test tests/Feature/PortalWebAccessTest.php tests/Feature/WebDashboardSmokeTest.php tests/Feature/WebOperationalPagesSmokeTest.php tests/Feature/WebSettingsFinanceSmokeTest.php tests/Feature/WebAuthOnboardingSmokeTest.php tests/Feature/WebSubscriptionSupportSmokeTest.php tests/Feature/WebSupportMarketingSmokeTest.php`
- Shortcut task VS Code:
	- `web: build`
	- `web: dev`
	- `tests: web-smoke`
- CI sekarang memvalidasi build Vite dan batch `web-smoke` di workflow `ci-tests.yml`, jadi perubahan Blade/JS/CSS dan guardrail halaman utama ikut tervalidasi di pipeline.

## Catatan Operasional
- UI mengambil data dari endpoint bootstrap dan membutuhkan tenant id.
- Tenant id otomatis diambil dari tenant pertama pada database untuk halaman web.
- Untuk API, header X-Tenant-Id wajib.

## Inventory Valuation (FIFO / Average)

Sistem mendukung metode valuation per tenant:
- `average` (default)
- `fifo`

Update metode valuation tenant (admin API):
- `PATCH /api/v1/settings/inventory-valuation-method`
- body: `{ "method": "fifo" }` atau `{ "method": "average" }`

Method aktif juga tersedia di bootstrap:
- `GET /api/v1/bootstrap` → `tenant.inventory_valuation_method`

### Rebuild Historis Valuation

Setelah perubahan metode atau rollout fitur, jalankan rebuild agar transaksi lama sinkron:
- Semua tenant:
	- `php artisan inventory:rebuild-valuation`
- Tenant tertentu:
	- `php artisan inventory:rebuild-valuation --tenant_id=<UUID_TENANT>`

Runbook aman produksi:
1) Stop write traffic (atau maintenance singkat)
2) Jalankan migrate terbaru
3) Jalankan rebuild valuation
4) Validasi sample HPP sale, nilai inventory, dan saldo qty
5) Buka kembali write traffic

### SOP Cutover Produksi (Ringkas)

1. Aktifkan maintenance/freeze write transaksi.
2. Jalankan migrate terbaru.
3. Jalankan `php artisan inventory:rebuild-valuation --tenant_id=<UUID>`.
4. Jalankan `php artisan inventory:audit-valuation --tenant_id=<UUID> --limit=10`.
5. Cek endpoint rekonsiliasi valuation (admin): `GET /api/v1/valuation/reconciliation`.
6. Jika normal, nonaktifkan freeze write.

### Guardrail Ganti Metode

- Endpoint update metode valuation kini mewajibkan konfirmasi rebuild:
	- `PATCH /api/v1/settings/inventory-valuation-method`
	- body minimal: `{ "method": "fifo|average", "confirm_rebuild": true }`
- Setelah metode berubah, sistem otomatis menjalankan replay valuation historis.
- Write API akan dikunci sementara selama rebuild berlangsung.

### Rebuild Valuation Async (Admin)

- Trigger rebuild background:
	- `POST /api/v1/settings/valuation-rebuild`
	- body opsional: `{ "trigger": "manual|web_settings|ops" }`
- Cek status rebuild background:
	- `GET /api/v1/settings/valuation-rebuild-status`
	- response ringkas: `{ "locked": bool, "meta": { "status", "started_at", "finished_at", "request_id", "message", "runtime_ms" } }`
- Batalkan request rebuild (best effort, aman untuk request yang belum selesai):
	- `POST /api/v1/settings/valuation-rebuild-cancel`
	- body: `{ "request_id": "<UUID>", "reason": "optional" }`
- Retry request rebuild failed/cancelled:
	- `POST /api/v1/settings/valuation-rebuild-retry`
	- body: `{ "request_id": "<UUID>", "trigger": "optional" }`
- Idempotency trigger rebuild:
	- header `Idempotency-Key: <KEY>` atau body `idempotency_key`.

### Report & Rekonsiliasi (Admin)

- List report valuation:
	- `GET /api/v1/settings/valuation-reports`
- Download report valuation:
	- `GET /api/v1/settings/valuation-reports/{filename}`
- Trigger export report async (admin):
	- `POST /api/v1/settings/valuation-reports/export`
	- body opsional: `{ "from": "2026-02-01", "to": "2026-02-27", "sku_prefix": "AUDIT-", "limit": 200 }`
- Cek status export async:
	- `GET /api/v1/settings/valuation-reports/export-status/{requestId}`
- Retry export failed/cancelled:
	- `POST /api/v1/settings/valuation-reports/export-retry`
	- body: `{ "request_id": "<UUID>" }`
- Batalkan export async (best effort):
	- `POST /api/v1/settings/valuation-reports/export-cancel`
	- body: `{ "request_id": "<UUID>", "reason": "optional" }`
- Dashboard ringkas rekonsiliasi valuation:
	- `GET /api/v1/valuation/reconciliation`
- Report valuation kini dipisahkan per tenant pada level nama file dan list/download endpoint hanya menampilkan file tenant aktif.

Catatan akses:
- Endpoint operasional valuation (`inventory-valuation-method`, `valuation-rebuild`, `valuation-reports`, `valuation/reconciliation`) dibatasi untuk role `owner` atau `admin`.

### API Audit Log Valuation

- List audit log valuation (owner/admin):
	- `GET /api/v1/settings/valuation-audit-logs`
	- query opsional: `action`, `actor_id`, `request_id`, `from`, `to`, `limit`

### Audit Trail & Alerting

- Semua aksi kritikal valuation (switch metode, queue/cancel rebuild, queue/cancel export, job success/failure) dicatat ke `valuation_audit_logs`.
- Anomaly valuation dan severity tinggi akan mengirim ops alert jika diaktifkan.
- Environment ops alert:
	- `OPS_ALERTS_ENABLED=true|false`
	- `OPS_ALERTS_WEBHOOK_URL=https://...`
	- `OPS_ALERTS_BEARER_TOKEN=...` (opsional)
	- `OPS_ALERTS_TIMEOUT_SECONDS=4`
	- `OPS_ALERTS_VALUATION_MIN_SEVERITY=med|high|critical`
	- `OPS_ALERTS_VALUATION_MIN_COUNT=1`

### Command Operasional Valuation

- Rebuild histori valuation:
	- `php artisan inventory:rebuild-valuation --tenant_id=<UUID>`
- Audit valuation sample:
	- `php artisan inventory:audit-valuation --tenant_id=<UUID> --limit=10`
- Export komparasi average vs fifo:
	- `php artisan inventory:export-valuation-comparison --tenant_id=<UUID> --from=2026-02-01 --to=2026-02-27 --sku_prefix=AUDIT- --limit=200`
- Benchmark durasi replay:
	- `php artisan inventory:benchmark-rebuild --tenant_id=<UUID> --iterations=3`
- Prune report lama sesuai retention:
	- `php artisan inventory:prune-valuation-reports --days=14`
- Prune cache valuation async/idempotency lama:
	- `php artisan inventory:prune-valuation-cache --hours=168`
- Rollback metode valuation + replay aman:
	- `php artisan inventory:rollback-valuation-method --tenant_id=<UUID> --to=average`
	- `php artisan inventory:rollback-valuation-method --tenant_id=<UUID> --to=fifo`

### Monitoring Terjadwal Tambahan

- Job baru `DetectValuationDriftJob` dijadwalkan hourly untuk deteksi:
	- drift qty `inventory_balances` vs `inventory_cost_states`
	- stok bernilai biaya nol
	- sale item dengan `hpp_snapshot` nol
- Detector anomaly kini memakai adaptive threshold dengan guard:
	- cold-start fallback (`min sample events` + `floor`)
	- robust baseline (median/MAD + winsorized mean)
	- poisoning guard (`max threshold cap`)
	- anti split-attack (`DetectVoidSplitAttackJob`) setiap 15 menit
- Prune report valuation dijadwalkan harian (`inventory:prune-valuation-reports`).
- Prune cache valuation dijadwalkan harian (`inventory:prune-valuation-cache`).

Roadmap tuning berikutnya (non-breaking):
- opsi baseline seasonality `hour-of-day` (bandingkan jam yang sama hari sebelumnya) untuk tenant dengan pola traffic tajam.

## Troubleshooting Valuation API

- `423 VALUATION_REBUILD_IN_PROGRESS`
	- Penyebab: write-flow dikunci saat rebuild berjalan.
	- Tindakan: tunggu status `ok/failed/cancelled` atau batalkan request rebuild aktif.
- `404 REQUEST_NOT_FOUND`
	- Penyebab: request id salah, berbeda tenant, atau cache status sudah expired.
	- Tindakan: cek request id terbaru dari endpoint status/list.
- `403 FORBIDDEN`
	- Penyebab: role bukan `owner/admin`.
	- Tindakan: assign role tenant yang benar.
- `400 TENANT_NOT_RESOLVED`
	- Penyebab: tenant context tidak terbaca.
	- Tindakan: kirim header `X-Tenant-Id` yang valid.
- `422 RETRY_NOT_ALLOWED`
	- Penyebab: retry dipanggil untuk request yang belum `failed/cancelled`.
	- Tindakan: retry hanya untuk request gagal atau dibatalkan.

## Dokumen Handoff

- Release note internal valuation:
	- `docs/valuation-release-note-2026-02-27.md`
- Smoke test checklist valuation:
	- `docs/valuation-smoke-test-checklist.md`
- DR / PITR runbook:
	- `docs/dr-pitr-runbook.md`
- Governance rebuild/repair:
	- `docs/rebuild-repair-governance.md`
- Data partition & archival plan:
	- `docs/data-partition-archival-plan.md`
- Costing policy purchase return:
	- `docs/costing-policy-purchase-return.md`
- SOP keuangan operasional toko kecil:
	- `docs/sop-keuangan-operasional-toko-kecil.md`
- Checklist UI keuangan per menu:
	- `docs/checklist-ui-keuangan-per-menu.md`
- Checklist keuangan per role:
	- `docs/checklist-keuangan-per-role.md`
- Checklist keuangan printable 1 halaman:
	- `docs/checklist-keuangan-printable-1-halaman.md`
- Template rekap mingguan keuangan 1 halaman:
	- `docs/template-rekap-mingguan-keuangan-1-halaman.md`
- Spesifikasi dashboard kontrol harian keuangan:
	- `docs/spesifikasi-dashboard-kontrol-harian-keuangan.md`
- Kebijakan batas otorisasi transaksi:
	- `docs/kebijakan-batas-otorisasi-transaksi.md`
- SLA insiden operasional keuangan:
	- `docs/sla-insiden-operasional-keuangan.md`
- Kode alasan standar void/refund/adjustment:
	- `docs/kode-alasan-standar-void-refund-adjustment.md`
- Kebijakan stok minimum dan reorder point:
	- `docs/kebijakan-stok-minimum-dan-reorder-point.md`
- Aturan prioritas pembayaran hutang supplier:
	- `docs/aturan-prioritas-pembayaran-hutang-supplier.md`
- Roadmap otomasi kontrol keuangan aplikasi:
	- `docs/roadmap-otomasi-kontrol-keuangan-aplikasi.md`
- Status eksekusi urutan SOP keuangan:
	- `docs/status-eksekusi-urutan-sop-keuangan.md`
- UI manual QA checklist (production readiness):
	- `docs/ui-manual-qa-checklist-2026-02-27.md`
- UAT checklist kontrol harian keuangan:
	- `docs/uat-checklist-kontrol-harian-keuangan.md`
- Handover operasional kontrol keuangan (1 halaman):
	- `docs/handover-operasional-kontrol-keuangan-2026-02-28.md`
- Handover operasional kontrol keuangan printable (1 halaman):
	- `docs/handover-operasional-kontrol-keuangan-printable-1-halaman.md`

## SOP Cutover Produksi (1 Halaman)

### A. Pre-check (T-30 menit)
1. Pastikan worker queue aktif dan sehat.
2. Pastikan backup DB terbaru tersedia.
3. Pastikan user operator punya role `owner`/`admin`.

### B. Eksekusi
1. Freeze write transaksi (maintenance singkat).
2. Jalankan migrate terbaru.
3. Trigger rebuild valuation async.
4. Pantau status hingga `ok` via endpoint status.

### C. Verifikasi
1. Cek rekonsiliasi valuation (`qty_drift_rows`, `zero_cost_rows`, `sale_zero_hpp_rows`).
2. Jalankan audit command sample produk.
3. Generate report komparasi dan simpan artefak.

### D. Recovery / Rollback
1. Jika rebuild gagal, cek anomaly + audit log.
2. Jalankan rollback metode (`inventory:rollback-valuation-method`) jika perlu.
3. Ulang rebuild setelah akar masalah selesai.

### E. Operasional Harian
1. Pantau anomaly valuation tiap jam.
2. Pantau report retention agar storage stabil.
3. Review audit log valuation berkala untuk jejak perubahan.

## QA Matrix: Invalid Transaction Date

Aturan validasi:
- `transaction_date` tidak boleh di masa depan.
- `transaction_date` tidak boleh melebihi batas backdate `APP_MAX_BACKDATE_DAYS` (default: 1 hari).
- Jika invalid, API mengembalikan `422` dengan `code: INVALID_TRANSACTION_DATE`.

Endpoint yang sudah tercakup test:

| Endpoint | Skenario | Ekspektasi | Test |
|---|---|---|---|
| `POST /api/v1/checkout` | future & backdate over limit | `422 INVALID_TRANSACTION_DATE` | `tests/Feature/CheckoutTransactionDateValidationTest.php` |
| `POST /api/v1/purchases/{purchase}/receive` | future & backdate over limit | `422 INVALID_TRANSACTION_DATE` | `tests/Feature/PurchaseReceiveTransactionDateValidationTest.php` |
| `POST /api/v1/stock-transfers/{transfer}/ship` | future | `422 INVALID_TRANSACTION_DATE` | `tests/Feature/StockTransferTransactionDateValidationTest.php` |
| `POST /api/v1/stock-transfers/{transfer}/receive` | backdate over limit | `422 INVALID_TRANSACTION_DATE` | `tests/Feature/StockTransferTransactionDateValidationTest.php` |
| `POST /api/v1/stock-adjustments/{adjustment}/post` | future & backdate over limit | `422 INVALID_TRANSACTION_DATE` | `tests/Feature/StockAdjustmentTransactionDateValidationTest.php` |

Perintah test cepat:
- `php artisan test tests/Unit/TransactionDateServiceTest.php tests/Feature/LockPeriodServiceTest.php tests/Feature/CheckoutTransactionDateValidationTest.php tests/Feature/PurchaseReceiveTransactionDateValidationTest.php tests/Feature/StockTransferTransactionDateValidationTest.php tests/Feature/StockAdjustmentTransactionDateValidationTest.php --stop-on-failure`

### Tuning APP_MAX_BACKDATE_DAYS

- Atur `APP_MAX_BACKDATE_DAYS` di `.env` sesuai policy operasional tiap environment.
- Contoh:
	- Staging: `APP_MAX_BACKDATE_DAYS=1`
	- Production ketat: `APP_MAX_BACKDATE_DAYS=0` (hanya tanggal hari ini)
- Setelah ubah config, jalankan `php artisan config:clear` agar nilai baru terbaca.
- Risiko operasional: jika `APP_MAX_BACKDATE_DAYS=0`, semua input backdate legacy akan ditolak oleh API.

### Runbook Operasional

- Rebuild data historis `transaction_date` (satu kali setelah rollout):
	- `php artisan transactions:backfill-transaction-date`
- Monitoring anomaly invalid date:
	- Sistem mencatat `invalid_transaction_date_attempt` pada endpoint write-flow.
	- Scheduler menjalankan deteksi spike (`invalid_transaction_date_spike`) setiap 15 menit.
- Checklist perubahan policy:
	- Ubah nilai `APP_MAX_BACKDATE_DAYS`.
	- Jalankan `php artisan config:clear`.
	- Pantau anomaly 1 jam pertama setelah perubahan.
