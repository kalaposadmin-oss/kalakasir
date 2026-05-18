# SOP Operasional Monitoring COGS

Tanggal: 2026-04-11

## 1. Tujuan

Dokumen ini menjadi panduan operasional resmi untuk memakai dashboard monitoring COGS setelah live.

Tujuan dashboard adalah membantu tim mendeteksi lebih cepat masalah biaya penjualan, ketidaksesuaian laporan, kegagalan validasi, dan gangguan proses ringkasan harian sebelum berdampak lebih besar ke operasional dan review keuangan.

Dashboard ini penting karena:

- membantu tim melihat insiden yang paling mendesak dari satu tempat;
- memisahkan tenant terdampak dan tingkat insiden secara jelas;
- mempercepat keputusan apakah insiden harus ditandai selesai, diabaikan, atau dieskalasi;
- menjaga agar masalah finansial tidak dibiarkan terbuka tanpa tindak lanjut.

## 2. Ruang Lingkup

Dashboard ini memantau insiden berikut:

- missing aggregate
- header mismatch
- strict reader blocked
- validate failed
- rebuild failed
- daily summary refresh failed
- cross-report mismatch
- refund reversal mismatch
- slow report query

Semua insiden di atas dianggap bagian dari kontrol operasional finansial, terutama untuk menjaga konsistensi angka COGS, stabilitas laporan, dan kesehatan proses ringkasan tenant.

## 3. Peran dan Tanggung Jawab

### Super Admin

- memantau ringkasan lintas tenant pada dashboard utama;
- memutuskan prioritas penanganan untuk insiden tingkat kritis dan tinggi;
- menyetujui tindakan berisiko seperti bangun ulang atau tindakan lintas fungsi;
- memastikan tidak ada insiden kritis terbuka tanpa PIC dan target tindak lanjut.

### Ops Internal

- melakukan triase harian dari overview dan daftar insiden;
- memeriksa tenant terdampak, jumlah kejadian, dan tren kejadian terbaru;
- menjalankan tindakan operasional awal yang aman;
- menulis catatan singkat alasan saat tandai selesai atau abaikan;
- mengeskalasi ke engineer atau finance reviewer sesuai aturan eskalasi.

### Finance Reviewer

- menilai dampak bisnis dan dampak angka untuk insiden yang berhubungan dengan mismatch laporan, refund, dan validasi biaya;
- memastikan keputusan tandai selesai tidak dilakukan sebelum dampak angka dipahami;
- memberi keputusan apakah perlu review data transaksi, review laporan, atau eskalasi ke product owner.

### Engineer On-Call

- menangani insiden yang menunjukkan kegagalan proses sistem, query lambat, strict reader blocked, validasi gagal, atau bangun ulang gagal;
- memeriksa log, scheduler, job, dan jalur sistem yang gagal;
- menjalankan tindakan teknis yang memerlukan akses engineering;
- memberi konfirmasi kapan insiden aman untuk ditandai selesai.

## 4. Cara Membaca Dashboard

### KPI cards

- `Masalah Kritis Terbuka`: jumlah insiden tingkat kritis yang masih berstatus terbuka dan harus menjadi perhatian pertama.
- `Masalah Prioritas Tinggi`: jumlah insiden tingkat tinggi yang belum selesai dan berpotensi naik menjadi lebih serius bila dibiarkan.
- `Tenant Terdampak`: jumlah tenant unik yang sedang terkena insiden aktif.
- kartu lain seperti status validasi, bangun ulang, atau refresh harian menunjukkan apakah proses kontrol utama terakhir berjalan sehat atau gagal.

### Tingkat

- `kritis`: risiko finansial tinggi, tenant bisa tidak aman untuk dibaca, atau proses inti gagal.
- `tinggi`: risiko nyata ke laporan atau operasional, perlu ditindak pada hari yang sama.
- `sedang`: ada masalah yang perlu ditangani, tetapi biasanya masih ada workaround.
- `rendah`: perlu dipantau, tetapi tidak memerlukan tindakan darurat.

### Status insiden

- `terbuka`: insiden aktif dan masih perlu tindakan.
- `selesai`: insiden sudah ditangani, verifikasi minimum sudah dilakukan, dan tidak ada blocker tersisa.
- `diabaikan`: insiden sengaja tidak ditindaklanjuti lebih jauh karena alasan operasional yang sah dan sudah dicatat.

### Tenant impact

Bagian tenant terdampak membantu melihat tenant mana yang paling sering terkena insiden kritis atau prioritas tinggi. Fokus awal diarahkan ke tenant dengan kombinasi tingkat tinggi dan jumlah kejadian terbesar.

### Occurrence count

`jumlah kejadian` menunjukkan seberapa sering event yang sama muncul. Semakin tinggi angkanya, semakin besar peluang masalah belum stabil atau belum benar-benar selesai.

### Latest critical events

`insiden terbuka terbaru` atau event kritis terbaru dipakai untuk melihat insiden baru yang paling membutuhkan keputusan cepat. Insiden baru dengan tingkat kritis selalu diperiksa sebelum insiden sedang atau rendah yang lebih lama.

## 5. Alur Triase Harian

1. Buka halaman overview dashboard monitoring.
2. Cek `Masalah Kritis Terbuka`.
3. Cek `Tenant Terdampak` untuk melihat sebaran blast radius.
4. Masuk ke `Daftar Insiden` dengan filter status terbuka.
5. Urutkan penanganan berdasarkan tingkat, tenant terdampak, dan jumlah kejadian.
6. Buka detail insiden yang paling mendesak.
7. Baca ringkasan insiden, payload, dan riwayat kejadian.
8. Putuskan tindakan awal: tandai selesai, abaikan, atau eskalasi.
9. Catat alasan keputusan secara singkat dan operasional.
10. Pastikan semua insiden kritis dan tinggi sudah punya PIC sebelum akhir shift.

## 6. Playbook per Jenis Insiden

### sales_cost_missing_aggregate

Arti sederhananya:
Data agregat biaya penjualan yang dibutuhkan laporan tidak ditemukan.

Dampak bisnis:
Laporan COGS bisa tidak lengkap atau pembacaan biaya tenant menjadi tidak aman.

Langkah cek cepat:

- lihat tenant dan waktu kejadian;
- cek apakah masalah hanya satu tenant atau banyak tenant;
- cek apakah ada event validate failed atau rebuild failed yang berdekatan.

Tindakan pertama:

- tahan keputusan tandai selesai;
- minta engineer mengecek proses pembentukan agregat;
- bila ada prosedur operasional refresh atau validasi yang aman, jalankan sesuai approval.

Kapan harus escalate:

- langsung ke engineer on-call bila tingkat kritis atau tinggi;
- ke finance reviewer bila tenant sedang dalam periode review laporan;
- ke super admin bila lebih dari satu tenant terdampak.

### sales_cost_header_mismatch

Arti sederhananya:
Header ringkasan biaya tidak cocok dengan data yang seharusnya dibaca.

Dampak bisnis:
Angka biaya bisa berbeda antara sumber data dan ringkasan laporan.

Langkah cek cepat:

- cek tenant, rentang waktu, dan jumlah kejadian;
- lihat apakah mismatch muncul setelah perubahan data atau setelah proses bangun ulang;
- cocokkan apakah ada cross_report_mismatch yang aktif.

Tindakan pertama:

- tandai sebagai perlu review angka;
- libatkan finance reviewer untuk memastikan ada atau tidak ada dampak ke laporan;
- eskalasi ke engineer bila mismatch terus muncul.

Kapan harus escalate:

- bila memengaruhi angka laporan yang sedang dipakai;
- bila berulang setelah tindakan sebelumnya;
- bila terjadi bersamaan dengan validate failed.

### strict_reader_blocked

Arti sederhananya:
Sistem menolak pembacaan karena data biaya dianggap tidak aman.

Dampak bisnis:
Laporan tertentu bisa gagal dibuka atau tenant tidak boleh dibaca sampai masalah sumber diperbaiki.

Langkah cek cepat:

- cek tenant yang diblokir;
- cek event pemicu sebelumnya seperti missing aggregate, validate failed, atau rebuild failed;
- cek apakah blokir hanya satu tenant atau lintas tenant.

Tindakan pertama:

- jangan abaikan secara default;
- eskalasi segera ke engineer on-call;
- beri tahu ops dan finance reviewer bila tenant sedang dalam jam review laporan.

Kapan harus escalate:

- selalu ke engineer;
- ke super admin bila tenant prioritas tinggi terdampak;
- ke finance reviewer bila laporan sedang diperlukan untuk keputusan bisnis hari itu.

### sales_cost_validate_failed

Arti sederhananya:
Proses validasi biaya penjualan gagal menyelesaikan pemeriksaan.

Dampak bisnis:
Data belum bisa dianggap sehat, sehingga angka COGS perlu diperlakukan hati-hati.

Langkah cek cepat:

- cek waktu validasi gagal;
- cek payload atau ringkasan error utama;
- cek apakah ada tenant impact tambahan sesudah validasi gagal.

Tindakan pertama:

- tandai insiden untuk review engineer;
- jangan tandai selesai sebelum ada verifikasi ulang;
- jika tenant sedang review finansial, tahan penggunaan hasil sampai ada konfirmasi sehat.

Kapan harus escalate:

- bila validasi gagal pada tenant yang aktif dipakai;
- bila kegagalan berulang pada hari yang sama;
- bila terjadi setelah bangun ulang.

### sales_cost_rebuild_failed

Arti sederhananya:
Proses bangun ulang data biaya tidak berhasil selesai.

Dampak bisnis:
Upaya perbaikan belum tuntas dan data bisa tetap tidak konsisten.

Langkah cek cepat:

- cek tenant target;
- cek apakah bangun ulang dijalankan dalam window yang benar;
- cek apakah ada insiden turunan sesudahnya.

Tindakan pertama:

- hentikan asumsi bahwa masalah sudah pulih;
- minta engineer review log dan status eksekusi;
- validasi ulang blast radius tenant sebelum bangun ulang dijadwalkan lagi.

Kapan harus escalate:

- selalu ke engineer on-call;
- ke super admin bila bangun ulang menyangkut tenant prioritas atau lebih dari satu tenant;
- ke finance reviewer bila hasil laporan sebelumnya sudah sempat dipakai.

### daily_summary_refresh_failed

Arti sederhananya:
Refresh ringkasan harian gagal.

Dampak bisnis:
Dashboard atau ringkasan harian bisa tidak mutakhir, walau data sumber belum tentu rusak.

Langkah cek cepat:

- cek tenant dan tanggal ringkasan;
- cek apakah ada event lain yang lebih serius di tenant yang sama;
- cek apakah kegagalan hanya satu kali atau berulang.

Tindakan pertama:

- minta ops menilai apakah ini keterlambatan refresh atau masalah data;
- jalankan refresh ulang hanya bila prosedurnya sudah disetujui;
- eskalasi ke engineer bila berulang atau berdampak ke banyak tenant.

Kapan harus escalate:

- bila refresh gagal berulang;
- bila tenant berdampak lebih dari satu;
- bila user bisnis membutuhkan ringkasan harian saat itu juga.

### cross_report_mismatch

Arti sederhananya:
Angka antara dua laporan yang seharusnya selaras ternyata berbeda.

Dampak bisnis:
Risiko salah interpretasi angka dalam review finansial dan closing.

Langkah cek cepat:

- identifikasi laporan mana yang berbeda;
- cek tenant, periode, dan besar perbedaan;
- cek apakah ada header mismatch atau validate failed pada tenant yang sama.

Tindakan pertama:

- libatkan finance reviewer;
- tahan penggunaan laporan final bila mismatch material;
- minta engineer review jika mismatch berasal dari jalur sistem, bukan keterlambatan refresh biasa.

Kapan harus escalate:

- bila perbedaan berdampak ke keputusan bisnis;
- bila terjadi pada periode closing;
- bila mismatch tetap ada setelah refresh atau validasi ulang.

### refund_cost_reversal_mismatch

Arti sederhananya:
Pembalikan biaya untuk refund tidak cocok dengan yang seharusnya.

Dampak bisnis:
COGS refund, margin, dan audit transaksi refund bisa salah.

Langkah cek cepat:

- cek tenant, transaksi refund, dan periode kejadian;
- lihat apakah masalah tunggal atau berulang;
- cek apakah ada pola pada jenis transaksi tertentu.

Tindakan pertama:

- libatkan finance reviewer untuk menilai dampak angka;
- eskalasi ke engineer bila mismatch berasal dari logika reversal atau data turunan;
- jangan abaikan bila transaksi refund masih aktif terjadi.

Kapan harus escalate:

- bila nilai refund material;
- bila transaksi serupa muncul lebih dari sekali;
- bila memengaruhi laporan margin atau reconciliation refund.

### report_query_slow

Arti sederhananya:
Query laporan berjalan lambat di atas batas aman.

Dampak bisnis:
Dashboard dan laporan menjadi lambat, terutama pada tenant besar atau jam sibuk.

Langkah cek cepat:

- cek tenant dan jenis laporan;
- cek apakah ada timeout atau hanya lambat;
- cek apakah kejadian berulang pada jam operasional tertentu.

Tindakan pertama:

- pantau tren kejadian;
- eskalasi ke engineer bila mulai memengaruhi akses user atau menumpuk;
- ops tidak perlu tandai selesai sebelum performa kembali normal.

Kapan harus escalate:

- bila query lambat berulang pada tenant yang sama;
- bila mulai memicu kegagalan refresh atau pembacaan;
- bila user bisnis melaporkan keterlambatan akses nyata.

## 7. Aturan Resolve vs Ignore

### Kapan insiden boleh ditandai selesai

Insiden boleh `tandai selesai` bila semua kondisi berikut terpenuhi:

- penyebab langsung sudah diatasi atau mitigasi stabil sudah berjalan;
- tidak ada event baru sejenis yang langsung muncul kembali;
- dampak tenant sudah dipahami;
- untuk insiden angka, finance reviewer sudah setuju atau tidak ada dampak finansial material;
- untuk insiden sistem, engineer atau operator yang berwenang sudah memberi konfirmasi aman.

### Kapan boleh diabaikan

Insiden boleh `abaikan` hanya bila:

- diketahui sebagai noise operasional sementara yang tidak berdampak ke angka atau akses laporan;
- kejadian berasal dari percobaan terkendali, maintenance terjadwal, atau tenant non-operasional yang memang sedang diuji;
- sudah ada alasan operasional yang jelas dan bisa diaudit.

### Kapan tidak boleh di-ignore

Insiden tidak boleh diabaikan bila:

- tingkat kritis atau tinggi tanpa penjelasan yang sah;
- berhubungan dengan COGS mismatch, strict reader blocked, validate failed, rebuild failed, atau refund mismatch yang masih aktif;
- memengaruhi tenant live yang sedang dipakai operasional;
- muncul berulang pada hari yang sama;
- belum ada penilaian dampak minimal.

### Alasan ignore harus seperti apa

Alasan `abaikan` harus singkat, spesifik, dan dapat diaudit. Minimal memuat:

- konteks tenant atau window kejadian;
- alasan kenapa tidak berdampak;
- siapa yang memutuskan;
- apakah perlu dipantau ulang.

Contoh alasan yang baik:

- `Tenant uji internal, event muncul saat maintenance terjadwal, tidak berdampak ke laporan live.`
- `Refresh ulang berhasil, tidak ada event lanjutan, tenant non-prioritas, dipantau sampai EOD.`

## 8. Aturan Eskalasi

### Ditangani ops

Ops internal menangani langsung bila:

- insiden rendah atau sedang;
- belum ada indikasi kerusakan angka yang material;
- tindakan awal cukup berupa pengecekan dashboard, pengecekan tenant, dan pemantauan kejadian ulang.

### Dieskalasi ke engineer

Eskalasi ke engineer on-call bila:

- ada strict reader blocked;
- validate gagal atau bangun ulang gagal;
- missing aggregate atau header mismatch berulang;
- query laporan lambat mulai mengganggu akses;
- ada kegagalan proses sistem, bukan hanya keterlambatan tampilan.

### Dieskalasi ke finance/product owner

Eskalasi ke finance reviewer atau product owner bila:

- mismatch berdampak ke angka laporan;
- ada potensi salah keputusan bisnis karena angka berbeda;
- insiden terjadi saat closing, review periodik, atau audit;
- perlu keputusan apakah laporan masih boleh dipakai.

### SLA sederhana

| Tingkat | Respons Awal | Tindakan Awal | Target Stabil / Keputusan |
|---|---|---|---|
| kritis | 15 menit | 30 menit | 4 jam |
| tinggi | 30 menit | 4 jam | H+1 |
| sedang | 4 jam | H+1 | H+2 |
| rendah | H+1 | H+2 | H+3 |

`Target Stabil / Keputusan` berarti masalah sudah pulih, atau sudah ada keputusan resmi apakah ditandai selesai, dipantau, atau masuk eskalasi lanjutan.

## 9. Checklist Harian

### Awal hari

- buka overview monitoring;
- cek `Masalah Kritis Terbuka`;
- cek `Tenant Terdampak`;
- cek apakah ada insiden terbuka dari hari sebelumnya;
- pastikan semua insiden kritis dan tinggi punya PIC.

### Saat jam operasional

- pantau daftar insiden status terbuka;
- review insiden baru berdasarkan tingkat dan jumlah kejadian;
- cek tenant yang mulai muncul berulang;
- eskalasi segera bila ada strict reader blocked atau mismatch material.

### Penutup hari / EOD

- pastikan tidak ada insiden kritis terbuka tanpa owner;
- rekap insiden yang ditandai selesai hari itu;
- rekap insiden yang diabaikan beserta alasannya;
- tandai insiden yang perlu handoff ke hari berikutnya.

## 10. Checklist Setelah Rebuild / Validasi

### Setelah validasi selesai

- cek apakah event validate failed sudah tidak muncul lagi;
- cek tenant terkait di dashboard overview;
- pastikan tidak ada mismatch aktif yang tersisa pada tenant yang sama;
- bila relevan, minta finance reviewer menilai apakah angka sudah aman dipakai.

### Setelah bangun ulang dijalankan

- cek apakah event rebuild failed tidak muncul;
- cek apakah strict reader blocked sudah hilang bila itu pemicu awal;
- cek tenant impact tidak meluas;
- pantau kejadian baru minimal sampai window observasi selesai.

### Setelah refresh summary dilakukan

- cek apakah daily summary refresh failed hilang;
- cek ringkasan harian kembali terbarui;
- cek apakah cross_report_mismatch ikut membaik atau tetap ada;
- bila mismatch tetap ada, jangan tandai selesai hanya karena refresh berhasil.

## 11. Contoh Kasus

### Contoh 1: mismatch COGS

Gejala:

- muncul `cross_report_mismatch` atau `sales_cost_header_mismatch` pada tenant aktif;
- jumlah kejadian bertambah dalam hari yang sama.

Dampak:

- laporan biaya dan laporan pembanding tidak konsisten;
- finance reviewer tidak bisa langsung percaya hasil angka.

Tindakan:

- buka detail insiden;
- cek tenant, periode, dan jumlah kejadian;
- libatkan finance reviewer;
- eskalasi ke engineer bila mismatch tidak hilang setelah refresh atau validasi.

Kapan selesai:

- mismatch sudah hilang;
- tidak ada kejadian baru;
- reviewer menyatakan angka aman dipakai.

### Contoh 2: aggregate hilang

Gejala:

- muncul `sales_cost_missing_aggregate`;
- tenant tertentu tidak punya data agregat yang dibutuhkan.

Dampak:

- pembacaan biaya bisa tidak aman;
- insiden lain seperti strict reader blocked bisa ikut muncul.

Tindakan:

- prioritaskan tenant terdampak;
- eskalasi ke engineer;
- cek apakah perlu validasi atau bangun ulang sesuai approval.

Kapan selesai:

- agregat tersedia kembali;
- event lanjutan tidak muncul;
- tenant bisa dibaca normal dan tidak ada blokir aktif.

### Contoh 3: strict reader block

Gejala:

- muncul `strict_reader_blocked` pada tenant tertentu;
- pembacaan laporan dianggap tidak aman.

Dampak:

- user atau sistem tidak boleh memakai hasil baca tertentu;
- review angka tenant tertunda.

Tindakan:

- jangan abaikan;
- cek event pemicu sebelumnya;
- eskalasi segera ke engineer on-call;
- beri tahu finance reviewer bila tenant sedang dipakai review harian.

Kapan selesai:

- blokir sudah hilang;
- akar pemicu minimum sudah diperbaiki atau dimitigasi;
- tidak ada event baru sejenis dalam window observasi.

## 12. Batasan SOP

SOP ini membantu operasional mengambil keputusan cepat dan konsisten dari dashboard monitoring.

SOP ini bukan:

- pengganti root cause analysis engineering;
- pengganti review teknis mendalam terhadap log, scheduler, atau query;
- pengganti validasi terhadap database rollout nyata atau produksi yang sebenarnya.

Jika ada insiden yang memerlukan pembuktian terhadap data live atau perilaku historis kompleks, keputusan akhir tetap membutuhkan validasi tambahan oleh engineer dan reviewer yang berwenang.

## 13. Ringkasan Keputusan

### Dashboard dinyatakan sehat

- tidak ada `Masalah Kritis Terbuka`;
- insiden tinggi terkendali atau sudah punya tindakan jelas;
- tenant terdampak rendah dan tidak bertambah;
- tidak ada strict reader blocked aktif;
- validasi, bangun ulang, dan refresh harian terakhir dalam kondisi aman.

### Status waspada

- ada insiden tinggi atau sedang yang berulang;
- tenant terdampak mulai bertambah;
- ada mismatch yang masih dalam investigasi;
- ada refresh atau query lambat yang belum stabil.

### Status insiden serius

- ada insiden kritis aktif;
- ada strict reader blocked pada tenant live;
- validasi gagal atau bangun ulang gagal pada tenant penting;
- mismatch angka material belum terselesaikan;
- lebih dari satu tenant terdampak pada jalur yang sama.