# UMKM Offline Android Blueprint (Execution Starter)

Proyek ini adalah fondasi aplikasi Android offline-first untuk operasional UMKM.

## Modul yang sudah di-scaffold
- `app`
- `core-common`
- `core-database`
- `core-network`
- `core-sync`
- `feature-pos`
- `feature-auth`
- `feature-home`
- `feature-sync-status`

## Fokus fondasi
- Clean Architecture + MVVM + Repository Pattern
- Room sebagai local-first data source
- Outbox sync dengan WorkManager
- API client melalui Retrofit + OkHttp
- Sync status eksplisit (`DRAFT`, `PENDING_SYNC`, `SYNCED`, `FAILED`, `CONFLICT`)
- UI MVP sederhana: login, home, POS cart, payment, sync status
- Sample offline flow: finalisasi sale lokal -> enqueue outbox -> trigger sync worker

## Dokumen penting
- `docs/offline-first-architecture-blueprint.md`
- `docs/room-schema-and-sync-design.md`
- `docs/phase-implementation-plan.md`
- `docs/backend-api-contract-mvp.md`

## Catatan
- Folder ini berisi fondasi arsitektur dan skeleton implementasi.
- Integrasi penuh build Android membutuhkan Android SDK lokal.
- Jika Gradle wrapper belum tersedia, generate dari Android Studio atau Gradle lokal lalu jalankan `:app:assembleDebug`.

## Verifikasi Cepat (Wajib Sebelum Commit)

### 1) Menjalankan unit test
Jalankan dari folder `mobile-android-umkm-offline`:

```bash
./gradlew :app:testDebugUnitTest :core-sync:testDebugUnitTest :feature-pos:testDebugUnitTest
```

Di Windows PowerShell bisa juga:

```powershell
.\gradlew.bat :app:testDebugUnitTest :core-sync:testDebugUnitTest :feature-pos:testDebugUnitTest
```

### 2) Menjalankan assembleDebug
Tetap dari folder `mobile-android-umkm-offline`:

```bash
./gradlew :app:assembleDebug
```

Di Windows PowerShell:

```powershell
.\gradlew.bat :app:assembleDebug
```

### 3) Cara membaca hasil sukses/gagal
- Sukses test/build ditandai `BUILD SUCCESSFUL`.
- Gagal ditandai `BUILD FAILED` dan biasanya ada task gagal seperti `:app:testDebugUnitTest` atau `:app:compileDebugKotlin`.
- Untuk test gagal, lihat bagian `FAILED` yang menampilkan nama class dan nama test.
- APK debug hasil assemble ada di `app/build/outputs/apk/debug/` jika build berhasil.

### 4) Urutan validasi minimum sebelum commit
1. Jalankan unit test minimum (`:app:testDebugUnitTest`, `:core-sync:testDebugUnitTest`, `:feature-pos:testDebugUnitTest`).
2. Pastikan semua test lulus tanpa flaky rerun.
3. Jalankan `:app:assembleDebug` untuk verifikasi integrasi compile.
4. Pastikan tidak ada perubahan di luar scope task.
5. Commit kecil dan terfokus pada stabilitas fondasi MVP.

## Skenario Demo HP (MVP Operasional)

Data demo otomatis tersedia saat database lokal masih kosong:
- Kategori: `Minuman`, `Makanan`, `Snack`
- Produk: item realistis warung/kafe (kopi, teh, mie, nasi, snack)
- Pelanggan: `Pelanggan Umum`, `Rina Anggota`, `PT Sejahtera Abadi`
- Metode bayar: `Tunai`, `QRIS`, `Kartu Debit`, `Transfer Bank`

Urutan review cepat di HP Android:
1. Buka tab `POS` lalu pilih pelanggan.
2. Filter kategori, cari produk, lalu tambahkan beberapa item berbeda ke cart.
3. Masuk ke `Pembayaran`, pilih metode bayar, lalu finalisasi transaksi lokal.
4. Verifikasi card `Receipt Transaksi Terakhir` menampilkan:
	- nomor transaksi
	- daftar item
	- total
	- metode bayar
	- status sync (`PENDING_SYNC`)
5. Buka tab `Sync` dan pastikan status sinkronisasi sesuai kondisi jaringan/worker.
