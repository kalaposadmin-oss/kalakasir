# Status Eksekusi Urutan SOP Keuangan

Tanggal update: 2026-02-28
Referensi urutan: `docs/sop-keuangan-operasional-toko-kecil.md` (Bagian 14)

## Ringkasan Status

1. Template mingguan + printable harian sebagai kontrol rutin: **SELESAI**
   - `docs/template-rekap-mingguan-keuangan-1-halaman.md`
   - `docs/checklist-keuangan-printable-1-halaman.md`
2. Kode alasan standar + SLA insiden: **SELESAI (fase berjalan)**
   - Kode alasan di API/UI:
     - `app/Http/Controllers/Api/VoidSaleController.php`
     - `app/Http/Controllers/Api/StockAdjustmentController.php`
     - `resources/views/void-refund.blade.php`
   - SLA dokumen:
     - `docs/sla-insiden-operasional-keuangan.md`
3. Batas otorisasi transaksi sesuai role: **SELESAI (enforced)**
   - `app/Services/TransactionAuthorizationPolicyService.php`
   - `app/Services/UserRoleResolverService.php`
   - `app/Services/VoidService.php`
   - `app/Services/StockAdjustmentService.php`
   - `routes/api.php`
4. Kebijakan stok minimum/ROP + prioritas hutang supplier: **SELESAI (otomasi inti)**
   - Alert stok berbasis ROP:
     - `app/Http/Controllers/Api/InventoryAlertController.php`
   - Reminder hutang jatuh tempo H-3/H-1:
     - `app/Jobs/DetectSupplierDebtDueJob.php`
     - `routes/console.php`
5. Otomasi aplikasi bertahap sesuai roadmap: **SELESAI (fase target saat ini)**
   - Dashboard kontrol harian (5 KPI) sudah tersedia via API dan UI.
   - Snapshot closing harian sudah otomatis saat tutup shift dan bisa dipicu manual.
   - Export harian/mingguan CSV untuk kontrol operasional sudah tersedia.
    - Verifikasi runtime endpoint (tenant aktif):
       - `GET /api/v1/finance-control-daily` => 200
       - `POST /api/v1/finance-control-daily/snapshot` => 200
       - `GET /api/v1/finance-control-daily/export` => 200 (`text/csv`)
    - UAT checklist operasional:
       - `docs/uat-checklist-kontrol-harian-keuangan.md`
    - Handover operasional 1 halaman:
       - `docs/handover-operasional-kontrol-keuangan-2026-02-28.md`
    - Handover printable 1 halaman:
       - `docs/handover-operasional-kontrol-keuangan-printable-1-halaman.md`

## Bukti Verifikasi Test

- `tests/Feature/FinancialControlsPhase1Test.php` (PASS)
- `tests/Feature/AuthorizationAndRopPolicyTest.php` (PASS)
- `tests/Feature/FinanceControlDashboardTest.php` (PASS)
- Perintah verifikasi gabungan terbaru:
   - `php artisan test --filter="AuthorizationAndRopPolicyTest|FinanceControlDashboardTest"`
