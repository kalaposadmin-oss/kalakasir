# Handover Operasional Kontrol Keuangan (1 Halaman)

Tanggal: 2026-02-28  
Scope: Dashboard kontrol harian, snapshot, export CSV, dan batas otorisasi transaksi.

## 1) Fitur Aktif

- Dashboard KPI harian (5 KPI): Penjualan, HPP, Kas Tutup, Selisih Kas, Hutang Jatuh Tempo 7 Hari.
- Snapshot harian:
  - Otomatis saat tutup shift.
  - Manual dari tombol **Simpan Snapshot** di halaman Laporan Keuangan.
- Export CSV harian/mingguan dari endpoint finance control.
- Batas otorisasi transaksi aktif:
  - Void: berbasis nominal + role.
  - Stock adjustment post: berbasis nominal + role.

## 2) Endpoint Operasional + Status Verifikasi

Tenant uji: `019c9578-3f84-7209-a55f-19a32dd99b41`

- `GET /api/v1/finance-control-daily` => 200
- `POST /api/v1/finance-control-daily/snapshot` => 200
- `GET /api/v1/finance-control-daily/export` => 200 (`text/csv`)

## 3) UAT Per Role (Ringkas)

### Cashier

- Void <= 250000: berhasil tanpa manager PIN.
- Void > 250000: ditolak dengan `MANAGER_PIN_REQUIRED` sampai ada approval PIN.

### Admin

- Post adjustment <= 500000: berhasil.
- Post adjustment > 500000: ditolak dengan `AUTHORIZATION_LIMIT_EXCEEDED`.
- Snapshot manual harian: boleh.

### Owner

- Semua skenario di atas bisa di-approve sesuai policy.
- Tetap wajib review log jika anomali berulang.

## 4) Error Code Penting + Tindakan Cepat

- `MANAGER_PIN_REQUIRED`
  - Tindakan: minta approval manager/admin/owner sesuai nominal.
- `AUTHORIZATION_LIMIT_EXCEEDED`
  - Tindakan: eskalasi ke role lebih tinggi (admin/owner) sesuai batas policy.
- `INVALID_TRANSACTION_DATE`
  - Tindakan: koreksi tanggal transaksi dan ulangi proses.
- `LOCK_PERIOD_CLOSED`
  - Tindakan: jangan paksa posting; eskalasi ke admin finance untuk periode terkunci.

## 5) Checklist Go-Live Harian

### Sebelum Buka

- Cek endpoint `finance-control-daily` dapat respons normal.
- Cek role user kasir/admin valid.

### Saat Operasional

- Monitor selisih kas dan hutang jatuh tempo 7 hari.
- Jika ada void nominal besar, pastikan approval flow berjalan.

### Saat Tutup Shift

- Pastikan shift ditutup normal.
- Pastikan snapshot harian tercatat (otomatis).
- Simpan export CSV untuk arsip audit harian.

## 6) Evidence Otomatis (PASS)

- `tests/Feature/AuthorizationAndRopPolicyTest.php`
- `tests/Feature/FinanceControlDashboardTest.php`
- Command: `php artisan test --filter="AuthorizationAndRopPolicyTest|FinanceControlDashboardTest"`

## 7) Risiko + Fallback

- Risiko: data KPI nol karena belum ada transaksi/snapshot pada tanggal berjalan.
  - Fallback: trigger snapshot manual, lalu re-check dashboard.
- Risiko: penolakan transaksi karena mismatch role.
  - Fallback: login role yang sesuai policy atau eskalasi ke owner.

## 8) PIC & Eskalasi

- PIC Operasional Toko: Supervisor Shift
- PIC Aplikasi: Admin Sistem
- Eskalasi level-2: Owner / Finance Lead
- SLA awal insiden: ikuti `docs/sla-insiden-operasional-keuangan.md`
