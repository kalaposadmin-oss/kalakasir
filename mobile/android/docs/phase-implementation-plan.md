# Phase Implementation Plan

## Fase 1 (MVP)
- Auth/login
- Produk
- Pelanggan
- POS + pembayaran
- Shift
- Laporan harian sederhana
- Basic sync engine (outbox push + pull delta)

## Fase 2
- Supplier
- Pembelian
- Kas masuk/keluar
- Stock opname

## Fase 3
- Hutang piutang
- Laba rugi sederhana
- Neraca sederhana
- Monitoring sinkronisasi

## Definition of Done Fase 1
- Transaksi POS selesai offline tanpa internet.
- Outbox terisi dan bisa dipush saat online.
- Status sync muncul jelas di UI.
- Tidak ada duplikasi transaksi saat retry.
- Tes unit domain utama lulus.

## Update Progress 2026-03-17
1. Verifikasi build dan test distabilkan dengan eksekusi non-interaktif Gradle agar tidak terganggu prompt batch job.
2. Hardening retur kumulatif ditambah untuk skenario staged return lintas produk sampai status returned_full.
3. Validasi qty desimal retur dibuat toleran floating-point agar 0.1 + 0.2 tidak false positive terhadap batas qty pembelian.
4. Retry payload retur identik dibuat idempotent di repository dan outbox dengan idempotency key deterministik.
5. Kontrak skema Room untuk versi 6 diverifikasi lewat schema contract test pada file JSON Room schema.
6. Regresi utama berhasil: core-database test, app unit test, dan assemble debug lulus.

## Next-Step Execution 2026-03-17
1. Integration-style test untuk alur purchase penuh ditambahkan (finalize -> receive -> staged return -> returned_full).
2. Guard idempotency policy berbasis time window diterapkan untuk retry payload retur identik.
3. Event observability lokal ditambahkan pada repository untuk metrik retur (`return_created`, `return_deduplicated`, `return_overflow_blocked`).
4. Kontrak payload outbox purchase return ditutup dengan snapshot test.
5. Cakupan migrasi diperluas via schema contract v5 -> v6 (penambahan tabel `purchase_return_items` sambil menjaga tabel inti purchase).
6. Regresi suite dijalankan ulang termasuk task backend `tests: write-flow`.
7. Checklist readiness rilis Android dibuat di `docs/release-readiness-checklist.md`.
