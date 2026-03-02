# Aturan Prioritas Pembayaran Hutang Supplier

Tanggal: 2026-02-28

## Tujuan
Menetapkan urutan pembayaran hutang supplier saat kas terbatas agar risiko operasional minimal.

## Prinsip Prioritas

1. Jatuh tempo terdekat dibayar lebih dulu.
2. Supplier kritikal (barang fast-moving) diprioritaskan.
3. Hutang dengan denda/bunga lebih tinggi diprioritaskan.
4. Hindari putus pasok SKU prioritas A.

## Skor Prioritas (Sederhana)

Gunakan skor total untuk setiap hutang:

- Kedekatan jatuh tempo:
  - <= 3 hari: 5
  - 4–7 hari: 4
  - 8–14 hari: 3
  - > 14 hari: 1
- Dampak pasok:
  - Supplier kritikal: 5
  - Supplier penting: 3
  - Supplier non-kritikal: 1
- Denda keterlambatan:
  - Tinggi: 3
  - Sedang: 2
  - Rendah/tidak ada: 1

Total skor = jatuh tempo + dampak pasok + denda.
Bayar dari skor tertinggi ke terendah.

## Rule Operasional

1. Siapkan daftar hutang jatuh tempo mingguan.
2. Simulasikan kas tersedia vs total kewajiban.
3. Jalankan pembayaran parsial jika kas tidak cukup total.
4. Komunikasikan rencana bayar ke supplier prioritas rendah.

## Ambang Eskalasi

- Jika hutang kritikal tidak dapat dibayar: eskalasi owner di hari yang sama.
- Jika rasio kas/total hutang jatuh tempo < 0,7: aktifkan mode penghematan pembelian.

## Integrasi Keputusan

- Terkait SOP utama: `docs/sop-keuangan-operasional-toko-kecil.md`
- Terkait kebijakan stok: `docs/kebijakan-stok-minimum-dan-reorder-point.md`
