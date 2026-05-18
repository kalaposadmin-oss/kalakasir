# Audit `feature-gating.json` untuk Paket UMKM

Tanggal: 2026-04-11  
Ruang lingkup: audit bisnis, UX, dan arsitektur gating untuk `config/feature-gating.json`

## 1. Ringkasan Eksekutif

Kesimpulan utama: struktur paket saat ini sudah bergerak ke arah yang benar, tetapi masih belum rapi sebagai produk yang siap dijual karena ada tiga masalah besar.

Masalah pertama adalah Paket S masih belum dibingkai sebagai paket operasional UMKM yang benar-benar lengkap. Saat ini Paket S sudah punya POS, stok, pembelian, retur kasir, dan ringkasan laporan. Itu bagus. Tetapi kebutuhan yang sangat terasa bagi pemilik usaha kecil seperti melihat uang usaha di akun kas/bank, melihat biaya operasional dasar, melihat nilai stok dasar, dan melihat arus aktivitas pembelian secara sederhana belum dipetakan secara tegas sebagai entitlement dasar.

Masalah kedua adalah banyak key di `feature-gating.json` baru berhenti di level deklarasi, belum di level enforcement. Di route web masih banyak halaman yang hanya memakai gate umum `dash.overview`, dan di API ada beberapa endpoint sensitif yang masih auth-only atau role-only. Artinya paket di atas kertas belum sepenuhnya sama dengan paket di runtime.

Masalah ketiga adalah struktur feature sekarang masih terlalu teknis, belum cukup product-friendly. Ada key yang sudah pas untuk dijual, seperti `product.materials` dan `inventory.production_batches`. Tetapi ada juga area yang masih terlalu generik, misalnya akses ke finance summary, bank accounts, dan ringkasan uang usaha. Itu membuat Paket S terasa kurang jelas nilainya, sementara Paket M dan P belum sepenuhnya punya pembeda yang konsisten.

Rekomendasi final:

- Paket S harus menjadi paket yang benar-benar bisa dipakai warung/toko kecil untuk jualan, beli stok, lihat laba-rugi dasar, lihat uang usaha, dan pantau stok dasar.
- Paket M harus menjadi paket naik kelas untuk usaha yang mulai punya kontrol operasional lebih rapi: pelanggan, loyalty, resep/BOM, bahan baku, produksi, tim/staf, promo, pajak, ekspor.
- Paket P tetap menjadi paket kontrol lanjutan: valuation detail, FIFO layer detail, audit log, lock period, COA lanjutan, role granular, multi cabang penuh, observability, integrations.

Dengan arah itu, secara bisnis produk menjadi jauh lebih gampang dijelaskan:

- `S`: jalanin usaha harian dan tahu kondisi usaha.
- `M`: rapikan operasi dan mulai scale.
- `P`: kontrol penuh, governance, dan analitik lanjutan.

## 2. Kondisi Aktual Config dan Runtime Saat Ini

Secara struktur, `feature-gating.json` sudah memuat:

- `planEnum`: `S`, `M`, `P`
- `planLabels`: `Starter`, `Medium`, `Pro`
- `featureFlags`
- `planMapping`

Secara runtime, akses saat ini dibaca terutama dari:

- `App\Support\FeatureGateConfig`
- `App\Services\SubscriptionService`

Temuan paling penting dari audit kode:

- Runtime utama membaca plan aktif dari `tenants.plan`.
- Banyak route web masih memakai `web_access:...,dash.overview` sehingga belum spesifik per domain.
- Beberapa endpoint API sensitif belum memakai `feature_gate:<key>` yang sesuai.
- Key yang baru ditambahkan untuk resep dan produksi sudah lebih rapi secara enforcement daripada mayoritas key lama.

Key yang sudah terlihat jelas benar-benar ditegakkan di route-level antara lain:

- `product.materials`
- `product.recipe_management`
- `inventory.production_batches`
- `settings.inventory_valuation`
- `settings.observability`

Tetapi banyak key lain masih belum setara antara config dan runtime, terutama area:

- customer
- supplier
- loyalty
- finance summary
- bank accounts
- lock periods
- branches
- users
- tax/promo

Catatan penting lain: `planMapping` berisiko drift dari `featureFlags`. Saat ini `featureFlags` sudah memuat key baru seperti `product.recipe_management` dan `inventory.production_batches`, tetapi pola duplikasi seperti ini rawan tertinggal. Bila `planMapping` memang tidak jadi sumber kebenaran runtime, sebaiknya dihapus atau dihasilkan otomatis dari `featureFlags`.

Update 2026-04-12: drift internal antara `featureFlags` dan `planMapping` sudah dicek ulang dan hasilnya konsisten. Pada pass cleanup yang sama, key aktif yang belum punya surface runtime jujur dikeluarkan dari config aktif: `dash.control`, `history.audit_log`, `reports.inventory_fifo_layers`, `finance.cash_flow`, `product.valuation`, `settings.promo_default`, `settings.roles_permissions`, dan `settings.multi_branch`.

## 3. Apakah Struktur Paket Sekarang Sudah Cocok untuk UMKM?

Jawaban singkat: belum sepenuhnya.

Yang sudah cocok:

- Paket S sudah tidak terlalu pelit untuk transaksi inti. POS, stok, pembelian, penyesuaian stok, retur kasir, dan laporan dasar sudah masuk.
- Paket M sekarang sudah lebih masuk akal karena bahan baku, resep, dan produksi ada di sini. Ini cocok untuk usaha makanan/minuman, bakery, frozen food, dan manufaktur ringan.
- Paket P sudah punya bentuk sebagai paket governance dan kontrol tingkat lanjut.

Yang belum cocok:

- Paket S belum secara eksplisit menjanjikan visibilitas uang usaha dan biaya operasional dasar, padahal itu justru kebutuhan paling emosional untuk owner UMKM.
- Paket S belum punya key yang tegas untuk bank accounts dan ringkasan kas/bank, padahal di kode area itu sudah ada.
- Paket S belum punya key terpisah untuk inventory value basic, padahal owner sering ingin tahu "stok saya nilainya berapa" tanpa perlu valuation engine yang rumit.
- Paket M dan P belum dibedakan secara konsisten di semua route sehingga secara UX bisa terasa bocor atau membingungkan.

Secara product-market fit, Paket S seharusnya tidak hanya berarti "fitur sedikit", tetapi "cukup untuk menjalankan usaha dan paham kondisi usaha". Saat ini struktur belum sepenuhnya sampai ke level itu.

## 4. Audit Per Feature Key

Tabel di bawah mengevaluasi semua key yang saat ini ada di `feature-gating.json`.

| Feature Key | Paket Saat Ini | Area | Makna Bisnis | Sudah Tepat? | Rekomendasi Final | Alasan Bisnis | Alasan UX | Alasan Teknis |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `onboarding.flow` | S/M/P | Global | Tenant baru bisa setup awal | Ya | Tetap S/M/P | Semua paket butuh aktivasi cepat | Tidak boleh terkunci oleh paket | Sudah aman sebagai akses awal |
| `search.global` | M/P | Global | Pencarian lintas modul | Ya | Tetap M/P | Nilai tambah efisiensi, bukan core | S tidak perlu dibebani tool global | Bisa tetap sebagai premium convenience |
| `notification.list` | M/P | Global | Daftar notifikasi kerja | Ya | Tetap M/P | Cocok untuk usaha yang sudah lebih aktif | S tetap sederhana | Bisa digate di navbar/inbox |
| `help.center` | S/M/P | Global | Pusat bantuan | Ya | Tetap S/M/P | Support harus bisa diakses semua | Mengurangi kebingungan onboarding | Aman dibuka universal |
| `dash.overview` | S/M/P | Dashboard | Dashboard utama | Ya | Tetap S/M/P | Semua user butuh landing page | Harus jadi titik masuk default | Sudah jadi gate umum, tetapi jangan dipakai berlebihan |
| `dash.insights` | S/M/P | Dashboard | Insight cepat usaha | Ya, dengan catatan | Tetap S/M/P | Owner kecil perlu angka inti | Insight harus simple untuk S | Perlu bedakan isi card berdasarkan paket |
| `dash.control` | P | Dashboard | Panel kontrol lanjutan | Ya | Tetap P | Cocok untuk pengawasan tingkat lanjut | Jangan munculkan kontrol kompleks di S/M | Sudah bagus sebagai premium admin surface |
| `pos.checkout` | S/M/P | POS | Transaksi penjualan | Ya | Tetap S/M/P | Core engine bisnis | POS wajib ada di semua paket | Harus selalu stabil dan cepat |
| `pos.customer` | M/P | POS | Pilih pelanggan saat checkout | Ya | Tetap M/P | Cocok saat usaha mulai kelola relasi | S tidak wajib pakai pelanggan formal | Gate perlu konsisten di UI POS |
| `pos.hold_bill` | S/M/P | POS | Simpan tagihan sementara | Ya | Tetap S/M/P | Sangat berguna di toko kecil | Ini perilaku kasir yang umum | Sudah pantas sebagai fitur dasar |
| `pos.split_payment` | S/M/P | POS | Bayar campuran | Ya | Tetap S/M/P | Praktis untuk operasional harian | Banyak toko kecil butuh ini | Sudah cocok sebagai basic POS capability |
| `pos.refund` | S/M/P | POS | Refund penjualan | Ya | Tetap S/M/P | Retur pelanggan itu kebutuhan dasar | Tidak boleh terasa premium | Sudah bagian dari trust operasional |
| `pos.void` | S/M/P | POS | Batalkan transaksi | Ya | Tetap S/M/P | Koreksi kasir harus tersedia | Mengurangi friksi kasir | Tetap butuh audit trail yang lebih kuat di P |
| `shift.open` | S/M/P | Shift | Buka kasir | Ya | Tetap S/M/P | Cocok untuk toko kecil maupun besar | Pola kerja kasir dasar | Sudah fundamental |
| `shift.close` | S/M/P | Shift | Tutup kasir | Ya | Tetap S/M/P | Core kontrol operasional | Tanpa ini POS kurang rapi | Sudah benar |
| `shift.history` | S/M/P | Shift | Riwayat shift | Ya | Tetap S/M/P | Owner kecil tetap perlu cek penutupan | Harus mudah ditelusuri | Layak jadi basic visibility |
| `shift.print_summary` | M/P | Shift | Cetak ringkasan shift | Cukup tepat | Tetap M/P | Nilai tambah operasional, bukan syarat utama | S tetap sederhana | Gate di halaman cetak/aksi print |
| `history.sales` | S/M/P | History | Riwayat penjualan | Ya | Tetap S/M/P | Core pengecekan transaksi | Semua paket butuh histori | Sudah wajar jadi dasar |
| `history.payments` | S/M/P | History | Riwayat pembayaran | Ya | Tetap S/M/P | Penting untuk telusur kasir | Owner kecil sering cek ini | Tetap dasar |
| `history.stock` | S/M/P | History | Riwayat pergerakan stok | Ya | Tetap S/M/P | Owner kecil perlu tahu stok berubah karena apa | Membantu kepercayaan pada sistem | Perlu konsistensi dengan stock ledger |
| `history.audit_log` | P | History | Jejak audit mendalam | Ya | Tetap P | Nilai governance, bukan kebutuhan warung | UI audit tidak cocok untuk user dasar | Tepat sebagai premium control |
| `reports.summary` | S/M/P | Reports | Ringkasan laporan utama | Ya | Tetap S/M/P | Semua paket perlu laporan ringkas | Cocok jadi landing laporan | Tetap basic reporting surface |
| `reports.analytics_products` | S/M/P | Reports | Produk terlaris/performa produk | Ya | Tetap S/M/P | Sangat relevan untuk owner kecil | Insight mudah dipahami | Aman jadi dasar |
| `reports.analytics_payments` | M/P | Reports | Analitik metode bayar | Ya | Tetap M/P | Lebih cocok untuk usaha yang mulai optimasi channel bayar | Bukan kebutuhan pertama user S | Sudah cukup sebagai add-on insight |
| `reports.purchases_view` | M/P | Reports | Laporan pembelian | Tidak sepenuhnya | Tetap M/P, tambah `history.purchases` di S/M/P | Laporan formal pembelian bisa naik kelas, tetapi histori pembelian dasar harus ada di S | User S tetap perlu melihat daftar pembelian tanpa UI report yang berat | Perlu pisahkan history vs analytics/report |
| `reports.inventory_view` | S/M/P | Reports | Laporan stok dasar | Ya | Tetap S/M/P | Sangat penting untuk UMKM | Owner kecil butuh lihat stok cepat | Tepat sebagai report dasar |
| `reports.inventory_stock_card` | P | Reports | Kartu stok detail | Ya | Tetap P | Lebih cocok untuk investigasi dan audit | UI detail ini terlalu kompleks untuk S | Sudah cocok premium |
| `reports.inventory_fifo_layers` | P | Reports | Detail layer FIFO | Ya | Tetap P | Ini alat kontrol costing lanjutan | Owner kecil tidak butuh lihat layer | Cocok premium dan specialist |
| `reports.download_pdf` | M/P | Reports | Unduh PDF | Ya | Tetap M/P | Nilai tambah administrasi | S cukup lihat di layar | Cocok premium ringan |
| `reports.export_csv` | M/P | Reports | Export CSV | Ya | Tetap M/P | Nilai tambah untuk olah data lanjut | Mengurangi clutter di S | Cocok untuk user yang sudah lebih matang |
| `finance.profit_loss` | S/M/P | Finance | Laba rugi dasar | Ya | Tetap S/M/P | Owner UMKM wajib tahu untung/rugi | Ini justru alasan membeli software | Sudah tepat, tetapi enforcement perlu diperjelas |
| `finance.balance_sheet` | P | Finance | Neraca | Ya | Tetap P | Lebih cocok untuk user akuntansi lebih matang | Neraca terlalu berat untuk S | Sudah cocok premium |
| `finance.cash_flow` | P | Finance | Laporan arus kas penuh | Ya, dengan catatan | Tetap P | Laporan formal arus kas bisa premium | S tetap perlu visibilitas uang, tapi tidak harus cash flow formal | Tambah key basic kas/bank terpisah |
| `finance.lock_period` | P | Finance | Kunci periode | Ya | Tetap P | Governance tingkat lanjut | Tidak cocok untuk S | Sudah premium control |
| `product.list` | S/M/P | Product | Kelola produk | Ya | Tetap S/M/P | Core katalog dagang | Semua tenant butuh ini | Sudah dasar |
| `product.category` | S/M/P | Product | Kategori produk | Ya | Tetap S/M/P | Katalog dasar | Membantu user kecil tetap rapi | Tepat sebagai basic setup |
| `product.materials` | M/P | Product | Master bahan baku | Ya | Tetap M/P | Cocok untuk usaha olahan/manufaktur ringan | S tidak perlu dibebani bahan baku | Sudah baru ditegakkan dengan benar |
| `product.recipe_management` | M/P | Product | BOM/resep dan production mode | Ya | Tetap M/P | Pembeda kuat Paket M | Menjaga S tetap sederhana | Sudah tepat, perlu konsisten di semua entry point |
| `inventory.production_batches` | M/P | Inventory | Produksi batch | Ya | Tetap M/P | Ini operasional menengah | S tidak cocok | Sudah bagus sebagai gate eksplisit |
| `product.pricing` | S/M/P | Product | Harga jual dan aturan harga dasar | Ya | Tetap S/M/P | Harga jual adalah kebutuhan dasar | Tidak boleh premium | Sudah tepat |
| `product.valuation` | P | Product | Valuation detail per produk | Ya | Tetap P | Ini kontrol costing lanjutan | UI valuation detail terlalu kompleks untuk S | Cocok premium |
| `inventory.stock` | S/M/P | Inventory | Lihat stok | Ya | Tetap S/M/P | Stok dasar wajib | Harus sangat mudah diakses | Sudah fundamental |
| `inventory.adjustment` | S/M/P | Inventory | Penyesuaian stok/opname | Ya | Tetap S/M/P | Stock opname dasar penting sejak awal | Sangat berguna bahkan untuk toko kecil | Sudah benar |
| `inventory.transfer` | S/M/P | Inventory | Transfer stok antar lokasi | Kurang tepat | Pindah ke M/P | Lebih relevan saat usaha mulai punya alur lokasi/gudang | Membingungkan bagi S yang single-store | Sebaiknya satu paket dengan operasi menengah |
| `purchase.order` | S/M/P | Purchase | Buat pembelian | Ya | Tetap S/M/P | Beli stok itu kebutuhan dasar | Wajib untuk retail/warung | Sudah benar |
| `purchase.receive` | S/M/P | Purchase | Penerimaan barang | Ya | Tetap S/M/P | Barang datang harus bisa diterima | Ini inti operasional | Sudah benar |
| `purchase.return` | M/P | Purchase | Retur ke supplier | Ya | Tetap M/P | Nilai tambah kontrol pembelian | Tidak wajib untuk semua toko kecil | Cukup baik di M/P |
| `purchase.stock_purchase` | S/M/P | Purchase | Pembelian stok standar | Ya | Tetap S/M/P | Core replenishment | Harus ada sejak awal | Sudah tepat |
| `supplier.manage` | S/M/P | Supplier | Kelola pemasok | Ya, dengan catatan | Tetap S/M/P | Owner kecil tetap perlu daftar supplier | Sering dipakai bersama pembelian | Route/API perlu digate spesifik, bukan generic |
| `customer.manage` | M/P | Customer | Kelola pelanggan | Ya | Tetap M/P | Cocok saat relasi pelanggan mulai penting | S bisa tetap transaksi tanpa CRM ringan | Sudah baik untuk diferensiasi |
| `loyalty.program` | M/P | Customer | Poin/member/loyalty | Ya | Tetap M/P | Jelas fitur naik kelas | Tidak perlu membebani S | Sudah cocok |
| `settings.store_profile` | S/M/P | Settings | Profil toko | Ya | Tetap S/M/P | Semua paket perlu setup identitas | Basic admin | Sudah benar |
| `settings.printer_receipt` | S/M/P | Settings | Cetak struk | Ya | Tetap S/M/P | POS dasar butuh printer | Tanpa ini pengalaman kasir terganggu | Tepat basic |
| `settings.devices` | S/M/P | Settings | Perangkat kasir | Ya | Tetap S/M/P | Masih relevan untuk setup dasar | Harus tersedia walau sederhana | Sudah tepat |
| `settings.payments` | S/M/P | Settings | Metode pembayaran dasar | Ya, tetapi kurang lengkap | Tetap S/M/P, dengan pemisahan `finance.bank_accounts` sebagai entitlement eksplisit | Semua user perlu atur metode bayar | User S butuh cash/bank/qris yang jelas | `finance.bank_accounts` sudah aktif; `finance.money_movement` tetap deferred sampai ada modul terpisah |
| `settings.subscription_billing` | S/M/P | Settings | Info paket/tagihan | Ya | Tetap S/M/P | Semua user perlu lihat status paket | Harus selalu tersedia | Sudah benar |
| `settings.tax` | M/P | Settings | Pajak | Ya | Tetap M/P | Cocok saat usaha mulai lebih formal | S tidak perlu beban awal | Sudah cukup pas |
| `settings.promo_default` | M/P | Settings | Promo default | Ya | Tetap M/P | Fitur scale penjualan | Jangan ramaikan S | Tepat di M/P |
| `settings.staff_basic` | M/P | Settings | Staff/user dasar | Ya | Tetap M/P | Menandai usaha mulai punya tim | S bisa owner-operated | Gate perlu lebih spesifik di routes/users |
| `settings.branches_manage` | M/P | Settings | Kelola cabang | Ya | Tetap M/P | Relevan saat usaha bertambah lokasi | Terlalu kompleks untuk S | Route masih perlu dibersihkan |
| `settings.coa` | Deferred | Settings | Chart of accounts penuh | Belum sebagai entitlement aktif | Tunda sampai ada modul COA penuh yang terpisah dari halaman akun kas/bank | Halaman runtime saat ini masih fokus pada akun kas/bank operasional | Jangan jual sebagai entitlement premium sebelum surface-nya benar-benar spesifik | `finance.bank_accounts` menjadi key aktif untuk surface yang ada sekarang |
| `settings.notifications` | M/P | Settings | Pengaturan notifikasi | Ya | Tetap M/P | Fitur pelengkap untuk operasi lebih matang | S tidak wajib | Cocok sebagai add-on |
| `settings.roles_permissions` | P | Settings | Role/permission granular | Ya | Tetap P | Governance lanjutan | Terlalu kompleks untuk S/M | Tepat premium |
| `settings.multi_branch` | P | Settings | Multi cabang penuh | Ya | Tetap P | Ini pembeda top-tier | UX sangat rumit bila dibuka luas | Cocok premium tinggi |
| `settings.inventory_valuation` | P | Settings | Pengaturan valuation lanjutan | Ya, untuk surface premium | Tetap P, dengan `settings.inventory_method_basic` dipisah sebagai surface dasar S/M/P | Owner S perlu pilih metode dasar, tetapi rebuild/advanced valuation tetap premium | Pisahkan basic choice vs advanced control | Route web premium tetap khusus rebuild, audit, dan report valuation |
| `settings.observability` | P | Settings | Monitoring/health system | Ya | Tetap P | Fitur operator/platform | Tidak relevan untuk mayoritas tenant | Sudah tepat |
| `settings.integrations` | P | Settings | Integrasi eksternal | Ya | Tetap P | Pembeda premium jelas | S/M tidak perlu beban integrasi | Sudah tepat |

## 5. Gap Besar yang Mengganggu Paket S untuk UMKM

Bagian ini mendokumentasikan lima gap awal yang muncul saat audit dimulai, sebelum config aktif dirapikan ke state runtime yang sekarang.

### 5.1. Belum ada key eksplisit untuk ringkasan keuangan dasar

Saat ini ada `finance.profit_loss`, tetapi akses ke finance summary di runtime masih belum diperlakukan sebagai produk yang jelas. Padahal owner UMKM biasanya bertanya:

- hari ini omzet berapa
- untung/rugi berapa
- biaya operasional berapa
- uang saya sekarang ada di mana

Perlu key eksplisit seperti `finance.summary_basic` untuk menandai layar ringkasan uang usaha yang ramah owner.

### 5.2. Belum ada key eksplisit untuk akun kas/bank

Bank accounts sudah ada di kode, termasuk list dan riwayat transaksi. Tetapi belum dipetakan sebagai entitlement yang jelas. Ini problem besar karena melihat saldo/arus akun pembayaran adalah kebutuhan dasar owner, bukan fitur premium.

Perlu key baru seperti `finance.bank_accounts`.

### 5.3. Belum ada modul terpisah untuk biaya operasional dasar

Finance summary sudah menampilkan breakdown beban secara sederhana, dan kebutuhan owner harian ini saat ini dipayungi oleh `finance.summary_basic`. Namun belum ada page atau API khusus untuk CRUD beban operasional yang berdiri sendiri.

Karena itu `finance.expense_tracking` belum dijadikan key aktif. Key ini baru layak diaktifkan bila modul biaya operasional sudah benar-benar terpisah dari finance summary.

### 5.4. Belum ada modul terpisah untuk perpindahan uang antar akun

Pemilik usaha kecil sangat peduli pada perpindahan uang dari kas ke bank, bank ke bank, atau pencatatan akun pembayaran. Namun saat ini belum ada page atau API khusus yang bisa dijadikan target entitlement tersendiri.

Karena itu `finance.money_movement` tetap deferred. Key ini sebaiknya baru diaktifkan setelah ada modul runtime yang berdiri sendiri.

### 5.5. Nilai stok dasar belum dipisahkan dari valuation detail premium

`product.valuation` dan `settings.inventory_valuation` saat ini terlalu dekat dengan konsep costing lanjutan. Owner kecil tidak perlu melihat layer FIFO, tetapi mereka tetap ingin tahu nilai stok dasar dalam rupiah.

Perlu key baru seperti `finance.inventory_value_basic` atau `inventory.value_basic`.

## 6. Gap Besar antara Config dan Enforcement Runtime

Masalah terbesar di arsitektur saat ini bukan cuma pembagian paket, tetapi konsistensi eksekusinya.

### 6.1. Overuse `dash.overview`

Di route web, banyak halaman domain masih dilindungi hanya oleh `dash.overview`. Akibatnya secara nyata paket bisa terlihat lebih longgar daripada yang dimaksud di config.

Halaman yang sebaiknya diganti ke gate spesifik antara lain area:

- customers
- suppliers
- branches
- users
- loyalty
- finance summary
- tax/promo
- purchase returns
- bank account pages

### 6.2. API sensitif yang belum pakai gate feature spesifik

Beberapa endpoint API penting masih belum memakai `feature_gate:<key>` yang sesuai, misalnya area:

- `bank-accounts`
- `customers`
- `suppliers`
- `finance-summary`
- `lock-periods`
- `loyalty`

### 6.3. UI hiding belum selalu diikuti backend hard enforcement

Untuk resep dan produksi, sekarang sudah lebih baik karena UI lock diikuti hard guard di controller. Pola ini harus diterapkan ke modul lain. Jangan berhenti di hide menu saja.

### 6.4. `planMapping` berpotensi drift

Bila `featureFlags` dan `planMapping` sama-sama disimpan manual, akan ada risiko paket di dokumen berbeda dengan paket di runtime. Ini sudah terlihat dari penambahan key baru yang lebih cepat masuk ke `featureFlags` daripada struktur lain.

## 7. Paket S yang Seharusnya Dijual ke UMKM

Kalau tujuan produk adalah dipakai warung, toko kelontong, toko frozen, kedai kecil, laundry kecil, atau usaha rumah tangga, maka Paket S harus menjawab kebutuhan berikut:

- bisa jualan
- bisa catat pembelian stok
- bisa koreksi stok
- bisa lihat histori transaksi
- bisa lihat untung/rugi sederhana
- bisa lihat stok dan nilai stok dasar
- bisa lihat uang usaha di kas/bank/akun pembayaran
- bisa lihat biaya operasional dasar
- bisa atur metode pembayaran dasar

Dengan kata lain, Paket S bukan paket "cuma POS", tetapi paket "jalanin usaha dan paham kondisi usaha".

Karena itu, secara final Paket S sebaiknya berisi:

- POS inti
- shift inti
- product inti
- inventory inti
- purchase inti
- supplier dasar
- reports inti
- finance summary dasar
- bank accounts dasar
- expense tracking dasar
- money movement dasar
- inventory value basic
- settings toko/perangkat/pembayaran/subscription

## 8. Paket M yang Seharusnya Menjadi Alasan Upgrade

Paket M harus terasa sebagai paket untuk usaha yang mulai lebih serius mengatur operasi, bukan sekadar menambahkan fitur acak.

Nilai jual Paket M yang paling kuat menurut kondisi kode saat ini adalah:

- customer management
- loyalty
- search global
- notifications
- materials
- recipe management
- production batches
- purchase return
- pajak dan promo
- staff basic
- branch management ringan
- export/report tambahan
- inventory transfer

Narasi jual yang paling masuk akal:

- dari jualan biasa ke pengelolaan pelanggan
- dari dagang biasa ke olahan/produksi
- dari owner sendiri ke tim kecil
- dari lihat data ke mulai mengolah data

Dengan begitu, Paket M punya batas yang jelas terhadap S.

## 9. Paket P yang Seharusnya Tetap Premium dan Tidak Bocor

Paket P harus fokus pada kontrol, governance, costing detail, dan integrasi. Jangan isi P dengan fitur dasar yang justru membuat Paket S terasa hambar.

Paket P sebaiknya tetap memegang:

- balance sheet
- cash flow formal
- lock period
- audit log
- inventory stock card detail
- FIFO layer detail
- product valuation detail
- COA penuh
- role/permission granular
- multi branch penuh
- observability
- integrations
- dashboard control khusus

Ini membuat P tetap tampak bernilai tinggi tanpa harus mengorbankan kegunaan S.

## 10. Usulan Struktur Feature Baru

Berikut key baru yang paling layak ditambahkan agar bahasa produk lebih jujur dan entitlements lebih mudah dipetakan.

| Key Baru | Paket Rekomendasi | Tujuan |
| --- | --- | --- |
| `finance.summary_basic` | S/M/P | Ringkasan usaha ramah owner: omzet, laba kotor, laba bersih sederhana, beban, snapshot uang usaha |
| `finance.bank_accounts` | S/M/P | Lihat akun kas/bank/akun pembayaran dan riwayat dasar |
| `finance.expense_tracking` | Deferred | Belum dijadikan key aktif karena runtime saat ini masih menumpang di `finance.summary_basic` |
| `finance.money_movement` | Deferred | Belum dijadikan key aktif karena belum ada page/API target yang berdiri sendiri |
| `finance.inventory_value_basic` | S/M/P | Nilai stok dasar tanpa layar valuation kompleks |
| `history.purchases` | S/M/P | Riwayat pembelian dasar terpisah dari laporan pembelian formal |
| `settings.inventory_method_basic` | S/M/P | Pilih metode dasar inventory costing tanpa membuka advanced valuation admin |

Key-key ini penting karena memisahkan:

- kebutuhan owner harian
- kebutuhan operasional menengah
- kebutuhan audit dan governance

## 11. Draf `featureFlags` yang Lebih Sehat

Berikut draft `featureFlags` yang saya rekomendasikan. Ini bukan sekadar perubahan kosmetik, tetapi penataan ulang supaya value ladder S -> M -> P lebih jelas.

```json
{
  "onboarding.flow": ["S", "M", "P"],
  "search.global": ["M", "P"],
  "notification.list": ["M", "P"],
  "help.center": ["S", "M", "P"],

  "dash.overview": ["S", "M", "P"],
  "dash.insights": ["S", "M", "P"],
  "dash.control": ["P"],

  "pos.checkout": ["S", "M", "P"],
  "pos.customer": ["M", "P"],
  "pos.hold_bill": ["S", "M", "P"],
  "pos.split_payment": ["S", "M", "P"],
  "pos.refund": ["S", "M", "P"],
  "pos.void": ["S", "M", "P"],

  "shift.open": ["S", "M", "P"],
  "shift.close": ["S", "M", "P"],
  "shift.history": ["S", "M", "P"],
  "shift.print_summary": ["M", "P"],

  "history.sales": ["S", "M", "P"],
  "history.payments": ["S", "M", "P"],
  "history.stock": ["S", "M", "P"],
  "history.purchases": ["S", "M", "P"],
  "history.audit_log": ["P"],

  "reports.summary": ["S", "M", "P"],
  "reports.analytics_products": ["S", "M", "P"],
  "reports.analytics_payments": ["M", "P"],
  "reports.purchases_view": ["M", "P"],
  "reports.inventory_view": ["S", "M", "P"],
  "reports.inventory_stock_card": ["P"],
  "reports.inventory_fifo_layers": ["P"],
  "reports.download_pdf": ["M", "P"],
  "reports.export_csv": ["M", "P"],

  "finance.summary_basic": ["S", "M", "P"],
  "finance.profit_loss": ["S", "M", "P"],
  "finance.bank_accounts": ["S", "M", "P"],
  "finance.inventory_value_basic": ["S", "M", "P"],
  "finance.balance_sheet": ["P"],
  "finance.cash_flow": ["P"],
  "finance.lock_period": ["P"],

  "product.list": ["S", "M", "P"],
  "product.category": ["S", "M", "P"],
  "product.materials": ["M", "P"],
  "product.recipe_management": ["M", "P"],
  "product.pricing": ["S", "M", "P"],
  "product.valuation": ["P"],

  "inventory.stock": ["S", "M", "P"],
  "inventory.adjustment": ["S", "M", "P"],
  "inventory.transfer": ["M", "P"],
  "inventory.production_batches": ["M", "P"],

  "purchase.order": ["S", "M", "P"],
  "purchase.receive": ["S", "M", "P"],
  "purchase.return": ["M", "P"],
  "purchase.stock_purchase": ["S", "M", "P"],

  "supplier.manage": ["S", "M", "P"],
  "customer.manage": ["M", "P"],
  "loyalty.program": ["M", "P"],

  "settings.store_profile": ["S", "M", "P"],
  "settings.printer_receipt": ["S", "M", "P"],
  "settings.devices": ["S", "M", "P"],
  "settings.payments": ["S", "M", "P"],
  "settings.subscription_billing": ["S", "M", "P"],
  "settings.tax": ["M", "P"],
  "settings.promo_default": ["M", "P"],
  "settings.staff_basic": ["M", "P"],
  "settings.branches_manage": ["M", "P"],
  "settings.coa": ["P"],
  "settings.notifications": ["M", "P"],
  "settings.roles_permissions": ["P"],
  "settings.multi_branch": ["P"],
  "settings.inventory_method_basic": ["S", "M", "P"],
  "settings.inventory_valuation": ["P"],
  "settings.observability": ["P"],
  "settings.integrations": ["P"]
}
```

Catatan penting:

- `P` di sini tetap dapat diposisikan secara bisnis sebagai paket top-tier setara `L` bila nanti nama komersial ingin diubah.
- Saya sengaja tidak menurunkan `balance_sheet`, `cash_flow`, `lock_period`, `valuation`, `audit_log`, dan `COA` ke S/M agar premium tetap punya pembeda yang kuat.
- Saya justru memperkuat S lewat visibilitas usaha dasar, bukan lewat fitur akuntansi yang terlalu teknis.

## 12. Prioritas Implementasi Arsitektur

Urutan kerja yang paling rasional bila mau menerapkan hasil audit ini:

### Tahap 1: rapikan bahasa produk di config

- Tambahkan key baru yang dibutuhkan: `finance.summary_basic`, `finance.bank_accounts`, `finance.inventory_value_basic`, `history.purchases`, `settings.inventory_method_basic`.
- Tunda `finance.expense_tracking` dan `finance.money_movement` sampai ada page atau API target yang benar-benar terpisah.
- Ubah `inventory.transfer` dari `S/M/P` menjadi `M/P`.
- Pertahankan `product.materials`, `product.recipe_management`, dan `inventory.production_batches` di `M/P`.

### Tahap 2: rapikan enforcement runtime

- Ganti route web yang masih `dash.overview` menjadi gate domain spesifik.
- Tambahkan `feature_gate` pada endpoint API sensitif.
- Pastikan menu hide selalu ditemani hard guard di controller/service.

### Tahap 3: rapikan penyajian paket di UX

- Dashboard dan menu harus adaptif terhadap paket.
- Paket S harus tetap terlihat sederhana, bukan terasa dipotong-potong secara acak.
- Paket M harus memunculkan value upgrade yang jelas.
- Paket P harus fokus pada governance dan analitik detail.

## 13. Rekomendasi Praktis untuk Minggu Ini

### Perubahan yang sebaiknya dikerjakan minggu ini

1. Tambahkan key baru di config untuk `finance.summary_basic`, `finance.bank_accounts`, `finance.inventory_value_basic`, `history.purchases`, dan `settings.inventory_method_basic`.
2. Tunda `finance.expense_tracking` dan `finance.money_movement` sampai ada target runtime khusus.
3. Rapikan gate web/API untuk area `finance-summary`, `bank-accounts`, `inventory-method`, `customers`, `suppliers`, `loyalty`, `users`, `branches`, `purchase-return`, dan `lock-periods`.
4. Turunkan ketergantungan pada `dash.overview` sebagai gate serbaguna.
5. Putuskan apakah `planMapping` akan dihapus atau dibuat otomatis dari `featureFlags`.

### Perubahan yang bisa ditunda

1. Redesign naming paket komersial dari `P` menjadi `L` di UI publik.
2. Perapian copywriting halaman paket/langganan.
3. Penyempurnaan modul biaya operasional menjadi CRUD penuh bila saat ini masih terbatas di finance summary.
4. Penyempurnaan cash flow formal dan laporan akuntansi lanjutan.

### Risiko jika tidak diubah

1. Paket S akan terasa kurang meyakinkan untuk owner UMKM karena tidak jelas memberi jawaban atas uang usaha, biaya, dan nilai stok.
2. Paket M dan P akan sulit dijelaskan karena sebagian pembeda masih generik atau bocor di runtime.
3. Tim akan makin sulit memelihara gating karena config dan enforcement tidak sinkron.
4. Risiko dispute produk akan naik: menu tersembunyi, tetapi endpoint masih terbuka, atau sebaliknya config bilang ada, runtime ternyata tidak konsisten.

## Penutup

Secara arah bisnis, fondasi paket sekarang sudah cukup baik. Perubahan paling penting bukan memperbanyak fitur premium, tetapi memastikan Paket S benar-benar kuat untuk owner UMKM. Setelah itu, Paket M dan P akan lebih mudah dijual karena naik kelasnya terasa masuk akal.

Kalau diringkas dalam satu kalimat: jadikan Paket S sebagai paket untuk menjalankan usaha dan memahami kondisi usaha, Paket M untuk merapikan dan memperluas operasi, dan Paket P untuk kontrol mendalam, governance, dan analitik lanjutan.