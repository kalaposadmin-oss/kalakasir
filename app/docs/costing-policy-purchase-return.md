# Costing Policy — Purchase Return

## Policy Decision (Current Implementation)
- Purchase return mengurangi stok berdasarkan **current valuation cost** saat transaksi return diposting.
- Nilai dokumen return (qty * price input) diposting ke payable.
- Selisih antara nilai dokumen vs nilai inventory diposting ke akun COGS sebagai variance.

## Accounting Impact
- Inventory value mengikuti costing engine aktif (FIFO/Average) saat itu.
- Margin period bisa terpengaruh oleh variance COGS.

## Why This Policy
- Menjaga konsistensi nilai inventory dengan cost state aktual.
- Menghindari rewrite layer historis purchase yang sudah terpakai transaksi lain.

## Governance Requirement
- Laporan harus menampilkan komponen variance secara eksplisit.
- Tim finance harus menyetujui treatment COGS variance.
- Kebijakan ini harus konsisten lintas tenant dan didokumentasikan di SOP finance.

## Alternative Policy (Not Active)
- Return by historical purchase layer/value.
- Cocok jika kebijakan akuntansi mewajibkan reversal ke nilai historis purchase.
- Membutuhkan model linking lebih ketat antar purchase item dan return allocation.
