# Panduan Paket Langganan Kalakasir (Final S -> M -> P)

Dokumen ini menjelaskan struktur final paket yang logis untuk perjalanan UMKM.

Prinsip paket:
- `S` = tahu kondisi usaha
- `M` = mulai mengelola usaha
- `P` = mengontrol usaha secara profesional

Status dokumen: aktif  
Sumber acuan: `app/config/feature-gating.json`  
Terakhir diperbarui: 2026-03-06

## 1. Paket S - Starter

Target:
- UMKM kecil, toko baru, warung, kedai kecil.

Tujuan:
- Pemilik usaha tahu kondisi dasar bisnis: untung/rugi sederhana, stok, produk paling laku, dan kapan harus kulakan.

Fitur utama:
- POS: checkout, refund/void, hold bill, split payment sederhana.
- Shift: buka/tutup shift, riwayat shift.
- Riwayat: riwayat transaksi penjualan.
- Produk: produk, kategori, harga jual, harga modal, stok, stok minimum.
- Stok: stok masuk/keluar, penyesuaian stok sederhana, notifikasi stok menipis.
- Supplier basic: daftar supplier, histori pembelian sederhana.
- Pembelian basic: catat pembelian, update stok otomatis.
- Laporan dasar: ringkasan penjualan, produk paling laku, stok menipis, laporan stok sederhana, laba rugi sederhana.
- Dashboard insight: penjualan hari ini, laba hari ini, produk paling laku, produk stok menipis.

Contoh laporan laba rugi sederhana:
- Penjualan
- Modal barang
- Laba kotor

Limit contoh:
- 1 cabang
- 2 staff
- 1000 produk

Nilai utama Starter:
- UMKM bisa tahu kondisi usahanya.

## 2. Paket M - Medium

Target:
- UMKM berkembang, mulai punya karyawan, transaksi lebih besar.

Tujuan:
- Pemilik usaha mulai mengelola operasional bisnis dengan lebih rapi.

Semua fitur Starter, ditambah:
- Manajemen pembelian lengkap: purchase order, penerimaan barang, retur pembelian.
- Supplier management: histori pembelian supplier, performa supplier.
- Manajemen pelanggan: database pelanggan, histori pembelian pelanggan.
- Multi staff: manajemen staff, akses staff sederhana.
- Multi cabang basic: beberapa cabang, laporan per cabang.
- Laporan bisnis: laporan penjualan lengkap, laporan pembelian, laporan persediaan, laporan pembayaran.
- Export laporan: CSV/PDF.
- Shift monitoring: laporan shift lebih lengkap.
- Dashboard insight lanjutan: penjualan per cabang, performa produk, performa kasir.

Limit contoh:
- 3 cabang
- 10 staff
- 5000 produk

Nilai utama Medium:
- UMKM bisa mengelola operasional bisnis dengan sistem.

## 3. Paket P - Pro

Target:
- Bisnis yang sudah serius, multi cabang, dan butuh kontrol lebih kuat.

Tujuan:
- Pemilik usaha bisa mengontrol bisnis secara profesional.

Semua fitur Medium, ditambah:
- Akuntansi: laba rugi lengkap, neraca, arus kas, COA, chart of accounts.
- Lock period: kunci periode laporan.
- Inventory advanced: stock card, FIFO layers, valuation lanjutan.
- Governance: role & permission, audit log, activity log.
- Observability: event log, system audit.
- Integrasi: API access, integrasi sistem lain.
- Multi cabang advanced: laporan konsolidasi, kontrol cabang.
- Export advanced: export laporan lengkap.

Limit contoh:
- Unlimited cabang
- Unlimited staff
- Unlimited produk

Nilai utama Pro:
- Bisnis bisa dikontrol dan diaudit secara profesional.

## 4. Ringkasan Tingkatan

| Paket | Fokus | Nilai untuk UMKM |
|---|---|---|
| Starter | tahu kondisi usaha | tahu laba, stok, dan produk laku |
| Medium | mengelola operasional | staff, supplier, pembelian rapi |
| Pro | kontrol bisnis profesional | akuntansi, audit, governance |

## 5. FAQ Singkat

Q: Kenapa beberapa fitur terlihat tetapi belum bisa dipakai?  
A: Sistem menggunakan model `visible but locked`. Fitur tetap terlihat, namun diarahkan ke halaman langganan jika paket belum memenuhi.

Q: Bagaimana cara cek paket aktif?  
A: Buka `Pengaturan > Langganan` (`/settings/subscription`).

Q: Kode paket artinya apa?  
A:
- `S` = Starter
- `M` = Medium
- `P` = Pro

## 6. Versi Siap PDF

Untuk kebutuhan cetak dan ekspor PDF, gunakan:
- `app/docs/paket-langganan-quick-compare-printable-a4.html` (utama, paling rapi untuk Print to PDF)
- `app/docs/paket-langganan-quick-compare-printable-1-halaman.md` (opsi markdown)

