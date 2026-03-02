# Roadmap Otomasi Kontrol Keuangan di Aplikasi

Tanggal: 2026-02-28  
Tujuan: Menerjemahkan kebijakan keuangan operasional menjadi fitur aplikasi bertahap.

## Fase 1 (Quick Wins - 1 Sprint)

1. Validasi pembelian tunai vs saldo kas
   - Blok submit jika kas tidak cukup.
   - Tampilkan pesan aksi: kurangi qty / ubah ke tempo.
2. Kode alasan standar
   - Dropdown kode alasan pada void/refund/adjustment.
   - Wajib catatan untuk kode "Lainnya".
3. Closing temuan harian
   - Ringkasan otomatis saat tutup shift:
     - selisih kas,
     - transaksi pending,
     - adjustment tanpa lampiran.

## Fase 2 (Kontrol Risiko - 1-2 Sprint)

1. Otorisasi nominal berjenjang
   - Enforce limit berdasarkan role untuk void/refund/adjustment.
2. Reminder hutang jatuh tempo
   - Notifikasi H-3 dan H-1 untuk hutang supplier.
3. Alert stok kritis berbasis ROP
   - Flag produk ketika stok <= reorder point.

## Fase 3 (Visibilitas Owner - 1 Sprint)

1. Dashboard kontrol harian (5 KPI)
   - Penjualan, HPP, Kas Akhir, Hutang H-7, Selisih Kas Shift.
2. Export rekap
   - Export harian/mingguan ke PDF/CSV.
3. Snapshot closing
   - Simpan snapshot KPI saat end-of-day.

## Definisi Sukses

- Kas minus karena pembelian tunai turun ke 0 kasus.
- 100% void/refund/adjustment punya kode alasan valid.
- 100% transaksi di atas limit memiliki jejak approval.
- Review owner harian dapat selesai <= 10 menit.

## Dependensi

- Data saldo kas harus akurat per shift/location.
- Role dan permission harus konsisten (`owner/admin/cashier`).
- Scheduler aktif untuk reminder otomatis.

## Referensi Kebijakan

- SOP utama: `docs/sop-keuangan-operasional-toko-kecil.md`
- Dashboard spec: `docs/spesifikasi-dashboard-kontrol-harian-keuangan.md`
- Otorisasi: `docs/kebijakan-batas-otorisasi-transaksi.md`
- SLA: `docs/sla-insiden-operasional-keuangan.md`
- Kode alasan: `docs/kode-alasan-standar-void-refund-adjustment.md`
- Stok minimum/ROP: `docs/kebijakan-stok-minimum-dan-reorder-point.md`
- Prioritas hutang: `docs/aturan-prioritas-pembayaran-hutang-supplier.md`
