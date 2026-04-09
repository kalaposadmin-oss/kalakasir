# Screen mapping: UI referensi -> sistem

Tujuan: memetakan file desain ke layar/route sistem yang sudah ada, plus catatan gap.

## Mapping per file
| File | Nama layar | Modul sistem (target) | Feature gate | Status | Catatan |
| --- | --- | --- | --- | --- | --- |
| [app/docs/UI BARU/code(1).html](../UI%20BARU/code(1).html) | PIN kasir | Pos auth / shift open | pos.open_shift | Gap | Sistem shift + PIN belum terpasang jelas. Desain dipakai sebagai referensi jika fitur dibuat. |
| [app/docs/UI BARU/code(2).html](../UI%20BARU/code(2).html) | Dashboard kasir | Dashboard utama | dash.overview | Mostly match | Konten kartu KPI perlu disambungkan ke data sistem. |
| [app/docs/UI BARU/code.html](../UI%20BARU/code.html) | Produk kosong | Produk list | product.list | Match | Gunakan empty state ini saat data kosong. |
| [app/docs/UI BARU/code(5).html](../UI%20BARU/code(5).html) | Karyawan | Pengguna / karyawan | settings.users | Gap | Perlu cek modul user di sistem, atau sesuaikan ke menu yang ada. |
| [app/docs/UI BARU/code(4).html](../UI%20BARU/code(4).html) | Metode bayar | Pengaturan metode bayar | settings.payment_methods | Gap | Jika modul belum ada, UI ini hanya referensi. |
| [app/docs/UI BARU/code(13).html](../UI%20BARU/code(13).html) | Pembayaran | POS checkout | pos.checkout | Match | Sesuaikan metode bayar ke konfigurasi aktual. |
| [app/docs/UI BARU/code(12).html](../UI%20BARU/code(12).html) | Riwayat transaksi | Riwayat transaksi | history.transactions | Match | Detail transaksi sudah ada di sistem. |
| [app/docs/UI BARU/code(9).html](../UI%20BARU/code(9).html) | Detail transaksi | Detail transaksi | history.transaction_detail | Match | Pastikan status refund/void sesuai model data. |
| [app/docs/UI BARU/code(11).html](../UI%20BARU/code(11).html) | Filter transaksi | Riwayat transaksi (filter) | history.transactions | Partial | UI filter ada, logic filter disesuaikan ke query sistem. |
| [app/docs/UI BARU/code(10).html](../UI%20BARU/code(10).html) | Export laporan | Laporan / export | reports.export_csv, reports.download_pdf | Partial | UI menampilkan XLSX. Sistem sekarang CSV/PDF. XLSX perlu ditambah atau hilangkan. |
| [app/docs/UI BARU/code(8).html](../UI%20BARU/code(8).html) | Riwayat shift | Riwayat shift | history.shift | Gap | Fitur shift belum jelas di sistem. |
| [app/docs/UI BARU/code(6).html](../UI%20BARU/code(6).html) | Pembelian stok | Pembelian / PO | purchase.create | Match | Cocok dengan alur prefill PO yang sudah dibuat. |
| [app/docs/UI BARU/code(7).html](../UI%20BARU/code(7).html) | Tambah supplier | Supplier create | supplier.create | Match | Cocok dengan modul supplier. |
| [app/docs/UI BARU/code(3).html](../UI%20BARU/code(3).html) | Langganan / plan | Pengaturan langganan | settings.subscription | Partial | Label plan disesuaikan ke S/M/P. |

## Gap utama yang perlu keputusan
1. Shift + PIN kasir (buat modul baru atau ganti UI).
2. Metode pembayaran (ada modul atau hanya UI).
3. Export XLSX (tambah fitur atau hilangkan opsi).
4. Nama plan (S/M/P vs label marketing di UI).

## Rekomendasi alur kerja
1. Tetapkan label plan S/M/P di semua layar plan.
2. Lock scope untuk fase 1: POS, Produk, Riwayat transaksi, Pembelian, Supplier.
3. Sisanya jadi fase 2: Shift, Metode bayar, Export XLSX, Langganan detail.
