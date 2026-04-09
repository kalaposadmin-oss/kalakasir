# APP-COMPILE-BLOCKER-001 - Linear Template

Title:
APP-COMPILE-BLOCKER-001: Resolve app module unresolved references blocking FIFO runtime verification

Problem:
App module compile gagal karena unresolved references lintas file, sehingga verifikasi runtime parity FIFO tidak dapat dijalankan.

Impact:
- assembleDebug gagal
- FIFO bucket belum bisa FULL
- Verifikasi E2E checkout + void tertunda

Scope:
- Diagnose root cause pada MainActivity.kt dan file terkait
- Terapkan patch minimal untuk restore compile
- Re-run validation agar FIFO tests tidak regresi

Acceptance Criteria:
- assembleDebug SUCCESS
- Simbol governance/screen/extension kembali ter-resolve
- Tidak ada regresi pada FIFO tests yang sudah PASS

Links:
- Parity Board: docs/apk-parity-test-scenarios-sprint1-2026-03-18.md
- Traceability Matrix: docs/apk-traceability-matrix-2026-03-18.md
- Blocker Doc: docs/app-compile-blocker-001.md

Unblock Criteria:
- App module builds successfully
- FIFO E2E flow (checkout + void) can be executed from UI
- Outbox payload contains fifo_allocations and fifo_reversal_allocations correctly

---

## Update 2026-03-19 - Scope Isolation Before E2E

Decision:
- E2E tidak dijalankan di workspace campur.
- Scope dipisah dulu agar evidence runtime FIFO tidak tercampur noise perubahan lain.

Isolation Action:
- Branch snapshot campur dibuat: `snapshot/mixed-workspace-20260319`.
- Branch parity dari workspace campur dibuat: `parity/e2e-runtime-20260319`.
- Worktree bersih untuk baseline E2E dibuat: `c:/laragon/www/kalakasir-parity-clean` pada branch `parity/e2e-clean-20260319`.

Pre-E2E Gate Check (worktree bersih):
- Branch clean check: PASS (`parity/e2e-clean-20260319`, working tree clean).
- Build web app (`npm run build`): PASS.
- Valuation parity suite:
	- `ValuationAuthorizationTest`: PASS.
	- `ValuationAsyncOperationsTest`: PASS.
	- `ValuationWebSmokeTest`: PASS.
- Write-flow parity suite: FAIL parsial.
	- Root cause dominan: validasi `register_id` required pada checkout belum terpenuhi pada baseline clean branch.
	- Dampak: `CheckoutTransactionDateValidationTest`, `WriteFlowLockPeriodApiEnforcementTest`, dan `WriteFlowTransactionDateHappyPathTest` gagal di path checkout.

Implication:
- Isolasi scope sudah siap untuk E2E kredibel.
- Sebelum E2E checkout/history/void dijalankan, parity checkout payload di branch parity clean harus ditutup dulu (khususnya `register_id` pada request checkout fixture/runtime path).

## Update 2026-03-19 - Pre-E2E Gate Closed

Status:
- Gap checkout parity pada branch clean sudah ditutup.
- Gate pra-E2E untuk build + parity test utama sekarang PASS.

Patch ringkas yang diterapkan di parity clean:
- `tests/Concerns/BuildsCheckoutRequestFixture.php`
	- Menambahkan `register_id` UUID ke fixture checkout payload.
	- Menjaga kompatibilitas schema test lama (tanpa hard insert ke tabel `registers`).
- `app/Services/CheckoutService.php`
	- Menambah fallback berbasis `Schema::hasTable/hasColumn` untuk schema SQLite lama (tanpa `product_variants`, `product_variant_id`, `payment_method_id`, `sales.register_id`).
	- Menyesuaikan dispatch event `SaleCompleted` agar kompatibel dengan signature event saat ini.
- `app/Models/Sale.php`
	- Menambahkan konstanta status `STATUS_COMPLETED` dan `STATUS_VOIDED`.

Validation Evidence (parity/e2e-clean-20260319):
- Build web app (`npm run build`): PASS.
- Valuation parity suite: PASS (13 tests / 93 assertions).
	- `ValuationAuthorizationTest`
	- `ValuationAsyncOperationsTest`
	- `ValuationWebSmokeTest`
- Write-flow parity suite: PASS (19 tests / 42 assertions).
	- `WriteFlowLockPeriodApiEnforcementTest`
	- `WriteFlowTransactionDateHappyPathTest`
	- `CheckoutTransactionDateValidationTest`
	- `PurchaseReceiveTransactionDateValidationTest`
	- `StockTransferTransactionDateValidationTest`
	- `StockAdjustmentTransactionDateValidationTest`
	- `PurchaseReturnConsistencyTest`

Readiness:
- Definisi selesai sebelum E2E sudah terpenuhi untuk branch parity clean:
	- branch clean: PASS
	- build: PASS
	- parity tests utama: PASS
- E2E checklist sempit dapat mulai dieksekusi di workspace bersih:
	- checkout UI
	- history / void UI
	- outbox payload final
	- fifo_allocations
	- fifo_reversal_allocations
	- status UI transaksi

## Update 2026-03-19 - Void PIN Gate Closed

Status:
- Void manager PIN enforcement pada baseline clean sudah PASS.
- Tidak ada regresi pada gate valuation dan write-flow setelah patch terbaru.

Patch ringkas tambahan:
- `tests/Feature/VoidManagerPinEnforcementTest.php`
	- Menyetel aktor transaksi sebagai `cashier`.
	- Menambahkan approver `admin` terpisah dengan `manager_pin_hash`.
	- Menetapkan nominal sale di atas threshold direct-void agar jalur manager PIN tervalidasi deterministik.
	- Menyesuaikan asersi `void_approved_by` ke approver admin.

Validation Evidence tambahan (parity/e2e-clean-20260319):
- `VoidManagerPinEnforcementTest`: PASS (4 tests / 16 assertions).
- Re-run gabungan gate parity inti (file-based deterministic): PASS (36 tests / 151 assertions), mencakup:
	- `ValuationAuthorizationTest`
	- `ValuationAsyncOperationsTest`
	- `ValuationWebSmokeTest`
	- `WriteFlowLockPeriodApiEnforcementTest`
	- `WriteFlowTransactionDateHappyPathTest`
	- `CheckoutTransactionDateValidationTest`
	- `PurchaseReceiveTransactionDateValidationTest`
	- `StockTransferTransactionDateValidationTest`
	- `StockAdjustmentTransactionDateValidationTest`
	- `PurchaseReturnConsistencyTest`
	- `VoidManagerPinEnforcementTest`

Catatan:
- Target `CheckoutShiftParityPilotTest` tidak ditemukan pada baseline clean saat ini (no tests found), sehingga tidak dihitung sebagai blocker gate parity inti.

## Update 2026-03-19 - Narrow E2E Runtime Checklist Executed

Status:
- Empat skenario runtime sempit untuk checkout/void/status/payload sudah dieksekusi otomatis pada branch clean.
- Semua skenario PASS dan konsisten dengan state database.

Implementasi test checklist:
- `tests/Feature/E2ENarrowRuntimeChecklistTest.php`
	- `test_checkout_ui_success_transaction_and_fifo_allocations_are_visible`
	- `test_void_from_history_flow_updates_status_and_marks_fifo_reversal_allocations`
	- `test_status_visible_in_ui_endpoints_is_honest_for_completed_and_voided`
	- `test_final_payload_core_fields_are_complete_and_consistent_with_ui_state`

Ringkasan skenario vs hasil:
- Checkout UI sukses
	- transaksi berhasil: PASS (`sales.status=completed`)
	- alokasi FIFO tercatat: PASS (`inventory_issue_allocations` + endpoint `stock-card.allocations`)
- Void dari history/UI
	- status berubah benar: PASS (`sales.status=voided`, `sales-history?status=voided`)
	- reversal allocation tercermin: PASS (`inventory_issue_allocations.reversed_at` terisi)
- Status transaksi di UI
	- `completed` dan `voided` tampil jujur pada `sales-history`: PASS
	- catatan: status `requested` tidak digunakan pada domain `sales` saat ini (status sales aktif: `completed`, `voided`)
- Payload final
	- field inti lengkap dan selaras dengan state UI/detail transaksi: PASS (`sale detail` + `stock-card.allocations`)

Validation Evidence (parity/e2e-clean-20260319):
- `E2ENarrowRuntimeChecklistTest`: PASS (4 tests / 61 assertions).
- Re-run gabungan gate parity + narrow runtime checklist: PASS (40 tests / 212 assertions).

## Update 2026-03-19 - Final Audit Delta (Backend + Android)

Status ringkas:
- Core parity logic tetap stabil dan gate utama tetap PASS.
- Ditemukan 1 hidden gap recipe runtime pada branch utama, sudah diperbaiki dan tervalidasi.
- Claim "100% fungsi end-to-end" masih belum dapat dinyatakan karena evidence UI E2E device belum tersedia.

Perbaikan hidden gap yang dikerjakan:
- `app/Services/AutoConsumeOnSellService.php`
	- Menambahkan normalisasi `bom_items` agar kompatibel saat data tersimpan sebagai JSON string (bukan hanya array), sehingga validasi dan konsumsi recipe berjalan benar.
- `tests/Concerns/BuildsCheckoutRequestFixture.php`
	- Menambahkan `register_id` ke payload checkout fixture.
	- Menyisipkan row `registers` secara kondisional saat tabel tersedia, agar lolos FK `sales.register_id`.

Validation Evidence tambahan (2026-03-19):
- Backend (main workspace):
	- `AutoConsumeOnSellCheckoutFlowTest`: PASS (11 tests).
	- `ShiftCloseEndpointTest`: PASS (1 test).
- Backend (clean parity workspace):
	- Re-run gate parity + narrow runtime checklist: PASS (40 tests / 212 assertions).
- Android:
	- `:app:assembleDebug`: PASS.
	- `:feature-pos:testDebugUnitTest :app:testDebugUnitTest :core-database:testDebugUnitTest`: PASS.
	- `:core-sync:testDebugUnitTest`: PASS.
	- `:app:connectedDebugAndroidTest`: PASS build task, namun tidak ada test instrumentation yang benar-benar dieksekusi (androidTest source saat ini kosong), sehingga tidak menghasilkan bukti UI E2E device.

Kesimpulan audit final sementara:
- Status dapat dinyatakan "100% logic parity" untuk scope inti yang sudah diuji.
- Status "100% fungsi end-to-end" belum dapat dinyatakan karena kekurangan bukti eksekusi UI E2E Android nyata untuk flow login -> open shift -> checkout -> history -> void -> sync payload di device/emulator dengan assertion state UI dan outbox lokal.

## Final Decision 2026-03-20

Klasifikasi final:
- SUDAH 100% SECARA LOGIC SAJA

Keputusan gate release:
- Gate logic parity: PASS
- Gate full functional end-to-end Android UI: BLOCKED (evidence instrumentation belum ada)

Penutupan blocker APP-COMPILE-BLOCKER-001:
- Compile blocker utama: CLOSED
- Risiko residual rilis: MASIH ADA pada klaim full E2E fungsi karena belum ada bukti androidTest UI flow kritikal.

Prasyarat agar dapat naik ke "SUDAH 100% SECARA FUNGSI END-TO-END":
- Tambahkan test instrumentation Android untuk flow kritikal:
	- login
	- open shift
	- checkout
	- sales history
	- void
	- verifikasi status UI dan outbox payload lokal
- Jalankan di device/emulator dan simpan evidence hasil assertion per langkah.
