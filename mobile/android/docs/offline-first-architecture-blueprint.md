# Offline-First Android UMKM Blueprint

## Tujuan Produk
- Aplikasi tetap operasional saat offline.
- Transaksi lokal selesai cepat tanpa menunggu server.
- Sinkronisasi otomatis dan aman saat koneksi kembali.
- UX sederhana untuk pengguna non-teknis.

## Modul Bisnis
- POS (penjualan, pembayaran, diskon, hutang pelanggan)
- Produk
- Pelanggan
- Supplier
- Pembelian
- Stok dan stock opname
- Shift kasir
- Kas masuk/keluar
- Hutang piutang dasar
- Laporan sederhana

## Prinsip Teknis
1. Semua command menulis ke Room dulu.
2. Semua perubahan bisnis membuat record `SyncAction` di outbox.
3. WorkManager menjalankan `push` lalu `pull`.
4. Konflik ditangani dengan aturan per jenis data.
5. Endpoint server wajib idempotent.

## Sync Status
- `DRAFT`
- `PENDING_SYNC`
- `SYNCED`
- `FAILED`
- `CONFLICT`

## Aturan Konflik
- Master data: last-write-wins + version check.
- Penjualan finalized: immutable, koreksi via reversal/void.
- Pembayaran: immutable, koreksi via reversal entry.
- Stok: append movement, jangan overwrite saldo langsung.

## KPI Teknis
- Waktu transaksi offline < 1 detik.
- Tidak ada duplikasi transaksi saat retry.
- Queue sync pulih setelah koneksi kembali.
- Aman terhadap app kill mendadak (atomic local transaction).
