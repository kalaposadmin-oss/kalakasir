# APK Operasional Shift dan POS

Dokumen ini untuk tim operasional lapangan agar alur kasir harian aman dipakai pada mode offline-first.

## 1. Persiapan sebelum toko buka

1. Pastikan login kasir berhasil.
2. Cek status sinkronisasi di aplikasi:
- Pending boleh ada, tetapi gagal harus dipantau.
- Jika ada gagal, catat jenis error untuk ditindaklanjuti.
3. Pastikan produk inti dan harga tampil benar.

## 2. Buka shift

1. Dari Home, tekan Buka Shift.
2. Verifikasi label shift berubah menjadi aktif.
3. Coba masuk POS.
- Jika shift belum aktif, aplikasi akan menolak akses POS.

## 3. Proses transaksi POS

1. Tambah item ke keranjang.
2. Masuk langkah pembayaran.
3. Selesaikan pembayaran.
4. Verifikasi receipt lokal muncul.
5. Verifikasi transaksi tersimpan sebagai pending sync bila offline.

## 4. Aturan guard yang harus selalu berlaku

1. POS tidak boleh bisa diakses jika shift belum dibuka.
2. Checkout tidak boleh lanjut jika shift tidak aktif.
3. Double tap pada aksi simpan tidak boleh membuat duplikasi outbox dengan kunci idempotensi yang sama.

## 5. Tambah produk dari APK

1. Isi Nama Produk dan Harga Jual wajib.
2. Untuk mode Add-On, wajib minimal satu add-on.
3. Untuk mode Varian, wajib minimal satu varian valid (nama dan harga > 0).
4. Untuk mode Resep, wajib minimal satu bahan valid.
5. Setelah simpan, data produk harus tampil di daftar lokal.

## 6. Tutup shift

1. Dari Home tekan Tutup Shift.
2. Verifikasi shift aktif hilang.
3. Coba masuk POS lagi.
- Aplikasi harus menolak hingga shift berikutnya dibuka.

## 7. Verifikasi laporan

1. Buka menu laporan.
2. Verifikasi metrik tampil:
- Total Penjualan
- Transaksi
- Laba Kotor Estimasi
- Laba Bersih Estimasi
- Status sinkronisasi pending dan gagal

## 8. Checklist handover akhir hari

1. Tidak ada transaksi yang gagal dicatat lokal.
2. Jumlah pending sinkronisasi diketahui operator.
3. Shift sudah ditutup jika operasional selesai.
4. Catat kendala untuk eskalasi teknis besok.
