# Monitoring COGS - Panduan Cepat Ops Harian

## Tujuan

Memastikan tidak ada masalah finansial COGS yang lolos tanpa terdeteksi.

## Frekuensi Cek

- Setiap 30-60 menit saat jam operasional
- 1x sebelum tutup hari (EOD)

## Langkah Cek Cepat

1. Buka Dashboard Monitoring.
2. Cek `Masalah Kritis Terbuka`.
3. Cek `Tenant Terdampak`.
4. Jika ada masalah, masuk ke `Daftar Insiden`.
5. Filter `Status = Terbuka`.
6. Urutkan berdasarkan:
   - Tingkat paling tinggi lebih dulu
   - Jumlah kejadian terbanyak

## Prioritas Tindakan

### Kritis

- Langsung cek dan tangani
- Jangan diabaikan
- Eskalasi jika perlu

### Tinggi

- Tangani jika berulang atau berdampak ke banyak tenant
- Monitor ketat sampai ada keputusan jelas

### Sedang dan Rendah

- Review berkala
- Tidak mendesak kecuali meningkat atau berulang

## Aksi yang Bisa Dilakukan

### Tandai Selesai

Lakukan jika:

- masalah sudah diperbaiki;
- data sudah valid atau sudah diverifikasi aman;
- tidak ada kejadian baru yang langsung muncul kembali.

### Abaikan

Lakukan jika:

- bukan masalah nyata;
- sudah dipahami konteksnya;
- tidak berdampak ke angka atau operasional.

Wajib isi alasan yang jelas.

### Eskalasi

Lakukan jika:

- memengaruhi angka keuangan;
- tidak bisa diperbaiki oleh ops;
- terjadi berulang;
- tenant terdampak bertambah;
- ada insiden tingkat kritis.

## Checklist Harian

### Pagi / Awal Shift

- [ ] Dashboard bersih dari insiden kritis
- [ ] Tidak ada tenant besar bermasalah

### Saat Operasional

- [ ] Tidak ada lonjakan insiden
- [ ] Tidak ada insiden berulang tanpa action

### Penutup Hari / EOD

- [ ] Tidak ada insiden kritis terbuka
- [ ] Semua insiden punya status jelas
- [ ] Semua `Abaikan` punya alasan

## Prinsip Utama

> Lebih baik sistem error daripada salah hitung tanpa ketahuan.

## Status Sistem

- Aman: tidak ada insiden kritis atau tinggi
- Waspada: ada insiden tinggi
- Bermasalah: ada insiden kritis

## Catatan

Dashboard ini dipakai untuk deteksi cepat, bukan untuk analisis mendalam.

Jika ragu, eskalasi. Jangan asumsi.