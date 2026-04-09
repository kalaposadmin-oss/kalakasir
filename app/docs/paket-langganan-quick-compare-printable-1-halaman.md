# Paket Langganan Kalakasir
## Quick Compare - Printable 1 Halaman

Tanggal: 2026-03-06
Acuan fitur: `app/config/feature-gating.json`

---

## 1. Ringkasan Paket

- `S (Starter)`: tahu kondisi usaha.
- `M (Medium)`: mulai mengelola usaha.
- `P (Pro)`: kontrol usaha secara profesional.

---

## 2. Tabel Perbandingan Inti

| Fitur Utama | S | M | P |
|---|---|---|---|
| POS checkout, refund/void, hold bill, split payment sederhana | Ya | Ya | Ya |
| Buka/tutup shift + riwayat shift | Ya | Ya | Ya |
| Produk, kategori, harga jual/modal, stok minimum | Ya | Ya | Ya |
| Stok masuk/keluar, adjustment sederhana, notifikasi stok menipis | Ya | Ya | Ya |
| Supplier basic + histori pembelian sederhana | Ya | Ya | Ya |
| Pembelian basic + update stok otomatis | Ya | Ya | Ya |
| Laporan dasar + laba rugi sederhana | Ya | Ya | Ya |
| Pembelian lengkap (PO, receiving, retur) | Tidak | Ya | Ya |
| Pelanggan, multi staff, multi cabang basic | Tidak | Ya | Ya |
| Laporan bisnis lengkap + pembayaran | Tidak | Ya | Ya |
| Export laporan CSV/PDF | Tidak | Ya | Ya |
| Akuntansi lengkap (L/R, neraca, arus kas, COA) | Tidak | Tidak | Ya |
| Lock period | Tidak | Tidak | Ya |
| Inventory advanced (stock card, FIFO, valuation) | Tidak | Tidak | Ya |
| Governance, observability, integrasi/API | Tidak | Tidak | Ya |
| Multi cabang advanced | Tidak | Tidak | Ya |

---

## 3. Limit Contoh

| Paket | Cabang | Staff | Produk |
|---|---|---|---|
| Starter | 1 | 2 | 1000 |
| Medium | 3 | 10 | 5000 |
| Pro | Unlimited | Unlimited | Unlimited |

---

## 4. Rekomendasi Cepat

- `Starter`: cocok untuk toko baru/satu outlet, fokus transaksi kasir harian.
- `Medium`: cocok untuk bisnis berkembang yang butuh pembelian, stok, dan tim lebih rapi.
- `Pro`: cocok untuk bisnis yang butuh kontrol profesional, audit, dan integrasi.

---

## 5. FAQ Singkat

- Kenapa fitur terlihat tapi tidak aktif?
  - Fitur dikunci sesuai paket langganan (visible but locked).

- Cek paket aktif di mana?
  - `Pengaturan > Langganan` (`/settings/subscription`).

- Arti kode paket?
  - `S = Starter`, `M = Medium`, `P = Pro`.

---

## 6. Script 30 Detik (Sales/CS)

"Jika fokus utama Bapak/Ibu adalah transaksi kasir harian, Starter sudah cukup. Jika sudah butuh pembelian, supplier, dan laporan operasional, pilih Medium. Jika butuh multi-cabang, audit, integrasi, serta kontrol keuangan lebih mendalam, pilih Pro."

---

Dokumen detail lengkap:
- `app/docs/panduan-paket-langganan-dan-perbedaan.md`
- `app/docs/paket-langganan-quick-compare-1-halaman.md`

Panduan cepat ekspor PDF:
- Buka `app/docs/paket-langganan-quick-compare-printable-a4.html` di browser.
- Pilih `Print` -> `Save as PDF` -> `Paper: A4`.
