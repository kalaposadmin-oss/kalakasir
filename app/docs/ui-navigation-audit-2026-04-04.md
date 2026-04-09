# UI Navigation Audit - 2026-04-04

## Goal

Menyeragamkan navigasi web POS dan backoffice agar user tidak kehilangan akses menu ketika berpindah antar layar kerja.

## Masalah Yang Ditemukan

- Beberapa halaman memakai tombol menu standar dari `page-header`, beberapa halaman lain memakai tombol menu custom sendiri.
- Drawer menu terlalu minim dan belum menampilkan shortcut penting seperti tambah produk, akun kas/bank, dan pengaturan inti.
- Beberapa layar flow utama mematikan tombol menu sehingga user terasa masuk ke dead end.
- State aktif menu belum cukup representatif untuk halaman detail, edit, dan subflow.
- Perpindahan POS ke backoffice belum cukup jelas untuk user baru.

## Perbaikan Yang Diterapkan

- Drawer menu POS dan backoffice dirapikan menjadi beberapa section dengan deskripsi tiap item.
- Shortcut penting ditambahkan langsung ke drawer.
- Header halaman kerja utama diseragamkan untuk kembali memakai tombol menu standar `page-header`.
- Beberapa layar yang sebelumnya kehilangan tombol menu sekarang kembali menampilkan tombol menu.
- Drawer menampilkan nama user, role, dan portal aktif.
- State aktif menu sekarang mengikuti path halaman aktif, termasuk halaman turunan.

## Aturan UI Yang Dipakai

- Halaman kerja utama tetap menampilkan tombol `back` bila perlu, tetapi tetap boleh membuka menu drawer.
- `page-header` adalah sumber utama tombol menu. Jangan membuat tombol menu custom kecuali ada alasan kuat.
- Menu POS fokus ke transaksi harian dan shortcut cepat.
- Menu backoffice fokus ke master data, laporan, dan pengaturan sistem.
- Shortcut penting yang wajib mudah ditemukan:
  - Tambah Produk
  - Daftar Produk
  - Kategori
  - Stok & Inventaris
  - Toko & Cabang
  - Pengguna
  - Akun Kas & Bank
  - Pajak & Promo
  - Preferensi / Pengaturan Toko

## Checklist Lanjutan

- Audit ulang semua layar receipt/final state apakah tetap perlu menu atau sebaiknya tetap fokus ke satu aksi selesai.
- Audit label menu agar istilah bisnis makin konsisten untuk user non-teknis.
- Audit mobile spacing drawer dan header di seluruh halaman dengan konten panjang.
- Tambah regression test untuk lebih banyak layar create/edit dan halaman backoffice settings.
