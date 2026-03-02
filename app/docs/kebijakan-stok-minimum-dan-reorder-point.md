# Kebijakan Stok Minimum dan Reorder Point

Tanggal: 2026-02-28

## Tujuan
Menjaga ketersediaan barang tanpa overstock melalui batas stok minimum dan reorder point.

## Definisi

- Stok Minimum: batas terendah stok aman.
- Reorder Point (ROP): titik trigger untuk melakukan pembelian ulang.
- Lead Time: waktu rata-rata dari order ke barang diterima.

## Rumus Dasar

- Rata-rata pemakaian harian = total penjualan unit / jumlah hari.
- Safety stock (sederhana) = pemakaian 2 hari.
- ROP = (rata-rata pemakaian harian x lead time) + safety stock.

## Aturan Praktis

1. SKU cepat laku: safety stock minimal 3 hari.
2. SKU lambat: safety stock 1–2 hari.
3. SKU musiman: review ROP mingguan saat musim puncak.
4. Jika stok <= ROP, status produk menjadi "Perlu Beli".

## Kategori Prioritas

- Prioritas A: produk kontribusi penjualan tinggi.
- Prioritas B: produk penjualan menengah.
- Prioritas C: produk penjualan rendah.

Aturan pembelian:
- Prioritas A dibeli dulu saat kas terbatas.
- Prioritas C ditahan kecuali ada permintaan khusus.

## Review Berkala

- Review ROP setiap minggu.
- Update lead time supplier setiap bulan.
- Validasi SKU slow-moving untuk keputusan diskon/clearance.

## Integrasi Laporan

- Gunakan laporan persediaan untuk identifikasi SKU kritis.
- Gunakan laporan penjualan untuk hitung pemakaian rata-rata.
