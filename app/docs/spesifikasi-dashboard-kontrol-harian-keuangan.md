# Spesifikasi Dashboard Kontrol Harian Keuangan

Tanggal: 2026-02-28  
Tujuan: Menyediakan 5 angka wajib untuk kontrol cepat owner/admin setiap akhir hari.

## KPI Wajib (Top Cards)

1. Penjualan Hari Ini
2. HPP Hari Ini
3. Kas Akhir Hari
4. Hutang Supplier Jatuh Tempo (H-7)
5. Selisih Kas Shift (Total)

## Definisi KPI

- Penjualan Hari Ini: total penjualan gross dari transaksi valid (exclude void final).
- HPP Hari Ini: total HPP dari item terjual hari itu.
- Kas Akhir Hari: saldo kas sistem setelah semua transaksi hari itu.
- Hutang Jatuh Tempo (H-7): total hutang supplier yang jatuh tempo dalam 7 hari ke depan.
- Selisih Kas Shift: akumulasi selisih kas fisik vs sistem dari shift yang sudah ditutup.

## Rule Warna Status

- Hijau: normal
- Kuning: perlu perhatian
- Merah: butuh tindakan segera

Contoh:
- Selisih kas = 0 → hijau
- Selisih kas > 0 dan <= ambang internal → kuning
- Selisih kas > ambang internal → merah

## Komponen Tampilan

- Header periode: tanggal hari ini + pilihan branch/location.
- Top cards: 5 KPI wajib.
- Panel alert: daftar 3 alert prioritas tertinggi.
- Panel tindakan cepat:
  - Buka daftar hutang jatuh tempo
  - Buka rekap selisih kas
  - Buka produk stok kritis

## Data Refresh

- Auto-refresh: setiap 5 menit.
- Manual refresh: tombol refresh di header.
- Snapshot final: tersimpan saat closing harian.

## Kriteria Selesai (DoD)

- Semua KPI tampil untuk tenant/branch aktif.
- Data sinkron dengan laporan harian.
- Alert merah memunculkan link tindakan.
- Owner/Admin dapat membaca dashboard < 30 detik untuk keputusan cepat.
