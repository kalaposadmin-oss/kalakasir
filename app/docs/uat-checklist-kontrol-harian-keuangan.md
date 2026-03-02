# UAT Checklist Kontrol Harian Keuangan

Tanggal: 2026-02-28  
Lingkup: Dashboard KPI harian, snapshot manual/otomatis, export CSV, dan policy otorisasi nominal.

## Prasyarat

- User login dengan role `admin` atau `owner` untuk aksi snapshot manual.
- Header tenant aktif (`X-Tenant-Id`) dan data transaksi harian tersedia.
- Migrasi `daily_finance_control_snapshots` sudah applied.

## Skenario UAT

1. Buka menu `Laporan Keuangan` (`/finance-summary`)
   - Ekspektasi: panel **Kontrol Harian** tampil dengan 5 KPI.
2. Validasi KPI harian
   - Ekspektasi: nilai `Penjualan`, `HPP`, `Kas Tutup`, `Selisih Kas`, `Hutang Jatuh Tempo 7 Hari` terisi dari API `/api/v1/finance-control-daily`.
3. Klik tombol `Simpan Snapshot`
   - Ekspektasi: muncul status sukses dan data snapshot tersimpan (`status: snapshotted`).
4. Klik tombol `Export CSV`
   - Ekspektasi: file CSV terunduh dari endpoint `/api/v1/finance-control-daily/export`.
5. Tutup shift kasir
   - Ekspektasi: snapshot harian otomatis tercatat tanpa trigger manual.
6. Uji void nominal kecil oleh `cashier` (<= 250000)
   - Ekspektasi: void berhasil tanpa manager PIN.
7. Uji void nominal besar oleh `cashier` (> 250000)
   - Ekspektasi: sistem meminta manager PIN (`MANAGER_PIN_REQUIRED`).
8. Uji post adjustment nominal kecil oleh `admin` (<= 500000)
   - Ekspektasi: posting berhasil.
9. Uji post adjustment nominal besar oleh `admin` (> 500000)
   - Ekspektasi: ditolak dengan `AUTHORIZATION_LIMIT_EXCEEDED`.

## Evidence Otomatis (Test)

- `tests/Feature/FinanceControlDashboardTest.php`
- `tests/Feature/AuthorizationAndRopPolicyTest.php`

Perintah cepat:

- `php artisan test --filter="AuthorizationAndRopPolicyTest|FinanceControlDashboardTest"`
