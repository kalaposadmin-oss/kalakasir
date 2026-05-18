# APK Modules A-H Execution Scope (Priority 6)

Tanggal: 2026-03-18
Pemilik: Android + Backend + QA
Status: In Progress

## Tujuan
Mengeksekusi prioritas implementasi (point 6) dan rekomendasi scope agar APK siap untuk rollout bertahap dengan risiko operasional rendah.

## Delta yang Sudah Dikerjakan di Batch Ini
1. POS checkout kini mendukung diskon nominal aktual pada finalisasi transaksi (bukan hardcoded 0).
2. Ringkasan pembayaran menampilkan subtotal, diskon, dan total bayar yang dihitung ulang.
3. Dashboard Home diubah menjadi data-driven untuk metrik shift aktif, kas awal, penjualan shift, penjualan hari ini, dan jumlah transaksi.
4. Sync adapter ditingkatkan dengan fallback endpoint:
   - api/v1/sync/actions -> sync/actions
   - api/v1/sync/pull -> sync/pull
5. APK debug terbaru sudah dibuild dan dipublikasikan ke folder apk.

## Delta Tambahan Batch Lanjutan
1. History transaksi kini memiliki aksi Void di detail transaksi.
2. Void akan menandai status lokal menjadi pending refund, menulis audit event, enqueue outbox event, lalu trigger sync segera.
3. Persentase pajak di APK tidak lagi hardcoded; kini mengikuti policy key `tax_percent` (default 11).
4. Tampilan detail transaksi menampilkan label pajak dinamis sesuai policy.

## Delta Batch Purchase Operasional
1. Layar Purchase Management tidak lagi placeholder; sudah terhubung ke PurchaseViewModel.
2. APK kini mendukung aksi operasional lokal:
   - tambah supplier,
   - finalize purchase,
   - mark purchase received,
   - record purchase return.
3. Semua aksi purchase di atas menggunakan repository + outbox sinkronisasi yang sudah ada.

## Delta Batch Stock Operasional
1. Ditambahkan `InventoryOpsRepository` untuk stock adjustment dan stock transfer lokal.
2. Kartu Stok pada History kini menjadi layar aksi operasional (bukan placeholder):
   - simpan stock adjustment,
   - simpan stock transfer.
3. Setiap aksi stock akan memperbarui stok lokal produk dan enqueue outbox `RECORD_STOCK_MOVEMENT`.

## Delta Batch Finance + Observability
1. Ditambahkan wiring `FinanceOpsRepository` di composition root untuk aksi arus kas dan snapshot laporan harian.
2. Menu Setelan -> Kebijakan Keuangan kini menampilkan metrik data-driven:
   - penjualan harian,
   - jumlah transaksi,
   - estimasi laba kotor,
   - kas masuk/keluar shift aktif,
   - estimasi nilai stok.
3. Ditambahkan form aksi finance operasional:
   - simpan arus kas masuk/keluar lokal,
   - buat snapshot laporan harian lokal.
4. Kedua aksi finance di atas menulis outbox dan langsung trigger sinkronisasi.
5. Ditambahkan observabilitas sinkronisasi per action type (total + gagal) di layar Kebijakan Keuangan.

## Delta Batch Governance Depth + Payload Refinement
1. Ditambahkan policy key governance baru:
   - `gov_current_role`
   - `gov_void_requires_approval`
2. Menu Governance kini operasional untuk mengatur role aktif (kasir/supervisor/admin) dan toggle policy void requires approval.
3. Enforce policy di alur void transaksi:
   - saat role aktif kasir dan policy approval aktif, void akan ditolak di APK.
4. Event outbox void sale diperkaya untuk parity payload:
   - `requested_by_role`
   - `approval_required`
5. Enforcement governance diperluas lintas aksi (bukan hanya void):
   - stock adjustment,
   - stock transfer,
   - cash movement,
   - snapshot laporan harian.
   Saat role aktif `kasir` dan policy approval aktif, aksi di atas diblokir dan dicatat ke audit log.
6. Payload outbox inventory dan finance kini ikut membawa metadata parity:
   - `source_module`,
   - `requested_by_role`,
   - `approval_required`.

## Scope Rilis yang Disepakati

### Scope Pilot (Kasir-Centric)
Masuk scope:
1. POS offline: pilih produk, cart, pembayaran, simpan lokal.
2. Sinkronisasi otomatis/manual dengan observabilitas pending/failed.
3. Shift monitoring dasar (status, angka penjualan shift, histori ringkas).
4. Product read-optimized untuk operasi kasir.
5. Lock period enforcement untuk blokir transaksi saat periode terkunci.

Tidak masuk scope pilot:
1. Pengganti penuh backoffice pembelian.
2. Pengganti penuh inventory control lanjutan (transfer/adjustment lifecycle).
3. Pengganti penuh laporan keuangan dan governance web.

### Scope Pengganti Web Penuh (Target Lanjutan)
Wajib tersedia sebelum menghapus ketergantungan web:
1. Purchase lifecycle end-to-end (PO, receive, close, return) di APK.
2. Stock transfer/adjustment lengkap dengan approval flow.
3. Finance dan governance setara kebutuhan operasional harian.
4. Parity rules penuh (tax, discount, void/refund, lock period, valuation impact).
5. Kontrak sync backend final + uji beban + uji recovery jaringan.

## Rencana Eksekusi Priority 6 (Actionable)

### P1 - Stabilitas Operasional (sedang jalan)
1. Finalisasi kontrak endpoint sync dengan backend (status code, idempotency, payload schema).
2. Lengkapi parity checkout (diskon selesai, tax dan refund action menyusul).
3. Pastikan shift operasional benar-benar data-driven di seluruh layar utama.

### P2 - Menutup Gap Backoffice Kritis
1. Implement flow purchase nyata (bukan placeholder).
2. Implement flow stock transfer/adjustment inti.
3. Buka laporan operasional minimum yang dapat ditindak (penjualan, stok, pembelian).

### P3 - Parity Lanjutan
1. Governance dan finance pro secara bertahap sesuai role.
2. Perluasan automation QA regression untuk skenario offline/online flip.

## Definisi Selesai per Tahap

### Definition of Done P1
1. Build APK hijau.
2. UAT kasir offline lulus untuk checkout + sync + lock period.
3. Tidak ada blocker P0/P1 pada transaksi penjualan.

### Definition of Done P2
1. Alur purchase dan stock control dasar berjalan end-to-end di APK.
2. Data sinkron konsisten terhadap backend pada skenario normal.

### Definition of Done P3
1. APK dapat menggantikan web untuk operasi harian sesuai role yang ditargetkan.
2. Dashboard dan laporan minimum sudah dapat dipakai untuk keputusan operasional.

## Owner Matrix
1. Android: UI flow, local persistence, sync client, UX operasional.
2. Backend: endpoint contract, validation, authorization, reconciliation.
3. QA: skenario regresi lintas modul, soak test offline-online, release gate.

## Risiko dan Mitigasi
1. Risiko mismatch endpoint sync: gunakan fallback path + contract test backend.
2. Risiko data drift saat reconnect: wajib idempotency + retry policy + audit trail.
3. Risiko scope creep: patuhi scope pilot, hold fitur non-kritis untuk gelombang berikutnya.

## Eksekusi Berurutan 1-10 (Batch Saat Ini)
1. Stabilkan build pipeline Android: selesai.
   - Menangani failure cache/lock Kotlin-Gradle dan memastikan build hijau kembali.
2. Governance matrix per role: selesai.
   - Role aktif `kasir/supervisor/admin` dipakai untuk gate aksi.
3. Approval policy granular: selesai.
   - Ditambahkan policy terpisah untuk void, stock adjustment, stock transfer, cash movement, daily snapshot, dan purchase mutation.
4. Enforcement lintas flow: selesai.
   - Policy diterapkan di callback aksi history/settings, dengan audit event saat diblokir.
5. Standardisasi payload outbox lintas domain: selesai.
   - Inventory, finance, purchase membawa `source_module`, `requested_by_role`, `approval_required`.
6. Observabilitas governance UI: selesai.
   - Ditambahkan ringkasan governance + policy matrix di layar Governance.
7. Hardening idempotency/deduplikasi: selesai parsial.
   - Idempotency key inventory/finance dibuat deterministik berbasis entity/action.
   - Purchase return tetap menggunakan key deterministik fingerprint (sudah ada sebelumnya).
8. Uji end-to-end offline/online: selesai parsial.
   - Validasi teknis dilakukan via compile/build artifact.
   - Uji UI end-to-end role-based di perangkat/emulator masih menjadi langkah QA manual.
9. Build final + publish APK: selesai.
   - Assemble debug sukses, artifact dipublish ke folder `apk`.
10. Dokumentasi eksekusi: selesai.
   - Delta implementasi dan status eksekusi batch ini dicatat di dokumen ini.

## Eksekusi Berurutan 1-8 (Batch Lanjutan)
1. Kontrak payload governance ke backend: selesai dengan adaptasi.
   - Catatan: endpoint `sync/actions` dan `sync/pull` tidak ditemukan di repo Laravel ini.
   - Adaptasi: kontrak governance diterapkan di endpoint aksi langsung (`void`, `stock`, `purchase`, `purchase-return`, `finance snapshot`).
2. Validasi server-side role/policy minimum: selesai.
   - Ditambahkan helper `GovernanceRequestContext` untuk validasi `requested_by_role`, `approval_required`, dan role actor.
3. Hardening idempotency backend: selesai parsial.
   - Alur idempotency berbasis key deterministik diteruskan dari APK.
   - Endpoint aksi langsung kini memproses metadata governance secara konsisten.
4. Observabilitas sync di APK: lanjut, sudah ditingkatkan.
   - Ringkasan per action type tetap aktif + reason blokir governance kini lebih jelas di UI.
5. Governance UX: selesai.
   - Layar Governance menampilkan daftar aksi yang terkunci untuk role kasir saat policy aktif.
6. Regression test: selesai.
   - Ditambahkan `GovernancePayloadPolicyEnforcementTest` (3 test, pass).
7. Build & artifact final: selesai.
   - Build Android hijau dan APK terbaru dipublish ke `apk/kalakasir-debug.apk`.
8. Dokumentasi & readiness: selesai.
   - Status batch dan batasan lingkungan backend dicatat di dokumen ini.
