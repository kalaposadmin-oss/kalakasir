# Handover Operasional Kontrol Keuangan — Printable 1 Halaman

Tanggal: 2026-02-28  
Gunakan dokumen ini sebagai checklist harian supervisor/admin saat operasional kasir.

## A. Pre-Shift (Sebelum Buka)

- [ ] Cek endpoint `GET /api/v1/finance-control-daily` respons normal.
- [ ] Pastikan role user aktif sesuai tugas (`cashier` / `admin` / `owner`).
- [ ] Pastikan dashboard menampilkan 5 KPI (Penjualan, HPP, Kas Tutup, Selisih Kas, Hutang 7 Hari).

## B. Saat Operasional

- [ ] Pantau anomali void nominal besar (> 250000) agar ada approval PIN.
- [ ] Pantau post adjustment nominal besar (> 500000) agar dieksekusi role sesuai policy.
- [ ] Jika diperlukan audit cepat, lakukan export CSV dari halaman Laporan Keuangan.

## C. End-Shift (Tutup Shift)

- [ ] Tutup shift kasir normal.
- [ ] Verifikasi snapshot harian tercatat (otomatis saat shift close).
- [ ] Jika snapshot belum ada, jalankan tombol **Simpan Snapshot** manual.
- [ ] Simpan file CSV harian sebagai arsip audit.

## D. Decision Matrix Cepat

- Void <= 250000 oleh cashier: **boleh langsung**.
- Void > 250000 oleh cashier: **wajib manager/admin/owner PIN** (`MANAGER_PIN_REQUIRED`).
- Adjustment post <= 500000 oleh admin: **boleh**.
- Adjustment post > 500000 oleh admin: **ditolak**, eskalasi owner (`AUTHORIZATION_LIMIT_EXCEEDED`).

## E. Error Code dan Tindakan

- `MANAGER_PIN_REQUIRED` → minta approval PIN role yang berwenang.
- `AUTHORIZATION_LIMIT_EXCEEDED` → eskalasi ke role lebih tinggi (admin/owner).
- `INVALID_TRANSACTION_DATE` → koreksi tanggal transaksi.
- `LOCK_PERIOD_CLOSED` → stop proses, eskalasi ke admin finance.

## F. Eskalasi

- PIC shift: Supervisor toko.
- PIC aplikasi: Admin sistem.
- Eskalasi L2: Owner / Finance Lead.
- SLA insiden: lihat `docs/sla-insiden-operasional-keuangan.md`.

## G. Bukti Verifikasi Teknis (PASS)

- `tests/Feature/AuthorizationAndRopPolicyTest.php`
- `tests/Feature/FinanceControlDashboardTest.php`
- Endpoint smoke:
  - `GET /api/v1/finance-control-daily` => 200
  - `POST /api/v1/finance-control-daily/snapshot` => 200
  - `GET /api/v1/finance-control-daily/export` => 200 (`text/csv`)
