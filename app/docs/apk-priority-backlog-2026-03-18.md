# Backlog Prioritas APK (Rule Parity First)

Tanggal: 2026-03-18
Status: Draft eksekusi sprint
Scope: APK Android offline-first parity terhadap rule backend Laravel

## 1. Prinsip Eksekusi
- Prioritas utama: parity rule modul kritikal, bukan ekspansi fitur baru.
- Fitur dianggap selesai hanya jika parity rule tervalidasi terhadap kontrak web.
- Logic kritikal tidak boleh bertambah di MainActivity.

## 2. Prioritas P1 / P2 / P3

### P1 (Wajib, Risiko Tertinggi)
- Shift context parity
- Checkout context parity
- Approval parity (nominal + role matrix)
- Lock period parity (policy snapshot versioned)
- Feature gating parity (backend-driven)

### P2 (Sangat Penting, Operasional)
- Resep / auto-consume parity
- Purchase parity
- Purchase return parity
- Product mode / varian / BOM parity

### P3 (Kejelasan UX + Stabilitas Jangka Menengah)
- Finance display clarity (estimasi vs final)
- Refactor arsitektur modul
- Room migration eksplisit (mengurangi risiko destructive fallback)

## 3. Sprint Plan

## Sprint 1
Fokus: Shift + Checkout + Approval

Deliverables:
- ShiftGuardUseCase
- ValidateCheckoutContextUseCase
- ApprovalPolicyEngine
- Ekstraksi rule kritikal keluar dari MainActivity

Task teknis:
- Buat feature-shift/usecase/ShiftGuardUseCase.
- Terapkan guard ke semua write flow sensitif (checkout, void request, purchase action trigger).
- Buat feature-pos/validator/CheckoutContextValidator.
- Buat feature-governance/policy/ApprovalPolicyEngine.
- Definisikan threshold nominal dan role matrix untuk operasi sensitif (minimal void).
- Tambah unit test untuk guard dan validator.

Acceptance criteria:
- Checkout diblokir lokal jika shift context tidak valid (aktif, ownership, branch, register).
- Approval decision untuk operasi sensitif ditentukan oleh engine tunggal, bukan if tersebar.
- Semua pre-check utama tidak berada di MainActivity.
- Unit test untuk ShiftGuardUseCase, CheckoutContextValidator, ApprovalPolicyEngine lulus.

## Sprint 2
Fokus: Lock Period + Feature Gating

Deliverables:
- PolicyRepository
- LockPeriodPolicySnapshot
- FeatureAccessEvaluator

Task teknis:
- Buat core-policy repository untuk snapshot policy backend.
- Simpan snapshot dengan metadata: version, updated_at, effective_from, effective_until, stale.
- Buat evaluator access berbasis snapshot backend, bukan enum lokal murni.
- Integrasikan evaluator ke halaman/aksi sensitif.
- Tambah fallback behavior saat snapshot stale (misal read-only untuk aksi berisiko tinggi).

Acceptance criteria:
- Flag lock period bukan hanya boolean lokal tanpa metadata.
- Feature access untuk modul sensitif memakai evaluator tunggal.
- Saat snapshot stale, perilaku aplikasi terdefinisi jelas dan teruji.
- Unit test policy repository + evaluator lulus.

## Sprint 3
Fokus: Resep + Purchase + Purchase Return

Deliverables:
- RecipePolicyValidator
- PurchasePolicyUseCase
- ReturnParityGuard

Task teknis:
- Buat validator resep: output finished_good, input raw_material, minus stock policy.
- Buat use case purchase parity: cash method, due date, status transition, finalisasi.
- Perkuat purchase return parity: supplier match, qty guard, status accounting pending.
- Pertahankan dedupe fingerprint return yang sudah bagus.

Acceptance criteria:
- Rule resep di APK konsisten dengan rule backend untuk kasus valid/invalid utama.
- Purchase invalid ditolak di pre-check APK sesuai kontrak web.
- Return dengan data tidak konsisten diblokir sebelum enqueue.
- Unit test validator/use case parity lulus.

## Sprint 4
Fokus: Product Mode + Finance Clarity + Migration

Deliverables:
- ProductModeValidator
- Label estimasi finance
- Rencana migrasi Room eksplisit

Task teknis:
- Validasi product mode (default/variant/bom/bundle) setara web.
- Tambah indikator finance di APK: estimasi, belum sinkron, last synced at.
- Kurangi ketergantungan fallbackToDestructiveMigration dengan migration plan eksplisit.

Acceptance criteria:
- Draft produk invalid tidak lolos parity validator.
- Semua angka finance yang belum final memiliki label status.
- Dokumen dan implementasi migration Room eksplisit tersedia.

## Sprint 5
Fokus: Refactor Besar APK

Deliverables:
- Pemecahan orchestration per domain
- Coordinator per feature
- Test parity lintas modul

Task teknis:
- Pisah orchestration dari MainActivity ke feature coordinators.
- Standarkan struktur feature module (ui, viewmodel, usecase, validator, mapper).
- Tambah suite parity test lintas modul prioritas.

Acceptance criteria:
- MainActivity tidak mengandung penambahan logic rule kritikal.
- Rule engine dan validator reusable antar feature.
- Parity test lintas modul berjalan stabil.

## 4. Backlog Modul (Task + Acceptance Criteria)

### 4.1 Shift (P1)
Task:
- ShiftContextValidator
- ShiftGuardUseCase
- Integrasi ke flow checkout dan write flow lain

Acceptance criteria:
- Validasi aktif shift.
- Validasi ownership user.
- Validasi branch match.
- Validasi register match.
- Semua write flow melewati ShiftGuardUseCase.

### 4.2 Checkout (P1)
Task:
- ValidateCheckoutContextUseCase
- CheckoutContextValidator
- Mapper payload checkout yang konsisten dengan kontrak web

Acceptance criteria:
- Pre-check shift, branch, register, actor, write lock berjalan sebelum finalize lokal.
- Error reason user-facing jelas saat blokir rule.

### 4.3 Approval (P1)
Task:
- ApprovalPolicyEngine
- Definisi role matrix
- Definisi threshold nominal
- Integrasi ke void dan operasi sensitif

Acceptance criteria:
- Approval decision selalu dari engine.
- Tidak ada if approval tersebar di UI layer.
- Test skenario nominal kecil/menengah/besar tersedia.

### 4.4 Lock Period (P1)
Task:
- LockPeriodPolicySnapshot model
- Sinkronisasi snapshot policy
- Guard stale snapshot

Acceptance criteria:
- Snapshot menyimpan version/updated/effective/stale.
- Aksi sensitif mengikuti snapshot, bukan flag tanpa konteks.

### 4.5 Feature Gating (P1)
Task:
- FeatureAccessEvaluator
- Policy source backend-driven
- Penyelarasan key feature dengan backend

Acceptance criteria:
- Keputusan akses fitur berasal dari evaluator tunggal.
- Tidak ada hardcoded enum lokal sebagai sumber kebenaran utama.

### 4.6 Resep / Auto-consume (P2)
Task:
- RecipePolicyValidator
- Konsolidasi formula consume

Acceptance criteria:
- Rule output/input resep selaras backend.
- Minus stock policy diterapkan konsisten.

### 4.7 Purchase (P2)
Task:
- PurchasePolicyUseCase
- Guard cash method + due date + status transition

Acceptance criteria:
- Purchase invalid diblokir di pre-check sesuai kontrak web.

### 4.8 Purchase Return (P2)
Task:
- ReturnParityGuard
- Penegasan status pending accounting

Acceptance criteria:
- Supplier mismatch dan qty overflow diblokir.
- Status akuntansi pending terlihat jelas di UI.

### 4.9 Product Mode / Varian / BOM (P2)
Task:
- ProductModeValidator
- Validator struktur varian, bom, bundle

Acceptance criteria:
- Input mode invalid tidak lolos.
- Rule parity utama terhadap ProductController tercapai.

### 4.10 Finance Clarity (P3)
Task:
- Label status angka: estimasi/final/stale
- Last synced timestamp untuk widget finance

Acceptance criteria:
- Tidak ada angka finance ambigu yang terlihat final padahal estimasi.

## 5. Arsitektur Target APK

Struktur minimum:
- feature-pos
  - ui
  - viewmodel
  - usecase
  - validator
  - mapper
- feature-shift
- feature-governance
- feature-purchase
- feature-recipe
- feature-product-admin
- feature-settings
- feature-finance

Komponen inti:
- PolicyEngine
  - evaluateRole()
  - evaluateApproval()
  - evaluateLockPeriod()
  - evaluateFeatureAccess()
- Validators
  - CheckoutContextValidator
  - ShiftContextValidator
  - RecipePolicyValidator
  - ProductModeValidator
  - PurchasePolicyValidator
- Mappers
  - LocalSaleDraftToCommandMapper
  - LocalPurchaseDraftToCommandMapper
  - LocalVoidRequestToCommandMapper

## 6. Definition of Done (Wajib)
Satu fitur/modul APK dinyatakan selesai hanya jika:
- UI ada.
- Flow lokal berjalan.
- Outbox/action terbentuk benar.
- Business rule parity dengan web diverifikasi.
- Status pending/final jelas ke user.
- Unit test validator/use case tersedia dan lulus.
- Tidak ada logic kritikal baru di MainActivity.

## 7. Risiko Jika Backlog Ini Tidak Diikuti
- Checkout offline terlihat sukses, server menolak.
- Void dianggap final padahal baru request.
- Shift lokal valid semu, server invalid.
- Fitur premium tampil pada tenant yang tidak berhak.
- Lock period ter-bypass semu.
- Angka finance disalahpahami sebagai final.

## 8. Daftar Referensi Kode (Grounding)
- Web:
  - app/app/Services/CheckoutService.php
  - app/app/Services/TransactionAuthorizationPolicyService.php
  - app/app/Services/LockPeriodService.php
  - app/app/Services/AutoConsumeOnSellService.php
  - app/app/Http/Controllers/Api/ProductController.php
  - app/app/Http/Controllers/Api/PurchaseController.php
  - app/app/Http/Controllers/Api/PurchaseReturnController.php
  - app/app/Services/VoidService.php
  - app/app/Services/SubscriptionService.php
  - app/config/feature-gating.json
- APK:
  - mobile-android-umkm-offline/app/src/main/java/com/umkm/offline/app/MainActivity.kt
  - mobile-android-umkm-offline/app/src/main/java/com/umkm/offline/app/SubscriptionPlan.kt
  - mobile-android-umkm-offline/app/src/main/java/com/umkm/offline/app/AppGovernance.kt
  - mobile-android-umkm-offline/feature-pos/src/main/java/com/umkm/offline/feature/pos/data/PosRepositoryImpl.kt
  - mobile-android-umkm-offline/app/src/main/java/com/umkm/offline/app/purchase/PurchaseRepository.kt
  - mobile-android-umkm-offline/app/src/main/java/com/umkm/offline/app/recipe/RecipeRepository.kt
  - mobile-android-umkm-offline/core-database/src/main/java/com/umkm/offline/core/database/OfflineDatabase.kt

## 9. Catatan Verifikasi
- Dokumen ini sengaja tidak memfokuskan endpoint sinkronisasi detail, sesuai arahan.
- Jika ada kontrak rule yang belum terwakili penuh di APK, tandai sebagai perlu verifikasi pada task sprint terkait.