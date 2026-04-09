# APK Priority Issue Breakdown (Parity-Driven, Ready to Assign)

Tanggal: 2026-03-18
Tujuan: memecah backlog parity menjadi issue eksekusi yang memaksa kesamaan rule Web dan APK
Referensi utama: docs/apk-priority-backlog-2026-03-18.md
Referensi scenario parity Sprint 1: docs/apk-parity-test-scenarios-sprint1-2026-03-18.md

## Cara Pakai
- Satu issue = satu outcome teknis yang bisa diuji.
- Label minimum: apk, parity, sprint-x, priority-p1|p2|p3.
- Setiap issue wajib menyebut:
  - ruang lingkup file target
  - acceptance criteria
  - test yang harus ada
  - risiko jika tidak selesai

## Parity Gate (Mandatory)
Issue TIDAK BOLEH ditutup tanpa checklist ini:
1. Sebutkan rule Web acuan.
   - file
   - function/service
   - ringkasan rule
2. Bandingkan implementasi APK terhadap rule Web.
   - sama / beda
   - jika beda, jelaskan gap
3. Simulasikan minimal 1 input yang sama di Web dan APK.
   - input A -> hasil Web
   - input A -> hasil APK
   - hasil sama/tidak
4. Jika belum 100 persen sama:
   - jelaskan alasan
   - tandai status sebagai PARITY PARTIAL

Status parity wajib per issue:
- NOT STARTED
- PARTIAL
- FULL

---

## Sprint 1 (P1): Shift + Checkout + Approval

### ISSUE S1-01 - Implement ShiftGuardUseCase
- Priority: P1
- Owner suggestion: Android Core/Domain
- Objective: semua write flow melewati validasi shift context.
- Scope file:
  - mobile-android-umkm-offline/app/src/main/java/com/umkm/offline/app/feature-shift/usecase/ShiftGuardUseCase.kt
  - mobile-android-umkm-offline/app/src/main/java/com/umkm/offline/app/feature-shift/validator/ShiftContextValidator.kt
  - mobile-android-umkm-offline/app/src/main/java/com/umkm/offline/app/MainActivity.kt
- Acceptance criteria:
  - Validasi shift aktif.
  - Validasi ownership user shift.
  - Validasi branch match.
  - Validasi register match.
  - Semua write flow sensitif memanggil guard.
- Required tests:
  - Unit test: ShiftGuardUseCaseTest (aktif/nonaktif, ownership mismatch, branch/register mismatch).
  - Parity test: input shift tidak valid harus ditolak sama seperti Web.
- Risk if incomplete:
  - Offline transaksi lolos lokal namun ditolak server.
- WEB RULE REFERENCE:
  - File: app/app/Services/CheckoutService.php
  - Service/Function: CheckoutService::assertShift
  - Rule: shift harus open, milik user actor, branch harus cocok.
- PARITY STATUS:
  - NOT STARTED
- PARITY TEST CASE:
  - Case 1: shift open + ownership benar + branch/register benar -> Web success, APK success.
  - Case 2: shift milik user lain -> Web reject, APK reject.
  - Edge case: branch mismatch -> Web reject, APK reject.

### ISSUE S1-02 - Implement ValidateCheckoutContextUseCase
- Priority: P1
- Owner suggestion: POS Domain
- Objective: pre-check checkout APK setara kontrak Web minimum.
- Scope file:
  - mobile-android-umkm-offline/feature-pos/src/main/java/com/umkm/offline/feature/pos/usecase/ValidateCheckoutContextUseCase.kt
  - mobile-android-umkm-offline/feature-pos/src/main/java/com/umkm/offline/feature/pos/validator/CheckoutContextValidator.kt
  - mobile-android-umkm-offline/feature-pos/src/main/java/com/umkm/offline/feature/pos/presentation/PosViewModel.kt
- Acceptance criteria:
  - Pre-check minimal: shift, branch, register, actor, write lock.
  - Error reason user-facing jelas.
- Required tests:
  - Unit test: CheckoutContextValidatorTest.
  - Integration test: checkout flow memakai validator sebelum finalize.
  - Parity test: payload checkout valid/invalid menghasilkan keputusan sama dengan Web.
- WEB RULE REFERENCE:
  - File: app/app/Http/Controllers/Api/CheckoutController.php; app/app/Services/CheckoutService.php
  - Service/Function: CheckoutController::store, CheckoutService::handle
  - Rule: checkout wajib kontrak request server dan lolos validasi shift/totals/lock.
- PARITY STATUS:
  - NOT STARTED
- PARITY TEST CASE:
  - Case 1: payload valid lengkap -> Web success, APK success.
  - Case 2: actor/shift invalid -> Web reject, APK reject.
  - Edge case: write lock aktif -> Web reject, APK reject.

### ISSUE S1-03 - Build ApprovalPolicyEngine
- Priority: P1
- Owner suggestion: Governance Domain
- Objective: sentralisasi rule approval nominal + role matrix.
- Scope file:
  - mobile-android-umkm-offline/app/src/main/java/com/umkm/offline/app/feature-governance/policy/ApprovalPolicyEngine.kt
  - mobile-android-umkm-offline/app/src/main/java/com/umkm/offline/app/AppGovernance.kt
  - mobile-android-umkm-offline/app/src/main/java/com/umkm/offline/app/MainActivity.kt
- Acceptance criteria:
  - Operasi sensitif diputuskan engine tunggal.
  - Threshold nominal terdefinisi.
  - Role matrix terdefinisi.
- Required tests:
  - Unit test: ApprovalPolicyEngineTest (role matrix + nominal boundary).
  - Parity test: void nominal kecil/menengah/besar sama keputusan dengan Web.
- WEB RULE REFERENCE:
  - File: app/app/Services/TransactionAuthorizationPolicyService.php
  - Service/Function: TransactionAuthorizationPolicyService::evaluateVoid
  - Rule: threshold nominal menentukan direct_allowed dan allowed approver roles.
- PARITY STATUS:
  - NOT STARTED
- PARITY TEST CASE:
  - Case 1: nominal kecil role kasir -> Web rule X, APK harus X.
  - Case 2: nominal menengah role kasir -> Web reject direct, APK reject direct.
  - Edge case: nominal besar non-owner -> Web reject direct, APK reject direct.

### ISSUE S1-04 - Remove Critical Rule Drift in MainActivity
- Priority: P1
- Owner suggestion: App Architecture
- Objective: menurunkan logic rule kritikal dari MainActivity.
- Scope file:
  - mobile-android-umkm-offline/app/src/main/java/com/umkm/offline/app/MainActivity.kt
- Acceptance criteria:
  - Rule shift/checkout/approval dipanggil via use case/engine.
  - Tidak ada if kritikal baru ditambah di MainActivity.
- Required tests:
  - Regression test: flow utama tetap lulus.
  - Parity test: keputusan rule tetap sama setelah refactor.
- WEB RULE REFERENCE:
  - File: app/app/Services/CheckoutService.php; app/app/Services/TransactionAuthorizationPolicyService.php
  - Service/Function: assertShift, evaluateVoid
  - Rule: keputusan rule bersumber dari policy domain, bukan UI.
- PARITY STATUS:
  - NOT STARTED
- PARITY TEST CASE:
  - Case 1: skenario checkout valid/invalid sebelum vs sesudah refactor hasil sama.
  - Case 2: skenario approval nominal sebelum vs sesudah refactor hasil sama.
  - Edge case: branch mismatch tetap terblokir.

---

## Sprint 2 (P1): Lock Period + Feature Gating

### ISSUE S2-01 - Add PolicyRepository + LockPeriodPolicySnapshot
- Priority: P1
- Owner suggestion: Core Data/Policy
- Objective: lock period berbasis snapshot versioned, bukan flag tunggal.
- Scope file:
  - mobile-android-umkm-offline/core-database/src/main/java/com/umkm/offline/core/database/entity/PolicySnapshotEntity.kt
  - mobile-android-umkm-offline/app/src/main/java/com/umkm/offline/app/feature-governance/repository/PolicyRepository.kt
  - mobile-android-umkm-offline/app/src/main/java/com/umkm/offline/app/feature-governance/model/LockPeriodPolicySnapshot.kt
- Acceptance criteria:
  - Snapshot menyimpan version, updated_at, effective_from, effective_until, stale.
  - Guard perilaku saat snapshot stale terdefinisi.
- Required tests:
  - Unit test: PolicyRepositoryTest, LockPeriodPolicySnapshotTest.
  - Parity test: skenario periode terkunci menghasilkan keputusan sama dengan Web.
- WEB RULE REFERENCE:
  - File: app/app/Services/LockPeriodService.php
  - Service/Function: LockPeriodService::ensureNotLocked
  - Rule: transaksi dalam rentang lock aktif harus ditolak.
- PARITY STATUS:
  - NOT STARTED
- PARITY TEST CASE:
  - Case 1: tanggal dalam lock range -> Web reject, APK reject.
  - Case 2: tanggal di luar lock range -> Web success, APK success.
  - Edge case: snapshot stale -> APK masuk mode protektif terdefinisi.

### ISSUE S2-02 - Implement FeatureAccessEvaluator (Backend-Driven)
- Priority: P1
- Owner suggestion: Governance + Product
- Objective: akses fitur berdasarkan snapshot backend, bukan enum lokal murni.
- Scope file:
  - mobile-android-umkm-offline/app/src/main/java/com/umkm/offline/app/feature-governance/policy/FeatureAccessEvaluator.kt
  - mobile-android-umkm-offline/app/src/main/java/com/umkm/offline/app/SubscriptionPlan.kt
  - mobile-android-umkm-offline/app/src/main/java/com/umkm/offline/app/MainActivity.kt
- Acceptance criteria:
  - Evaluasi akses fitur sensitif via evaluator tunggal.
  - Fallback behavior saat snapshot missing/stale terdefinisi.
- Required tests:
  - Unit test: FeatureAccessEvaluatorTest.
  - Parity test: akses fitur per plan mengikuti rule backend.
- WEB RULE REFERENCE:
  - File: app/config/feature-gating.json; app/app/Services/SubscriptionService.php; app/app/Http/Middleware/WebAccessMiddleware.php
  - Service/Function: SubscriptionService::canAccessFeature, WebAccessMiddleware::handle
  - Rule: akses fitur mengikuti entitlement plan dan status subscription.
- PARITY STATUS:
  - NOT STARTED
- PARITY TEST CASE:
  - Case 1: plan tidak memenuhi entitlement -> Web deny, APK deny.
  - Case 2: plan memenuhi entitlement -> Web allow, APK allow.
  - Edge case: subscription suspended -> Web deny, APK deny.

---

## Sprint 3 (P2): Recipe + Purchase + Purchase Return

### ISSUE S3-01 - Build RecipePolicyValidator
- Priority: P2
- Owner suggestion: Inventory/Recipe Domain
- Objective: parity rule resep dengan backend.
- Scope file:
  - mobile-android-umkm-offline/app/src/main/java/com/umkm/offline/app/feature-recipe/validator/RecipePolicyValidator.kt
  - mobile-android-umkm-offline/app/src/main/java/com/umkm/offline/app/recipe/RecipeRepository.kt
  - mobile-android-umkm-offline/feature-pos/src/main/java/com/umkm/offline/feature/pos/data/PosRepositoryImpl.kt
- Acceptance criteria:
  - Validasi output finished_good.
  - Validasi input raw_material.
  - Validasi minus stock policy.
- Required tests:
  - Unit test: RecipePolicyValidatorTest.
  - Parity test: skenario invalid recipe sama-sama ditolak.
- WEB RULE REFERENCE:
  - File: app/app/Services/AutoConsumeOnSellService.php
  - Service/Function: AutoConsumeOnSellService::consumeForSaleItem
  - Rule: output recipe finished_good, input raw_material, policy minus stock tenant.
- PARITY STATUS:
  - NOT STARTED
- PARITY TEST CASE:
  - Case 1: recipe valid -> Web success, APK success.
  - Case 2: output bukan finished_good -> Web reject, APK reject.
  - Edge case: stok bahan tidak cukup dan minus=false -> Web reject, APK reject.

### ISSUE S3-02 - Implement PurchasePolicyUseCase
- Priority: P2
- Owner suggestion: Purchase Domain
- Objective: parity purchase rule pre-check.
- Scope file:
  - mobile-android-umkm-offline/app/src/main/java/com/umkm/offline/app/purchase/PurchasePolicyUseCase.kt
  - mobile-android-umkm-offline/app/src/main/java/com/umkm/offline/app/purchase/PurchaseRepository.kt
  - mobile-android-umkm-offline/app/src/main/java/com/umkm/offline/app/purchase/PurchaseViewModel.kt
- Acceptance criteria:
  - Cash method guard.
  - Due date guard.
  - Status transition guard.
- Required tests:
  - Unit test: PurchasePolicyUseCaseTest.
  - Parity test: skenario cash/tempo dan transition mengikuti Web.
- WEB RULE REFERENCE:
  - File: app/app/Http/Controllers/Api/PurchaseController.php
  - Service/Function: PurchaseController::store
  - Rule: validasi payment method, due date, status dan constraint operasional.
- PARITY STATUS:
  - NOT STARTED
- PARITY TEST CASE:
  - Case 1: method tunai dengan saldo cukup -> Web success, APK success.
  - Case 2: method tunai dengan saldo tidak cukup -> Web reject, APK reject.
  - Edge case: transition status invalid -> Web reject, APK reject.

### ISSUE S3-03 - Add PurchaseReturnParityGuard
- Priority: P2
- Owner suggestion: Purchase Domain
- Objective: parity guard untuk return selain dedupe.
- Scope file:
  - mobile-android-umkm-offline/app/src/main/java/com/umkm/offline/app/purchase/PurchaseReturnParityGuard.kt
  - mobile-android-umkm-offline/app/src/main/java/com/umkm/offline/app/purchase/PurchaseRepository.kt
- Acceptance criteria:
  - Supplier match guard.
  - Qty guard parity.
  - Status pending accounting jelas di output state.
- Required tests:
  - Unit test: PurchaseReturnParityGuardTest.
  - Parity test: supplier mismatch/qty overflow sama-sama ditolak.
- WEB RULE REFERENCE:
  - File: app/app/Http/Controllers/Api/PurchaseReturnController.php
  - Service/Function: PurchaseReturnController::store
  - Rule: supplier harus sama dengan purchase asal, qty return harus valid, stok harus cukup.
- PARITY STATUS:
  - NOT STARTED
- PARITY TEST CASE:
  - Case 1: supplier sesuai + qty valid -> Web success, APK success.
  - Case 2: supplier mismatch -> Web reject, APK reject.
  - Edge case: qty overflow -> Web reject, APK reject.

---

## Sprint 4 (P2/P3): Product Mode + Finance Clarity + Migration

### ISSUE S4-01 - Build ProductModeValidator
- Priority: P2
- Owner suggestion: Product Admin Domain
- Objective: parity rule product mode dengan backend.
- Scope file:
  - mobile-android-umkm-offline/app/src/main/java/com/umkm/offline/app/feature-product-admin/validator/ProductModeValidator.kt
  - mobile-android-umkm-offline/app/src/main/java/com/umkm/offline/app/MainActivity.kt
- Acceptance criteria:
  - Rule validasi mode default/variant/bom/bundle jelas dan teruji.
- Required tests:
  - Unit test: ProductModeValidatorTest.
  - Parity test: rule mode valid/invalid sama keputusan dengan Web.
- WEB RULE REFERENCE:
  - File: app/app/Http/Controllers/Api/ProductController.php
  - Service/Function: ProductController::store
  - Rule: mode product menentukan kewajiban variants/bom/bundle dan validasi struktur.
- PARITY STATUS:
  - NOT STARTED
- PARITY TEST CASE:
  - Case 1: variant tanpa variants -> Web reject, APK reject.
  - Case 2: bom tanpa bom_items -> Web reject, APK reject.
  - Edge case: default mode valid -> Web success, APK success.

### ISSUE S4-02 - Finance Display Clarity
- Priority: P3
- Owner suggestion: Finance UX
- Objective: hindari salah tafsir angka estimasi lokal.
- Scope file:
  - mobile-android-umkm-offline/app/src/main/java/com/umkm/offline/app/AppExtraScreens.kt
  - mobile-android-umkm-offline/app/src/main/java/com/umkm/offline/app/MainActivity.kt
- Acceptance criteria:
  - Label: estimasi, belum sinkron, last synced at.
  - Nilai non-final tidak tampil seolah final.
- Required tests:
  - UI test: badge/status muncul sesuai state.
  - Parity test: skenario data belum sinkron tidak menampilkan status final.
- WEB RULE REFERENCE:
  - File: app/app/Http/Controllers/Api/FinanceReportController.php
  - Service/Function: FinanceReportController::index
  - Rule: data report server adalah acuan final, bukan estimasi lokal.
- PARITY STATUS:
  - NOT STARTED
- PARITY TEST CASE:
  - Case 1: offline lama -> APK menandai estimasi/stale.
  - Case 2: setelah sinkron -> APK menandai updated.
  - Edge case: data parsial tidak dilabel final.

### ISSUE S4-03 - Room Explicit Migration Plan
- Priority: P3
- Owner suggestion: Core Data
- Objective: mengurangi risiko data loss lokal.
- Scope file:
  - mobile-android-umkm-offline/core-database/src/main/java/com/umkm/offline/core/database/OfflineDatabase.kt
  - mobile-android-umkm-offline/app/src/main/java/com/umkm/offline/app/MainActivity.kt
- Acceptance criteria:
  - Migration eksplisit untuk perubahan schema baru.
  - fallbackToDestructiveMigration mulai dihapus bertahap.
- Required tests:
  - Integration test: RoomMigrationTest vN->vN+1.
  - Parity test: data policy/shift/sale lokal tetap utuh setelah upgrade.
- WEB RULE REFERENCE:
  - File: app/database/migrations/*
  - Service/Function: n/a
  - Rule: evolusi schema harus aman terhadap data existing.
- PARITY STATUS:
  - NOT STARTED
- PARITY TEST CASE:
  - Case 1: upgrade schema dengan data existing -> data inti tetap terbaca.
  - Case 2: rollback scenario terkontrol -> data tidak hilang tanpa alasan.
  - Edge case: kolom baru policy snapshot tetap backward compatible.

---

## Sprint 5 (P3): Refactor Besar dan Parity Regression

### ISSUE S5-01 - Split App Orchestration by Domain
- Priority: P3
- Owner suggestion: App Architecture
- Objective: pecah orchestration besar ke coordinator per feature.
- Scope file:
  - mobile-android-umkm-offline/app/src/main/java/com/umkm/offline/app/MainActivity.kt
  - mobile-android-umkm-offline/app/src/main/java/com/umkm/offline/app/coordinator/**
- Acceptance criteria:
  - MainActivity fokus bootstrap/navigation.
  - Domain orchestration pindah ke coordinator feature.
- Required tests:
  - Integration test: flow antar coordinator tetap stabil.
  - Parity test: keputusan rule tidak berubah setelah pemecahan orchestration.
- WEB RULE REFERENCE:
  - File: app/app/Services/CheckoutService.php; app/app/Services/TransactionAuthorizationPolicyService.php
  - Service/Function: rule domain acuan tetap di service backend.
  - Rule: refactor arsitektur APK tidak boleh mengubah keputusan parity.
- PARITY STATUS:
  - NOT STARTED
- PARITY TEST CASE:
  - Case 1: regression checkout parity tetap pass.
  - Case 2: regression approval parity tetap pass.
  - Edge case: lock period parity tetap pass.

### ISSUE S5-02 - Add Parity Test Suite
- Priority: P3
- Owner suggestion: QA Automation
- Objective: lock parity rule lintas modul prioritas.
- Scope file:
  - mobile-android-umkm-offline/app/src/test/java/com/umkm/offline/app/parity/**
- Acceptance criteria:
  - Test suite parity untuk modul P1 dan P2.
  - Gagal test jika rule parity drift.
- Required tests:
  - Parity test suite: shift, checkout, approval, lock period, feature gating, recipe, purchase, return, product mode.
- WEB RULE REFERENCE:
  - File: app/app/Services/* dan app/app/Http/Controllers/Api/* terkait modul parity.
  - Service/Function: rule domain acuan per modul.
  - Rule: hasil keputusan Web dan APK untuk input setara harus sama.
- PARITY STATUS:
  - NOT STARTED
- PARITY TEST CASE:
  - Case 1: input kontrak valid -> Web success, APK success.
  - Case 2: input kontrak invalid -> Web reject, APK reject.
  - Edge case: boundary nominal/lock date/role matrix.

---

## Template Issue (Copy-Paste, Parity-Driven)
Judul:
- [P1/P2/P3][Sprint-X][Module] task short title

Deskripsi:
- Objective:
- Background risk:
- Scope file:
- Out of scope:

WEB RULE REFERENCE:
- File:
- Service/Function:
- Rule summary:

PARITY STATUS:
- NOT STARTED / PARTIAL / FULL

PARITY TEST CASE:
- Case 1:
- Case 2:
- Edge case:

Acceptance criteria:
- [ ]
- [ ]
- [ ]

Required tests:
- [ ] Unit test
- [ ] Integration test jika perlu
- [ ] PARITY TEST mandatory

PARITY CHECK MANDATORY:
- [ ] Rule Web acuan disebutkan jelas
- [ ] Implementasi APK dibandingkan dan gap dijelaskan
- [ ] Simulasi input yang sama Web vs APK dicatat
- [ ] Jika belum sama, status ditandai PARTIAL + alasan

Definition of done check:
- [ ] UI/flow lokal jalan
- [ ] Rule parity diverifikasi
- [ ] Status pending/final jelas
- [ ] Tidak menambah logic kritikal di MainActivity

Dependencies:
- Depends on:
- Blocks:

Owner + ETA:
- Owner:
- Estimate:
