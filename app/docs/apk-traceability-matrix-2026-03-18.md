# APK Traceability Matrix (Parity-Control)

Tanggal: 2026-03-18
Tujuan: memastikan tiap task parity punya jejak implementasi, jenis test, parity status, dan parity test yang eksplisit

Referensi scenario parity Sprint 1:
- docs/apk-parity-test-scenarios-sprint1-2026-03-18.md

## Legenda
- Priority: P1 wajib, P2 sangat penting, P3 stabilisasi
- Parity Status:
  - NOT STARTED
  - PARTIAL
  - FULL

## Matrix Utama

| Task ID | Priority | Task | Web Source | APK File | Test | Test Type | Parity Status | Parity Test |
|---|---|---|---|---|---|---|---|---|
| TM-001 | P1 | ShiftGuardUseCase + ShiftContextValidator | app/app/Services/CheckoutService.php (assertShift) | mobile-android-umkm-offline/feature-pos/src/main/java/com/umkm/offline/feature/pos/usecase/ShiftGuardUseCase.kt; mobile-android-umkm-offline/feature-pos/src/main/java/com/umkm/offline/feature/pos/usecase/ValidateCheckoutContextUseCase.kt | mobile-android-umkm-offline/feature-pos/src/test/java/com/umkm/offline/feature/pos/usecase/ShiftGuardUseCaseTest.kt | Unit + Parity | FULL | input shift ownership mismatch -> Web reject, APK reject |
| TM-002 | P1 | ValidateCheckoutContextUseCase | app/app/Http/Controllers/Api/CheckoutController.php; app/app/Services/CheckoutService.php | mobile-android-umkm-offline/feature-pos/src/main/java/com/umkm/offline/feature/pos/usecase/ValidateCheckoutContextUseCase.kt | mobile-android-umkm-offline/feature-pos/src/test/java/com/umkm/offline/feature/pos/usecase/ValidateCheckoutContextUseCaseTest.kt | Unit + Integration + Parity | FULL | input checkout valid context -> Web allow, APK allow |
| TM-003 | P1 | Void approval parity engine | app/app/Services/TransactionAuthorizationPolicyService.php (evaluateVoid) | mobile-android-umkm-offline/app/src/main/java/com/umkm/offline/app/VoidApprovalPolicy.kt; mobile-android-umkm-offline/app/src/main/java/com/umkm/offline/app/MainActivity.kt | mobile-android-umkm-offline/app/src/test/java/com/umkm/offline/app/VoidApprovalPolicyTest.kt | Unit + Parity | FULL | void nominal boundary per role + requested status parity |
| TM-004 | P1 | LockPeriodPolicySnapshot | app/app/Services/LockPeriodService.php (ensureNotLocked) | mobile-android-umkm-offline/app/src/main/java/com/umkm/offline/app/feature-governance/model/LockPeriodPolicySnapshot.kt; mobile-android-umkm-offline/app/src/main/java/com/umkm/offline/app/feature-governance/repository/PolicyRepository.kt | mobile-android-umkm-offline/app/src/test/java/com/umkm/offline/app/feature_governance/LockPeriodPolicySnapshotTest.kt | Unit + Parity | NOT STARTED | input transaction date dalam rentang lock -> Web reject, APK reject |
| TM-005 | P1 | FeatureAccessEvaluator | app/config/feature-gating.json; app/app/Services/SubscriptionService.php; app/app/Http/Middleware/WebAccessMiddleware.php | mobile-android-umkm-offline/app/src/main/java/com/umkm/offline/app/feature-governance/policy/FeatureAccessEvaluator.kt; mobile-android-umkm-offline/app/src/main/java/com/umkm/offline/app/MainActivity.kt | mobile-android-umkm-offline/app/src/test/java/com/umkm/offline/app/feature_governance/FeatureAccessEvaluatorTest.kt | Unit + Parity | NOT STARTED | entitlement plan per feature key -> allow/deny Web == APK |
| TM-006 | P2 | Recipe auto-consume compatibility | app/app/Services/AutoConsumeOnSellService.php (consumeForSaleItem) | mobile-android-umkm-offline/feature-pos/src/main/java/com/umkm/offline/feature/pos/data/PosRepositoryImpl.kt; mobile-android-umkm-offline/app/src/main/java/com/umkm/offline/app/recipe/RecipeRepository.kt; mobile-android-umkm-offline/core-database/src/main/java/com/umkm/offline/core/database/entity/ProductEntity.kt; mobile-android-umkm-offline/app/src/main/java/com/umkm/offline/app/MainActivity.kt | mobile-android-umkm-offline/feature-pos/src/test/java/com/umkm/offline/feature/pos/data/PosRepositoryImplTest.kt; app/tests/Feature/AutoConsumeOnSellCheckoutFlowTest.php | Unit + Parity | FULL | PARITY-RECIPE-001/002/003/004 FULL: guard berbasis item_type eksplisit dan policy allow_negative_raw_material_stock dari tenant snapshot |
| TM-007 | P2 | PurchasePolicyUseCase | app/app/Http/Controllers/Api/PurchaseController.php (store) | mobile-android-umkm-offline/app/src/main/java/com/umkm/offline/app/purchase/PurchasePolicyUseCase.kt; mobile-android-umkm-offline/app/src/main/java/com/umkm/offline/app/purchase/PurchaseRepository.kt | mobile-android-umkm-offline/app/src/test/java/com/umkm/offline/app/purchase/PurchasePolicyUseCaseTest.kt | Unit + Parity | NOT STARTED | method tunai dengan constraint saldo/validasi status -> hasil Web == APK |
| TM-008 | P2 | PurchaseReturnParityGuard | app/app/Http/Controllers/Api/PurchaseReturnController.php (store) | mobile-android-umkm-offline/app/src/main/java/com/umkm/offline/app/purchase/PurchaseReturnParityGuard.kt; mobile-android-umkm-offline/app/src/main/java/com/umkm/offline/app/purchase/PurchaseRepository.kt | mobile-android-umkm-offline/app/src/test/java/com/umkm/offline/app/purchase/PurchaseReturnParityGuardTest.kt | Unit + Parity | NOT STARTED | supplier mismatch atau qty overflow -> Web reject, APK reject |
| TM-009 | P2 | ProductModeValidator | app/app/Http/Controllers/Api/ProductController.php (store) | mobile-android-umkm-offline/app/src/main/java/com/umkm/offline/app/feature-product-admin/validator/ProductModeValidator.kt; mobile-android-umkm-offline/app/src/main/java/com/umkm/offline/app/MainActivity.kt | mobile-android-umkm-offline/app/src/test/java/com/umkm/offline/app/feature_product_admin/ProductModeValidatorTest.kt | Unit + Parity | NOT STARTED | mode variant/bom/bundle invalid -> Web reject, APK reject |
| TM-010 | P3 | Finance status labeling | app/app/Http/Controllers/Api/FinanceReportController.php (index) | mobile-android-umkm-offline/app/src/main/java/com/umkm/offline/app/AppExtraScreens.kt; mobile-android-umkm-offline/app/src/main/java/com/umkm/offline/app/MainActivity.kt | mobile-android-umkm-offline/app/src/test/java/com/umkm/offline/app/feature_finance/FinanceStatusLabelTest.kt | UI + Parity | NOT STARTED | data belum sinkron harus bertanda estimasi/stale, tidak final |
| TM-011 | P3 | Room explicit migration | app/database/migrations/* | mobile-android-umkm-offline/core-database/src/main/java/com/umkm/offline/core/database/OfflineDatabase.kt; mobile-android-umkm-offline/app/src/main/java/com/umkm/offline/app/MainActivity.kt | mobile-android-umkm-offline/core-database/src/test/java/com/umkm/offline/core/database/RoomMigrationTest.kt | Integration + Parity | NOT STARTED | upgrade schema mempertahankan data inti lokal |
| TM-012 | P3 | Split orchestration from MainActivity | app/app/Services/CheckoutService.php; app/app/Services/TransactionAuthorizationPolicyService.php | mobile-android-umkm-offline/app/src/main/java/com/umkm/offline/app/MainActivity.kt; mobile-android-umkm-offline/app/src/main/java/com/umkm/offline/app/coordinator/** | mobile-android-umkm-offline/app/src/test/java/com/umkm/offline/app/coordinator/CoordinatorIntegrationTest.kt | Integration + Parity Regression | NOT STARTED | keputusan rule sebelum vs sesudah refactor tetap sama |
| TM-013 | P2 | FIFO issue and reversal parity | app/app/Services/InventoryValuationService.php (issueFifo, reverseIssue) | mobile-android-umkm-offline/feature-pos/src/main/java/com/umkm/offline/feature/pos/data/PosRepositoryImpl.kt; mobile-android-umkm-offline/app/src/main/java/com/umkm/offline/app/MainActivity.kt; mobile-android-umkm-offline/app/src/main/java/com/umkm/offline/app/VoidApprovalPolicy.kt | app/tests/Feature/ValuationGapCoverageTest.php; mobile-android-umkm-offline/feature-pos/src/test/java/com/umkm/offline/feature/pos/data/PosRepositoryImplTest.kt; mobile-android-umkm-offline/app/src/test/java/com/umkm/offline/app/VoidApprovalPolicyTest.kt | Feature + Parity | PARTIAL | PARITY-FIFO-001/002/003/004 pass on Web+APK unit scope; Android app compile/build sudah pass (`:app:compileDebugKotlin`, `:app:assembleDebug`), namun bukti E2E UI/history void verification masih pending |

Catatan blocker terpisah:
- docs/app-compile-blocker-001.md

## Parity Gate Checklist Per Task
Task tidak boleh ditutup jika salah satu item ini belum terpenuhi:
- Rule Web acuan telah disebutkan di kolom Web Source.
- Implementasi APK telah dibandingkan terhadap rule Web.
- Parity test telah dijalankan dengan input setara Web dan APK.
- Jika hasil belum identik, status wajib PARTIAL dan gap dicatat.

## Gate Review Per Sprint

### Gate Sprint 1
- TM-001, TM-002, TM-003 minimal status PARTIAL, target FULL.
- Tidak ada penambahan logic rule kritikal di MainActivity.

### Gate Sprint 2
- TM-004, TM-005 minimal status PARTIAL, target FULL.
- Snapshot stale behavior sudah teruji.

### Gate Sprint 3
- TM-006, TM-007, TM-008 minimal status PARTIAL, target FULL.
- Rule operasional parity tervalidasi.

### Gate Sprint 4
- TM-009, TM-010, TM-011 minimal status PARTIAL, target FULL.
- Product parity, finance clarity, migration readiness tercapai.

### Gate Sprint 5
- TM-012 target FULL.
- Parity regression suite stabil.

## Definition of Done (Parity-Control)
Task dianggap done hanya jika:
- Implementasi selesai di APK file target.
- Test wajib berjalan sesuai Test Type.
- Parity test dijalankan dan hasil tercatat.
- Parity Status mencapai FULL, atau PARTIAL dengan alasan formal dan action follow-up.
- Tidak menambah logic kritikal baru di MainActivity.

## Template Update Row Matrix
Gunakan template ini saat update status:
- Task ID:
- Parity Status: NOT STARTED / PARTIAL / FULL
- Web Rule Reference:
- APK Decision Reference:
- Parity Test Input:
- Web Result:
- APK Result:
- Gap Summary:
- Follow-up Action:

## Mapping Task ke Scenario ID
| Task ID | Scenario ID Utama |
|---|---|
| TM-001 | PARITY-SHIFT-001, PARITY-SHIFT-002 |
| TM-002 | PARITY-CHECKOUT-001, PARITY-CHECKOUT-002 |
| TM-003 | PARITY-VOID-001, PARITY-VOID-002, PARITY-VOID-003 |
| TM-004 | PARITY-LOCK-001 |
| TM-006 | PARITY-RECIPE-001, PARITY-RECIPE-002, PARITY-RECIPE-003, PARITY-RECIPE-004 |
| TM-013 | PARITY-FIFO-001, PARITY-FIFO-002, PARITY-FIFO-003, PARITY-FIFO-004 |
