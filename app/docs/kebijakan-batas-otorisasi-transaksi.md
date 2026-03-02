# Kebijakan Batas Otorisasi Transaksi

Tanggal: 2026-02-28

## Tujuan
Menetapkan batas nominal transaksi yang membutuhkan approval berjenjang untuk menjaga kontrol risiko.

## Matriks Otorisasi (Default)

| Jenis Aksi | Kasir | Admin | Owner |
|---|---|---|---|
| Void transaksi <= Rp250.000 | Ya | Ya | Ya |
| Void transaksi > Rp250.000 s/d Rp1.500.000 | Tidak | Ya | Ya |
| Void transaksi > Rp1.500.000 | Tidak | Tidak | Ya |
| Refund <= Rp250.000 | Ya | Ya | Ya |
| Refund > Rp250.000 s/d Rp1.500.000 | Tidak | Ya | Ya |
| Refund > Rp1.500.000 | Tidak | Tidak | Ya |
| Adjustment stok nilai <= Rp500.000 | Tidak | Ya | Ya |
| Adjustment stok nilai > Rp500.000 | Tidak | Tidak | Ya |

## Ketentuan Wajib

1. Alasan void/refund/adjustment wajib diisi.
2. Approval harus meninggalkan jejak: siapa, kapan, nominal, alasan.
3. Tidak boleh override approval di luar sistem.
4. Jika sistem approval error, transaksi status pending sampai disetujui.

## Pengecualian Darurat

- Hanya owner yang dapat mengeksekusi override darurat.
- Override darurat wajib memiliki catatan insiden.
- Harus direview maksimal H+1 oleh owner.

## Review Berkala

- Review limit minimal tiap 3 bulan.
- Sesuaikan limit berdasar volume transaksi dan risk profile cabang.
