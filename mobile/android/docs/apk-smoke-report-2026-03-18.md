# APK Smoke Report - 2026-03-18

## Ringkasan

Status: PASS (otomatis), MANUAL CHECKLIST SIAP JALAN

Lingkup verifikasi otomatis yang dijalankan:
1. Build APK debug
2. Focused unit tests (POS, sync, app)
3. Full debug unit tests lintas modul Android

## Hasil Eksekusi Otomatis

1. Build
- Command: gradlew :app:assembleDebug
- Result: PASS

2. Focused tests
- Command: gradlew :feature-pos:testDebugUnitTest :core-sync:testDebugUnitTest :app:testDebugUnitTest
- Result: PASS

3. Full tests
- Command: gradlew testDebugUnitTest
- Result: PASS

## Checklist Smoke Manual (Lapangan)

1. Login dan status awal
- Buka aplikasi dan login kasir
- Cek indikator sync
- Expected: aplikasi terbuka normal, status sync tampil

2. Guard shift sebelum transaksi
- Coba masuk POS saat shift belum dibuka
- Expected: akses POS ditolak dengan pesan jelas

3. Buka shift
- Dari Home tekan Buka Shift
- Expected: status shift aktif muncul

4. Mulai transaksi POS
- Tambah produk ke cart
- Masuk pembayaran
- Konfirmasi pembayaran
- Expected: transaksi sukses, receipt lokal terbentuk

5. Validasi blokir checkout tanpa shift
- Tutup shift lalu coba checkout ulang
- Expected: checkout diblokir sampai shift dibuka lagi

6. Tambah produk mode default
- Isi nama dan harga jual valid
- Expected: tersimpan lokal, masuk daftar produk

7. Tambah produk mode add-on
- Simpan tanpa add-on
- Expected: ditolak validasi
- Tambah minimal 1 add-on lalu simpan
- Expected: diterima

8. Tambah produk mode varian
- Simpan tanpa varian valid
- Expected: ditolak validasi
- Tambah varian valid lalu simpan
- Expected: diterima

9. Tambah produk mode resep
- Simpan tanpa bahan valid
- Expected: ditolak validasi
- Tambah minimal 1 bahan valid lalu simpan
- Expected: diterima

10. Cek laporan
- Buka tab laporan
- Expected: tampil total penjualan, transaksi, laba kotor estimasi, laba bersih estimasi, pending dan gagal sync

11. Tutup shift akhir hari
- Tekan Tutup Shift
- Expected: shift nonaktif, POS kembali terkunci sampai buka shift baru

## Catatan

1. Peringatan compileSdk lama sudah disuppress di gradle.properties agar output build lebih stabil.
2. Exit code 1 pada terminal lama berasal dari sesi batch prompt yang terblokir, bukan dari kegagalan kode terbaru.
