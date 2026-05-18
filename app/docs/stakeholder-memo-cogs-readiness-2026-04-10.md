# Memo Stakeholder — COGS System Readiness

Tanggal: 2026-04-10

## Inti Pesan

Sistem perhitungan biaya penjualan telah diperkuat secara signifikan dan saat ini siap secara engineering. Namun, sistem belum dapat dinyatakan tervalidasi penuh untuk go-live finansial sampai diuji terhadap data produksi nyata.

## Apa yang Sudah Benar

- Perhitungan COGS sekarang berbasis agregasi komponen yang lebih terstruktur dan lebih dapat ditelusuri.
- Sistem tidak lagi membiarkan ketidaksesuaian biaya lewat tanpa alarm.
- Jika terjadi mismatch data, sistem akan langsung gagal keras, bukan menampilkan angka yang tampak benar padahal salah.
- Alur penting seperti penjualan normal, refund, pembatalan, dan produk berbasis resep sudah diuji dalam simulasi lokal dan hasilnya konsisten.

## Dampak ke Bisnis

- Risiko salah hitung yang tidak terlihat sudah ditekan secara signifikan.
- Masalah akan lebih cepat terdeteksi karena jalur laporan sekarang menolak data biaya yang tidak valid.
- Struktur laporan keuangan lebih aman secara teknis dibanding sebelumnya.

## Apa yang Belum Bisa Dipastikan

- Belum ada pembuktian terhadap data tenant live atau data produksi nyata.
- Belum ada validasi terhadap kasus historis kompleks yang hanya muncul di lapangan.
- Belum ada bukti pada volume data besar atau kondisi concurrency tinggi.

## Status Kesiapan

- Engineering Ready: ya, sekitar 90%.
- Financially Validated terhadap data produksi: belum.
- Safe usage scope saat ini: internal testing, UAT, atau pilot kecil yang terkontrol.

## Rekomendasi Keputusan

- Lanjut ke pilot atau staged rollout.
- Jangan langsung full go-live finansial.
- Final sign-off penuh diberikan hanya setelah sistem divalidasi pada database rollout atau production yang benar.

## Kesimpulan

Secara teknis, sistem sudah jauh lebih kuat dan lebih aman dibanding kondisi sebelumnya. Secara finansial, keputusan go-live penuh masih harus menunggu validasi terhadap data nyata.