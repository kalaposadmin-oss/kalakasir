# UI Manual QA Checklist (Production Readiness)

Tanggal: 2026-02-27  
Aplikasi: Kalakasir POS Web  
Scope: Kasir, Void/Refund, Purchase, Receiving, Stock Adjustment, Observability, Role UX

## 1) Persiapan Uji

- Environment app aktif dan data seed tersedia.
- Minimal 2 akun:
  - `cashier`
  - `admin` (atau `owner/admin` + supervisor/admin yang punya manager PIN)
- Pastikan worker/scheduler aktif untuk observability/anomaly.
- Browser mobile viewport: 360px, 390px, 430px.

Status template eksekusi:
- Result: `PASS` / `FAIL`
- Evidence: screenshot/video + timestamp
- Notes: bug/ketidaksesuaian

---

## 2) Kasir End-to-End (Speed & Safety)

### TC-KASIR-01: Bootstrap kasir
Langkah:
1. Login sebagai cashier.
2. Buka `/kasir`.

Ekspektasi:
- Data produk/kategori tampil.
- Shift status tampil sesuai data.
- Tidak ada error blocking.

Result: ____  
Evidence: ____  
Notes: ____

### TC-KASIR-02: Add to cart + stock guard
Langkah:
1. Tambah item track stock sampai batas stok.
2. Tambah 1 lagi melebihi stok.

Ekspektasi:
- Item hingga batas berhasil.
- Melebihi stok ditolak dengan pesan jelas.

Result: ____  
Evidence: ____  
Notes: ____

### TC-KASIR-03: Checkout in-flight guard
Langkah:
1. Masuk `/payment-method` dari cart.
2. Klik `KONFIRMASI PEMBAYARAN` berulang cepat (double click).

Ekspektasi:
- Tombol disable saat in-flight.
- Tidak terjadi double transaction.
- Redirect ke receipt/hasil checkout normal.

Result: ____  
Evidence: ____  
Notes: ____

### TC-KASIR-04: Checkout timeout UX
Langkah:
1. Simulasikan jaringan lambat/drop (DevTools throttling/offline sesaat).
2. Submit checkout.

Ekspektasi:
- Muncul pesan timeout: cek riwayat sebelum ulang.
- Tidak auto-repeat blind retry.

Result: ____  
Evidence: ____  
Notes: ____

---

## 3) Void/Refund (Manager PIN Server-side)

### TC-VOID-01: PIN required
Langkah:
1. Buka `/sales-void-refund?sale_id=<id>`.
2. Kosongkan PIN, submit void.

Ekspektasi:
- Ditolak, error code `MANAGER_PIN_REQUIRED` termap jelas di UI.

Result: ____  
Evidence: ____  
Notes: ____

### TC-VOID-02: PIN invalid
Langkah:
1. Isi PIN salah.
2. Submit.

Ekspektasi:
- Ditolak, error `MANAGER_PIN_INVALID`.

Result: ____  
Evidence: ____  
Notes: ____

### TC-VOID-03: PIN lockout
Langkah:
1. Ulangi PIN salah sampai batas attempt.
2. Coba submit lagi.

Ekspektasi:
- Ditolak `MANAGER_PIN_LOCKED` (rate-limit aktif).

Result: ____  
Evidence: ____  
Notes: ____

### TC-VOID-04: PIN valid + audit approver
Langkah:
1. Isi PIN valid supervisor/admin.
2. Submit void.
3. Cek sale detail / audit terkait.

Ekspektasi:
- Void sukses.
- `void_approved_by` terisi.
- Audit event `sale_void_authorized` tercatat.

Result: ____  
Evidence: ____  
Notes: ____

### TC-VOID-05: Truthful confirmation
Langkah:
1. Pilih beberapa item/qty.
2. Klik proses void.

Ekspektasi:
- Modal konfirmasi menampilkan ringkasan item + total refund.

Result: ____  
Evidence: ____  
Notes: ____

---

## 4) Purchase / Receiving / Adjustment Critical Action

### TC-PO-01: Create PO confirmation summary
Langkah:
1. Buka `/purchases/new`.
2. Isi supplier + item.
3. Submit Draft / Buat Pesanan.

Ekspektasi:
- Konfirmasi menampilkan supplier, item count, total qty, estimasi nilai.
- Tombol submit in-flight guarded.

Result: ____  
Evidence: ____  
Notes: ____

### TC-RECV-01: Receiving confirmation summary
Langkah:
1. Buka `/receiving/action?purchase_id=<id>`.
2. Ubah qty diterima.
3. Klik simpan.

Ekspektasi:
- Konfirmasi menampilkan PO, jumlah item, qty order vs diterima.
- Tombol submit guarded.

Result: ____  
Evidence: ____  
Notes: ____

### TC-ADJ-01: Adjustment confirmation summary
Langkah:
1. Buka `/stock-adjustment`.
2. Isi qty + reason.
3. Klik konfirmasi.

Ekspektasi:
- Konfirmasi menampilkan lokasi, perubahan qty, alasan/catatan.
- Tombol submit guarded.

Result: ____  
Evidence: ____  
Notes: ____

---

## 5) Observability & Anomaly UI (Admin)

### TC-OBS-01: Queue lag indicator
Langkah:
1. Login admin.
2. Buka `/settings/observability`.

Ekspektasi:
- Queue depth, failed jobs 5m, lag P95 tampil.
- Badge status `OK/WARN/CRIT` sesuai threshold.

Result: ____  
Evidence: ____  
Notes: ____

### TC-OBS-02: Anomaly summary
Langkah:
1. Di halaman observability, cek panel summary.

Ekspektasi:
- Count anomaly by severity/type tampil.

Result: ____  
Evidence: ____  
Notes: ____

### TC-OBS-03: Resolve action
Langkah:
1. Pilih anomaly open.
2. Klik `Resolve`.

Ekspektasi:
- Konfirmasi muncul.
- Status anomaly berubah resolved setelah refresh/list reload.

Result: ____  
Evidence: ____  
Notes: ____

### TC-OBS-04: Filter behavior
Langkah:
1. Uji filter severity + resolved.

Ekspektasi:
- List anomaly berubah sesuai filter.

Result: ____  
Evidence: ____  
Notes: ____

---

## 6) Role-Based UI & Access

### TC-ROLE-01: Cashier menu hiding
Langkah:
1. Login cashier.
2. Buka menu drawer.

Ekspektasi:
- Menu admin-only tidak tampil.
- Aksi admin tidak bisa dijalankan dari UI.

Result: ____  
Evidence: ____  
Notes: ____

### TC-ROLE-02: Admin capabilities visible
Langkah:
1. Login admin.
2. Buka menu drawer.

Ekspektasi:
- Menu admin-only tampil (settings/reports/management).
- Observability menu tersedia.

Result: ____  
Evidence: ____  
Notes: ____

### TC-ROLE-03: Tenant mismatch clarity
Langkah:
1. Simulasikan tenant mismatch (header/sesi tidak cocok).
2. Trigger request kritikal.

Ekspektasi:
- UI menampilkan pesan jelas terkait tenant mismatch.

Result: ____  
Evidence: ____  
Notes: ____

---

## 7) Responsive & Visual Quality

### TC-UI-01: Responsive mobile viewport
Langkah:
1. Uji viewport 360, 390, 430.
2. Cek kasir, payment, void, observability.

Ekspektasi:
- Tidak ada overflow layout kritikal.
- CTA tetap terlihat dan clickable.

Result: ____  
Evidence: ____  
Notes: ____

### TC-UI-02: Loading/empty/error states
Langkah:
1. Uji kondisi data kosong dan error API.

Ekspektasi:
- Empty state informatif.
- Error state tidak memblok total navigasi.

Result: ____  
Evidence: ____  
Notes: ____

---

## 8) Sign-off

- QA Engineer: __________
- Product Owner: __________
- Tanggal: __________
- Keputusan:
  - [ ] Ready for staged rollout
  - [ ] Needs fixes before rollout

## 9) Defect Log Ringkas

| ID | Severity | Halaman/Flow | Deskripsi | Langkah Repro | Status |
|---|---|---|---|---|---|
| BUG-___ | P0/P1/P2 | ___ | ___ | ___ | Open/Fixed |
