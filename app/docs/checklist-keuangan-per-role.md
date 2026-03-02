# Checklist Keuangan per Role

Tanggal: 2026-02-28  
Tujuan: Membagi tanggung jawab kontrol keuangan berdasarkan role operasional.

## 1) Owner

### Harian
- [ ] Memastikan saldo kas/bank aman untuk operasional hari ini.
- [ ] Menyetujui transaksi khusus bernilai besar (sesuai kebijakan internal).
- [ ] Meninjau ringkas penjualan dan kas akhir hari.

### Mingguan
- [ ] Meninjau arus kas masuk/keluar mingguan.
- [ ] Meninjau umur hutang supplier dan prioritas pembayaran.
- [ ] Menentukan batas pembelian minggu berikutnya berdasarkan cashflow.

### Bulanan
- [ ] Menyetujui penutupan buku bulanan (cut-off).
- [ ] Meninjau laporan laba rugi, arus kas, dan persediaan.
- [ ] Memutuskan tindakan: tambah modal, efisiensi biaya, atau koreksi strategi stok.

## 2) Admin

### Harian
- [ ] Memastikan master data (produk, harga, supplier) valid.
- [ ] Memastikan pembelian tunai/tempo dicatat lengkap.
- [ ] Memastikan transaksi void/refund memiliki alasan dan approval.
- [ ] Memastikan setiap adjustment stok memiliki catatan audit.

### Mingguan
- [ ] Rekonsiliasi data pembelian, stok, dan laporan persediaan.
- [ ] Meninjau produk slow-moving dan usulan reorder.
- [ ] Menyiapkan ringkasan masalah operasional untuk owner.

### Bulanan
- [ ] Menyusun rekap hutang supplier jatuh tempo.
- [ ] Menyiapkan data pendukung review laba rugi dan arus kas.
- [ ] Mengarsipkan dokumen transaksi utama (pembelian, refund, adjustment).

## 3) Kasir

### Harian (Buka Shift)
- [ ] Buka shift sesuai prosedur.
- [ ] Verifikasi saldo awal kas kecil.
- [ ] Pastikan perangkat kasir siap (printer, scanner, jaringan).

### Harian (Saat Operasional)
- [ ] Semua transaksi diproses melalui POS.
- [ ] Metode pembayaran dipilih sesuai transaksi aktual.
- [ ] Transaksi bermasalah tidak diselesaikan di luar sistem.
- [ ] Untuk void/refund, ikuti alur approval yang berlaku.

### Harian (Tutup Shift)
- [ ] Menutup shift sesuai jadwal.
- [ ] Rekonsiliasi kas fisik vs kas sistem.
- [ ] Mencatat selisih kas dan alasan jika ada.
- [ ] Menyerahkan rekap shift ke admin/owner.

## 4) Kontrol Wajib Lintas Role

- [ ] Tidak boleh ada kas negatif.
- [ ] Pembelian tempo wajib memiliki tanggal jatuh tempo.
- [ ] Semua perubahan stok wajib punya jejak alasan.
- [ ] Tidak boleh ada transaksi off-system untuk penjualan.
- [ ] Selisih kas wajib ditutup dengan berita acara internal.

## 5) SLA Penanganan Temuan

- [ ] Selisih kas harian: investigasi maksimal H+1.
- [ ] Selisih stok material: investigasi maksimal H+2.
- [ ] Hutang jatuh tempo kritikal: eskalasi ke owner di hari yang sama.
- [ ] Bug data/transaksi: catat tiket dan mitigasi operasional sementara.

## 6) Referensi Dokumen

- SOP utama: `docs/sop-keuangan-operasional-toko-kecil.md`
- Checklist UI per menu: `docs/checklist-ui-keuangan-per-menu.md`
- QA checklist UI: `docs/ui-manual-qa-checklist-2026-02-27.md`
