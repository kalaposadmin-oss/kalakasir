# Kode Alasan Standar (Void, Refund, Adjustment)

Tanggal: 2026-02-28

## Tujuan
Menstandarkan alasan transaksi korektif agar laporan konsisten dan mudah diaudit.

## A. Void

| Kode | Alasan | Keterangan Wajib |
|---|---|---|
| V01 | Salah input item | Nomor transaksi awal |
| V02 | Salah harga/diskon | Detail harga seharusnya |
| V03 | Duplicate input | Referensi transaksi duplikat |
| V04 | Pelanggan batal sebelum bayar | Catatan singkat |
| V99 | Lainnya | Penjelasan minimal 20 karakter |

## B. Refund

| Kode | Alasan | Keterangan Wajib |
|---|---|---|
| R01 | Produk rusak/cacat | SKU + foto/bukti (jika ada) |
| R02 | Produk tidak sesuai | SKU + penjelasan |
| R03 | Salah qty kirim | Qty seharusnya |
| R04 | Kesalahan kasir | Nomor transaksi awal |
| R99 | Lainnya | Penjelasan minimal 20 karakter |

## C. Stock Adjustment

| Kode | Alasan | Keterangan Wajib |
|---|---|---|
| A01 | Hasil stock opname | Nomor sesi stock opname |
| A02 | Barang rusak/hilang | Detail penyebab |
| A03 | Koreksi sistem pasca insiden | Nomor tiket insiden |
| A04 | Barang expired | Tanggal expired |
| A99 | Lainnya | Penjelasan minimal 20 karakter |

## Rule Input

1. Field kode alasan wajib dipilih, tidak boleh kosong.
2. Field catatan wajib diisi untuk kode `V99`, `R99`, `A99`.
3. Nominal/qty koreksi di atas ambang harus mengikuti kebijakan otorisasi.

## Integrasi Dokumen

- Kebijakan limit approval: `docs/kebijakan-batas-otorisasi-transaksi.md`
- SLA insiden: `docs/sla-insiden-operasional-keuangan.md`
