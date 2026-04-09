# Handoff Arsitektur Web + APK (18-03-2026)

## 1. Ringkasan Sistem
- Sistem terdiri dari dua runtime utama:
  - Web backend Laravel sebagai source of truth transaksi, stok, valuation, dan jurnal.
  - APK Android offline-first sebagai klien operasional dengan local DB + outbox sync.
- Multi-tenant enforced melalui tenant resolver dan role middleware.
- Domain utama yang aktif di kode: checkout, shift, auto-consume resep, purchase, purchase return, void sale, laporan finansial ringkas.

## 2. Stack Teknologi
- Web:
  - Laravel 12, PHP, middleware role/tenant, Sanctum stateful API.
  - MySQL/PostgreSQL style SQL transaction dengan lock row dan ledger.
- APK:
  - Kotlin, Jetpack Compose, Room, WorkManager.
  - Retrofit + OkHttp + Moshi.

## 3. Entry Point dan Struktur Modul
- Web entry and routing:
  - bootstrap: app/bootstrap/app.php
  - API route: app/routes/api.php
  - Web route: app/routes/web.php
- APK entry and modules:
  - entry activity: mobile-android-umkm-offline/app/src/main/java/com/umkm/offline/app/MainActivity.kt
  - modules: app, core-common, core-database, core-network, core-sync, feature-pos, feature-auth, feature-home, feature-sync-status

## 4. Alur Bisnis End-to-End
- Checkout Web:
  - Validasi payload/totals/idempotency/shift.
  - Kurangi stok atomic, issue valuation (fifo/average), buat stock_ledger.
  - Simpan sale, sale_items, payments.
  - Posting jurnal (debit kas/bank, kredit penjualan, COGS, kredit inventory).
- Checkout APK:
  - Validasi shift aktif dan lock period di sisi UI/flow.
  - Simpan sale + payment lokal.
  - Auto consume bahan resep lokal.
  - Enqueue outbox action dan trigger immediate sync.
- Shift:
  - Web: open, summary, close, close-summary.
  - APK: open/close shift lokal + outbox OPEN_SHIFT/CLOSE_SHIFT.
- Purchase/Return:
  - Web: create purchase, receive/decision flow, purchase return dengan guard stok/tanggal/approval.
  - APK: finalize purchase lokal, mark received, return dengan dedupe fingerprint.

## 5. Model Data Inti
- Web tabel kunci:
  - products, inventory_balances, stock_ledger
  - sales, sale_items, payments, shifts
  - purchases, purchase_items, purchase_returns, purchase_return_items
  - product_price_tiers, product_variants, payment_methods, refunds
- APK entity kunci:
  - ProductEntity, RecipeItemEntity, SaleEntity, PaymentEntity, ShiftEntity
  - SyncActionEntity (outbox), SyncCursorEntity

## 6. Peta Endpoint Web API (Operasional)
- Daftar endpoint inti (prefix /api/v1):
  - POST checkout
  - POST shifts/open
  - POST shifts/close
  - GET shifts/opening
  - GET shifts/summary
  - GET shifts/close-summary
  - GET sales-history
  - GET finance-summary
  - POST purchases
  - POST purchase-returns
  - POST sales/{sale}/void
  - GET stock-card
  - GET/POST valuation reports + rebuild endpoints (owner/admin)

## 7. Peta Action Sinkronisasi APK
- EntityType (APK):
  - PRODUCT, RECIPE, CUSTOMER, SUPPLIER, SALE, PURCHASE, PAYMENT, STOCK_MOVEMENT, SHIFT, CASH_MOVEMENT, DAILY_REPORT
- SyncActionType (APK):
  - UPSERT_PRODUCT
  - UPSERT_RECIPE
  - UPSERT_CUSTOMER
  - UPSERT_SUPPLIER
  - FINALIZE_SALE
  - FINALIZE_PURCHASE
  - RECORD_PURCHASE_RETURN
  - RECORD_PAYMENT
  - RECORD_STOCK_MOVEMENT
  - OPEN_SHIFT
  - CLOSE_SHIFT
  - UPSERT_CASH_MOVEMENT
  - UPSERT_DAILY_REPORT

## 8. Mapping Web API vs APK Outbox (Per Fitur)
| Fitur | APK ActionType | Payload Kunci APK | Endpoint Web yang Relevan | Catatan |
|---|---|---|---|---|
| Checkout sale | FINALIZE_SALE | sale_local_id, receipt_no, total, shift_local_id | POST /api/v1/checkout | Idempotency ada di web sale.idempotency_key; perlu adapter mapping local_id -> server_id |
| Payment sale | RECORD_PAYMENT | payment_local_id, sale_local_id, method, amount | terserap di POST /api/v1/checkout (payments[]) | Perlu kebijakan apakah payment dipush terpisah atau digabung di checkout |
| Open shift | OPEN_SHIFT | shift_local_id, opened_at, opening_cash | POST /api/v1/shifts/open | Perlu resolver branch_id/register_id saat transform payload |
| Close shift | CLOSE_SHIFT | shift_local_id, closed_at, closing_cash | POST /api/v1/shifts/close | Perlu sinkronisasi expected/system cash behavior |
| Create supplier | UPSERT_SUPPLIER | supplier_local_id, name, source_module, requested_by_role | endpoint supplier create di backend (via purchase flow) | Pastikan endpoint final sinkronisasi tersedia |
| Finalize purchase | FINALIZE_PURCHASE | purchase_local_id, purchase_no, total | POST /api/v1/purchases | Perlu mapping items detail jika endpoint web mewajibkan items penuh |
| Purchase return | RECORD_PURCHASE_RETURN | purchase_local_id, reason, returned_at, items[] | POST /api/v1/purchase-returns | APK sudah dedupe fingerprint, web perlu idempotent guard juga |
| Recipe upsert | UPSERT_RECIPE | product_local_id, bom_items/recipe_items | POST/PUT product recipe (tergantung kontrak backend) | Kontrak endpoint recipe dedicated belum terlihat eksplisit di route utama |
| Stock movement | RECORD_STOCK_MOVEMENT | product_local_id, direction, qty, reason | stock-adjustments/stock-transfers/purchase receive | Butuh mapper reason -> endpoint domain yang tepat |
| Cash movement | UPSERT_CASH_MOVEMENT | finance payload | finance control endpoints | Perlu verifikasi kontrak payload |
| Daily report | UPSERT_DAILY_REPORT | snapshot payload | finance-control-daily/snapshot | Perlu verifikasi kontrak payload |

## 9. Logika Khusus yang Harus Dipahami AI Penerus
- Shift enforcement berlapis:
  - Backend checkout reject jika shift bukan open/mismatch user/branch.
  - APK juga blokir checkout jika shift belum buka.
- Auto-consume resep:
  - Backend hanya untuk output finished_good dan input raw_material.
  - Jika kebijakan tenant allow_negative_raw_material_stock=false, kekurangan stok bahan langsung gagal.
- Void governance:
  - Nominal kecil/menengah/besar butuh level otorisasi berbeda.
  - Void melakukan reverse stok, reverse valuation allocation, dan reversal jurnal.
- Lock period:
  - Write flow sensitif tanggal transaksi dan periode terkunci.

## 10. Temuan Risiko dan Perlu Verifikasi
- Perlu verifikasi 1:
  - Endpoint sinkronisasi server untuk /sync/actions dan /sync/pull tidak terlihat pada route web API utama yang dibaca.
  - Android client mengandalkan endpoint ini dengan fallback path api/v1/sync/* dan sync/*.
- Perlu verifikasi 2:
  - Kontrak payload action outbox APK vs kontrak endpoint web domain (checkout/purchase/return/shift) belum dipastikan satu-satu secara adapter layer.
- Perlu verifikasi 3:
  - MainActivity APK sangat besar dan memuat banyak orchestration; risiko maintainability tinggi.
- Perlu verifikasi 4:
  - Room memakai fallbackToDestructiveMigration; risiko kehilangan data lokal saat perubahan schema.

## 11. Rekomendasi Handoff Eksekusi (Untuk AI Berikutnya)
- Prioritas 1: pastikan jalur sinkronisasi server
  - Telusuri controller/route khusus sync action ingest + pull changes.
  - Jika belum ada, buat adapter endpoint yang mengonversi action outbox ke service domain backend.
- Prioritas 2: kontrak idempotency lintas sisi
  - Samakan kunci idempotency antara APK action dan write endpoint backend.
  - Tambahkan guard idempotent untuk purchase return dan shift close/open.
- Prioritas 3: stabilisasi APK orchestration
  - Pecah orchestration besar di MainActivity ke module-level coordinator/use-case.
- Prioritas 4: hardening operasional
  - Ganti fallbackToDestructiveMigration ke migration yang eksplisit.
  - Tambah smoke test integrasi offline->online replay untuk checkout, shift, purchase return, void.

## Lampiran A: File Referensi Kritis
- Web:
  - app/bootstrap/app.php
  - app/routes/api.php
  - app/routes/web.php
  - app/app/Services/CheckoutService.php
  - app/app/Services/AutoConsumeOnSellService.php
  - app/app/Services/VoidService.php
  - app/app/Services/InventoryValuationService.php
  - app/app/Http/Controllers/Api/CheckoutController.php
  - app/app/Http/Controllers/Api/ShiftController.php
  - app/app/Http/Controllers/Api/PurchaseController.php
  - app/app/Http/Controllers/Api/PurchaseReturnController.php
  - app/app/Http/Controllers/Api/VoidSaleController.php
  - app/app/Http/Controllers/Api/FinanceReportController.php
  - app/app/Http/Middleware/TenantResolver.php
  - app/app/Http/Middleware/RoleMiddleware.php
  - app/app/Support/GovernanceRequestContext.php
- APK:
  - mobile-android-umkm-offline/app/src/main/java/com/umkm/offline/app/MainActivity.kt
  - mobile-android-umkm-offline/feature-pos/src/main/java/com/umkm/offline/feature/pos/data/PosRepositoryImpl.kt
  - mobile-android-umkm-offline/app/src/main/java/com/umkm/offline/app/purchase/PurchaseRepository.kt
  - mobile-android-umkm-offline/app/src/main/java/com/umkm/offline/app/recipe/RecipeRepository.kt
  - mobile-android-umkm-offline/core-sync/src/main/java/com/umkm/offline/core/sync/SyncEngine.kt
  - mobile-android-umkm-offline/core-sync/src/main/java/com/umkm/offline/core/sync/worker/SyncOrchestratorWorker.kt
  - mobile-android-umkm-offline/core-network/src/main/java/com/umkm/offline/core/network/SyncApi.kt
  - mobile-android-umkm-offline/core-network/src/main/java/com/umkm/offline/core/network/RetrofitSyncApi.kt
  - mobile-android-umkm-offline/core-database/src/main/java/com/umkm/offline/core/database/OfflineDatabase.kt
