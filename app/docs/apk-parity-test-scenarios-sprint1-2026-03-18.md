# APK Parity Test Scenarios - Sprint 1

Tanggal: 2026-03-18
Tujuan: memastikan modul P1 benar-benar parity terhadap rule Web (source of truth)
Cakupan: Shift, Checkout, Approval, Lock Period, Recipe auto-consume

## Cara Eksekusi
- Jalankan setiap scenario dengan input yang sama pada:
  - Web rule reference (hasil expected backend)
  - APK flow/use case terkait
- Catat status:
  - FULL: hasil Web dan APK identik
  - PARTIAL: ada deviasi yang terdokumentasi
  - FAIL: hasil berbeda dan belum ada mitigasi

## Template Bukti Eksekusi
- Scenario ID:
- Tanggal uji:
- Penguji:
- Build APK:
- Web branch/commit:
- APK branch/commit:
- Input:
- Hasil Web aktual:
- Hasil APK aktual:
- Parity status: FULL / PARTIAL / FAIL
- Catatan gap:
- Tindak lanjut:

## Status Board Internal (Update 2026-03-19)
| Scenario ID | Status |
|---|---|
| PARITY-SHIFT-002 | FULL |
| PARITY-CHECKOUT-001 | FULL |
| PARITY-VOID-001 | FULL |
| PARITY-VOID-002 | FULL |
| PARITY-VOID-003 | FULL |
| PARITY-RECIPE-001 | FULL |
| PARITY-RECIPE-002 | FULL |
| PARITY-RECIPE-003 | FULL |
| PARITY-FIFO-001 | FULL |
| PARITY-FIFO-002 | FULL |
| PARITY-FIFO-003 | FULL |
| PARITY-FIFO-004 | FULL |
| PARITY-RECIPE-004 | FULL |

## FIFO Bucket Status
- Status: PARTIAL

Validated:
- Web valuation parity tests: PASS
- Android repository FIFO allocation/reversal tests: PASS
- Android app compile/build: PASS (`:app:compileDebugKotlin`, `:app:assembleDebug` on 2026-03-19)

Blocked:
- E2E runtime verification for FIFO UI/history/void payload pending

Blocker Classification:
- Runtime parity evidence belum lengkap (device/emulator flow belum direkam)

Update 2026-03-20 (instrumentation execution attempt):
- Added app-level Compose instrumentation tests:
  - E2ECheckoutFifoTest
  - E2EVoidFifoTest
  - E2EStatusConsistencyTest
- Command run:
  - `:app:connectedDebugAndroidTest`
- Result:
  - Build/test APK compilation PASS.
  - Instrumentation execution FAIL karena environment: `No connected devices!`
- Kesimpulan:
  - Evidence runtime E2E masih BLOCKED oleh ketersediaan device/emulator, bukan oleh compile error test.

Referensi ticket blocker:
- docs/app-compile-blocker-001.md

## Bucket Berikutnya
- PARITY-RECIPE-*
- PARITY-FIFO-*
- PARITY-LOCK-*
- PARITY-PURCHASE-*

---

## PARITY-FIFO-001
MODULE: FIFO / Issue allocation order

WEB RULE REFERENCE:
- File: app/app/Services/InventoryValuationService.php
- Function: issueFifo()
- Rule summary:
  - issue harus consume layer tertua lebih dulu berdasarkan received_at
  - jika qty issue melewati layer pertama, alokasi harus split ke layer berikutnya

TEST INPUT:
- valuation method: fifo
- layer 1: qty 2, unit cost 10.000
- layer 2: qty 3, unit cost 12.000
- qty issue: 4

EXPECTED WEB:
- total cost: 44.000
- unit cost efektif: 11.000
- allocation split: 2 unit dari layer 1 dan 2 unit dari layer 2
- qty layer tersisa: layer 1 = 0, layer 2 = 1

EXPECTED APK:
- checkout offline mengalokasikan qty per layer berdasar urutan oldest-first
- payload finalize sale memuat fifo_allocations untuk replay server

PARITY CHECK:
- pass vs pass

STATUS:
- FULL

BUKTI EKSEKUSI:
- Web test: ValuationGapCoverageTest::test_fifo_issue_consumes_oldest_layers_and_records_split_allocations
- APK test: PosRepositoryImplTest::PARITY-FIFO-001 sale issue follows oldest layer order
- Tanggal uji: 2026-03-19

GAP NOTE:
- Coverage masih fokus ke scenario issue order; edge case replay lintas batch belum dicakup di sprint ini.

---

## PARITY-FIFO-002
MODULE: FIFO / Void reversal

WEB RULE REFERENCE:
- File: app/app/Services/InventoryValuationService.php
- Function: reverseIssue()
- Rule summary:
  - reversal void harus mengembalikan qty ke layer asal
  - allocation yang direverse wajib bertanda reversed_at
  - qty_on_hand pada cost state harus konsisten setelah reversal

TEST INPUT:
- valuation method: fifo
- receive: qty 5, unit cost 9.000
- issue sale: qty 3
- reverse issue untuk sale yang sama

EXPECTED WEB:
- qty layer kembali menjadi 5
- allocation sale bertanda reversed_at
- qty_on_hand cost state kembali ke 5

EXPECTED APK:
- flow void memanggil reversal allocation lokal untuk restore qty ke layer asal
- payload void_sale memuat fifo_reversal_allocations untuk sinkronisasi

PARITY CHECK:
- pass vs pass

STATUS:
- FULL

BUKTI EKSEKUSI:
- Web test: ValuationGapCoverageTest::test_fifo_reverse_issue_restores_layer_qty_and_marks_allocations_reversed
- APK test: PosRepositoryImplTest::PARITY-FIFO-002 void reversal restores previously consumed layers
- Tanggal uji: 2026-03-19

GAP NOTE:
- Validasi saat ini level repository; uji end-to-end UI/history void akan dilanjutkan pada batch berikutnya.

---

## PARITY-FIFO-003
MODULE: FIFO / Multi-layer complex issue

WEB RULE REFERENCE:
- File: app/app/Services/InventoryValuationService.php
- Function: issueFifo()
- Rule summary:
  - qty issue boleh melintasi banyak layer secara berurutan tanpa skip
  - allocation tiap layer harus eksplisit sesuai urutan FIFO

TEST INPUT:
- valuation method: fifo
- layer A: qty 1, unit cost 9.000
- layer B: qty 2, unit cost 10.000
- layer C: qty 4, unit cost 12.000
- qty issue: 6

EXPECTED WEB:
- allocation split 1 + 2 + 3 sesuai urutan A -> B -> C
- total cost dan unit cost efektif konsisten

EXPECTED APK:
- allocation split per layer identik urutan A -> B -> C
- payload finalize sale menyertakan fifo_allocations sesuai pembagian layer

PARITY CHECK:
- pass vs pass

STATUS:
- FULL

BUKTI EKSEKUSI:
- Web test: ValuationGapCoverageTest::test_fifo_multi_layer_issue_consumes_without_skipping_layers
- APK test: PosRepositoryImplTest::PARITY-FIFO-003 sale spanning three layers keeps strict fifo order and payload split
- Tanggal uji: 2026-03-19

GAP NOTE:
- Multi-layer complex issue sudah parity; pengujian fokus pada jalur issue.

---

## PARITY-FIFO-004
MODULE: FIFO / Reversal replay safety

WEB RULE REFERENCE:
- File: app/app/Services/InventoryValuationService.php
- Function: reverseIssue()
- Rule summary:
  - reverse issue harus idempotent terhadap replay yang sama
  - reversal kedua tidak boleh double-restore layer

TEST INPUT:
- issue qty 2 dari layer tunggal qty 4
- reverse issue dijalankan dua kali untuk sale yang sama

EXPECTED WEB:
- reverse pertama restore layer
- reverse kedua tidak menambah restore lagi

EXPECTED APK:
- reverse pertama restore layer dan menandai allocation reversed
- reverse kedua menghasilkan payload kosong dan stok layer tidak berubah

PARITY CHECK:
- pass vs pass

STATUS:
- FULL

BUKTI EKSEKUSI:
- Web test: ValuationGapCoverageTest::test_fifo_reverse_issue_is_replay_safe_and_does_not_double_restore
- APK test: PosRepositoryImplTest::PARITY-FIFO-004 replaying void reversal does not double-restore layer quantities
- APK test: VoidApprovalPolicyTest::PARITY-FIFO-E2E-HISTORY void payload includes governance and fifo reversal allocations
- Tanggal uji: 2026-03-19

GAP NOTE:
- Void payload wiring History -> outbox sudah ter-cover unit-level; UI instrumentation test khusus history screen belum ditambahkan.

---
## PARITY-SHIFT-001
MODULE: Shift

WEB RULE REFERENCE:
- File: app/app/Services/CheckoutService.php
- Function: assertShift()
- Rule summary:
  - shift harus open
  - shift harus milik actor user
  - shift branch harus match dengan payload transaksi

TEST INPUT:
- actor: kasir A
- branch: A
- shift: open, milik kasir A, branch A
- register: R1

EXPECTED WEB:
- checkout diizinkan lanjut

EXPECTED APK:
- guard shift mengizinkan lanjut
- tidak ada blokir UI untuk context shift

PARITY CHECK:
- success vs success

STATUS:
- NOT STARTED

RISIKO JIKA TIDAK PARITY:
- user melihat transaksi valid di APK tapi ditolak server saat sinkron

---

## PARITY-SHIFT-002
MODULE: Shift (edge case mismatch)

WEB RULE REFERENCE:
- File: app/app/Services/CheckoutService.php
- Function: assertShift()
- Rule summary:
  - shift user mismatch harus ditolak

TEST INPUT:
- shift open milik kasir A
- actor transaksi: kasir B
- branch sama

EXPECTED WEB:
- reject dengan error shift user mismatch

EXPECTED APK:
- harus reject sebelum finalize lokal atau enqueue outbox

PARITY CHECK:
- reject vs reject

STATUS:
- NOT STARTED

RISIKO JIKA TIDAK PARITY:
- local success semu, replay server gagal

---

## PARITY-CHECKOUT-001
MODULE: Checkout

WEB RULE REFERENCE:
- File: app/app/Services/CheckoutService.php
- Function: handle(), assertShift(), assertTotals()
- Rule summary:
  - shift valid
  - totals valid
  - context transaksi valid

TEST INPUT:
- actor: kasir A
- branch: A
- register: R1
- shift: open valid
- items: 2 produk
- subtotal/discount/tax/total: konsisten
- payments total = grand total

EXPECTED WEB:
- transaksi berhasil
- stok berkurang
- sale dan payment tercatat

EXPECTED APK:
- finalize lokal berhasil
- stok lokal berkurang
- sale/payment lokal tersimpan
- outbox checkout terbentuk

PARITY CHECK:
- success vs success

STATUS:
- NOT STARTED

RISIKO JIKA TIDAK PARITY:
- mismatch hasil transaksi antara local dan server

---

## PARITY-CHECKOUT-002
MODULE: Checkout (edge case total invalid)

WEB RULE REFERENCE:
- File: app/app/Services/CheckoutService.php
- Function: assertTotals()
- Rule summary:
  - subtotal/total/payment total harus konsisten

TEST INPUT:
- items: 2 produk
- subtotal payload sengaja tidak sesuai hitungan item
- payment amount tidak sama dengan total

EXPECTED WEB:
- reject invalid total

EXPECTED APK:
- harus reject saat pre-check, tidak finalize lokal

PARITY CHECK:
- reject vs reject

STATUS:
- NOT STARTED

RISIKO JIKA TIDAK PARITY:
- banyak transaksi invalid masuk local queue dan gagal belakangan

---

## PARITY-VOID-001
MODULE: Approval / Void

WEB RULE REFERENCE:
- File: app/app/Services/TransactionAuthorizationPolicyService.php
- Function: evaluateVoid()
- Rule summary:
  - limit nominal menentukan direct allowed vs butuh approver

TEST INPUT:
- nominal: 2,000,000
- role actor: kasir
- manager pin: tidak diberikan

EXPECTED WEB:
- tidak direct allowed, reject atau minta approval sesuai policy

EXPECTED APK:
- harus reject direct void atau minta approval
- tidak boleh dianggap langsung sukses final

PARITY CHECK:
- reject/pending approval vs reject/pending approval

STATUS:
- NOT STARTED

RISIKO JIKA TIDAK PARITY:
- user mengira void selesai padahal ditolak server

---

## PARITY-VOID-002
MODULE: Approval / Void (boundary)

WEB RULE REFERENCE:
- File: app/app/Services/TransactionAuthorizationPolicyService.php
- Function: evaluateVoid()
- Rule summary:
  - nominal kecil dapat direct allowed untuk role tertentu

TEST INPUT:
- nominal: 200,000
- role actor: kasir

EXPECTED WEB:
- direct allowed untuk kasir pada threshold kecil (sesuai policy)

EXPECTED APK:
- engine approval memberi keputusan setara

PARITY CHECK:
- allow vs allow

STATUS:
- NOT STARTED

---

## PARITY-VOID-003
MODULE: Approval / Void (manager approval path)

WEB RULE REFERENCE:
- File: app/app/Services/TransactionAuthorizationPolicyService.php
- Function: evaluateVoid()
- Rule summary:
  - jalur approval tidak boleh diperlakukan final completed sebelum approval final

TEST INPUT:
- nominal: 1,600,000
- role actor: supervisor

EXPECTED WEB:
- approval owner dibutuhkan, tidak direct completed

EXPECTED APK:
- status lokal setelah request harus REQUESTED (bukan COMPLETED)

PARITY CHECK:
- pending approval vs pending approval

STATUS:
- FULL

RISIKO JIKA TIDAK PARITY:
- over-blocking di APK atau under-blocking dibanding backend

---

## PARITY-LOCK-001
MODULE: Lock Period

WEB RULE REFERENCE:
- File: app/app/Services/LockPeriodService.php
- Function: ensureNotLocked()
- Rule summary:
  - transaksi pada rentang lock aktif harus ditolak

TEST INPUT:
- transaction_date berada di periode lock aktif tenant
- operation: checkout (atau write flow sensitif lain)

EXPECTED WEB:
- reject lock period

EXPECTED APK:
- reject sebelum enqueue

PARITY CHECK:
- reject vs reject

STATUS:
- NOT STARTED

RISIKO JIKA TIDAK PARITY:
- transaksi terkunci lolos lokal dan gagal massal saat sinkron

---

## PARITY-RECIPE-001
MODULE: Recipe / Auto-consume

WEB RULE REFERENCE:
- File: app/app/Services/AutoConsumeOnSellService.php
- Function: consumeForSaleItem()
- Rule summary:
  - recipe valid pada item jual akan consume bahan input dan menulis reference_type sale_recipe_input

TEST INPUT:
- auto_consume_on_sell_enabled: true
- item jual memiliki recipe valid
- qty jual: 1
- recipe qty material: 2

EXPECTED WEB:
- stok bahan berkurang sesuai recipe
- ledger/referensi auto-consume tercatat (sale_recipe_input)

EXPECTED APK:
- stok bahan lokal berkurang sesuai recipe
- outbox stock movement dibuat dengan reason sale_recipe_input

PARITY CHECK:
- consume vs consume

STATUS:
- FULL (pilot)

BUKTI EKSEKUSI PILOT:
- Web test: AutoConsumeOnSellCheckoutFlowTest::test_flag_on_with_valid_recipe_consumes_raw_material
- APK test: PosRepositoryImplTest::PARITY-RECIPE-001 valid recipe sale auto-consumes raw material and emits sale_recipe_input movement
- Tanggal uji: 2026-03-19

RISIKO JIKA TIDAK PARITY:
- konsumsi bahan lokal menyimpang dari rule backend

---

## PARITY-RECIPE-002
MODULE: Recipe / Auto-consume (invalid input type)

WEB RULE REFERENCE:
- File: app/app/Services/AutoConsumeOnSellService.php
- Function: consumeForSaleItem()
- Rule summary:
  - semua input recipe harus bertipe raw_material

TEST INPUT:
- recipe line menunjuk komponen dengan tipe bukan raw_material

EXPECTED WEB:
- reject dengan code AUTO_CONSUME_RECIPE_INVALID

EXPECTED APK:
- reject dengan code RECIPE_INPUT_TYPE_INVALID
- stok bahan tidak berubah
- outbox stock movement tidak terbentuk

PARITY CHECK:
- reject vs reject (reason setara)

STATUS:
- FULL

BUKTI EKSEKUSI:
- Web test: AutoConsumeOnSellCheckoutFlowTest::test_recipe_input_non_raw_material_fails_checkout_clearly
- APK test: PosRepositoryImplTest::PARITY-RECIPE-002 invalid recipe input type rejects and does not mutate stock or outbox
- Tanggal uji: 2026-03-19

IMPLEMENTATION NOTE:
- APK guard kini memakai field eksplisit item_type (bukan inferensi categoryName).

---

## PARITY-RECIPE-003
MODULE: Recipe / Auto-consume (invalid output type)

WEB RULE REFERENCE:
- File: app/app/Services/AutoConsumeOnSellService.php
- Function: consumeForSaleItem()
- Rule summary:
  - output recipe harus bertipe finished_good

TEST INPUT:
- produk output yang dijual bertipe bukan finished_good tetapi memiliki recipe line

EXPECTED WEB:
- reject dengan code AUTO_CONSUME_RECIPE_INVALID

EXPECTED APK:
- reject dengan code RECIPE_OUTPUT_TYPE_INVALID
- stok bahan tidak berubah
- outbox stock movement tidak terbentuk

PARITY CHECK:
- reject vs reject (reason setara)

STATUS:
- FULL

BUKTI EKSEKUSI:
- Web test: AutoConsumeOnSellCheckoutFlowTest::test_recipe_exists_but_output_not_finished_good_fails
- APK test: PosRepositoryImplTest::PARITY-RECIPE-003 output non finished good rejects auto-consume
- Tanggal uji: 2026-03-19

IMPLEMENTATION NOTE:
- APK guard output kini berbasis item_type eksplisit pada ProductEntity.

---

## PARITY-RECIPE-004
MODULE: Recipe / Auto-consume (insufficient stock policy)

WEB RULE REFERENCE:
- File: app/app/Services/AutoConsumeOnSellService.php
- Function: consumeForSaleItem()
- Rule summary:
  - allow_negative_raw_material_stock=false -> reject jika stok kurang
  - allow_negative_raw_material_stock=true -> boleh lanjut minus

TEST INPUT:
- stok bahan awal: 1
- kebutuhan consume: 2
- dua mode policy: strict dan allow-minus

EXPECTED WEB:
- strict: reject AUTO_CONSUME_INSUFFICIENT_RAW_MATERIAL
- allow-minus: consume diizinkan

EXPECTED APK:
- strict: reject AUTO_CONSUME_INSUFFICIENT_RAW_MATERIAL
- allow-minus: consume diizinkan hingga saldo negatif

PARITY CHECK:
- strict reject vs reject, permissive allow vs allow

STATUS:
- FULL

BUKTI EKSEKUSI:
- Web test: AutoConsumeOnSellCheckoutFlowTest::test_insufficient_raw_material_blocks_when_negative_not_allowed
- Web test: AutoConsumeOnSellCheckoutFlowTest::test_insufficient_raw_material_allowed_when_negative_policy_enabled
- APK test: PosRepositoryImplTest::PARITY-RECIPE-004 insufficient raw material policy mirrors reject and allow-minus
- Tanggal uji: 2026-03-19

IMPLEMENTATION NOTE:
- APK kini membaca allow_negative_raw_material_stock dari app policy snapshot (app_policies).

---

## Ringkasan Eksekusi Sprint 1 (Isi Berkala)
| Scenario ID | Module | Last Run | Web Result | APK Result | Status | Owner | Notes |
|---|---|---|---|---|---|---|---|
| PARITY-SHIFT-001 | Shift | - | - | - | NOT STARTED | - | - |
| PARITY-SHIFT-002 | Shift | 2026-03-19 | pass | pass | FULL | Team | Shift ownership parity validated |
| PARITY-CHECKOUT-001 | Checkout | 2026-03-19 | pass | pass | FULL | Team | Checkout happy path parity validated |
| PARITY-CHECKOUT-002 | Checkout | - | - | - | NOT STARTED | - | - |
| PARITY-VOID-001 | Approval/Void | 2026-03-19 | pass | pass | FULL | Team | Void low nominal direct allowed parity |
| PARITY-VOID-002 | Approval/Void | 2026-03-19 | pass | pass | FULL | Team | Void approval-required parity |
| PARITY-VOID-003 | Approval/Void | 2026-03-19 | pass | pass | FULL | Team | Requested vs completed status parity |
| PARITY-LOCK-001 | Lock Period | - | - | - | NOT STARTED | - | - |
| PARITY-RECIPE-001 | Recipe | 2026-03-19 | pass | pass | FULL | Team | Valid recipe auto-consume parity |
| PARITY-RECIPE-002 | Recipe | 2026-03-19 | pass | pass | FULL | Team | Guard input type kini berbasis item_type eksplisit |
| PARITY-RECIPE-003 | Recipe | 2026-03-19 | pass | pass | FULL | Team | Guard output type kini berbasis item_type eksplisit |
| PARITY-RECIPE-004 | Recipe | 2026-03-19 | pass | pass | FULL | Team | Policy minus-stock kini berbasis tenant policy snapshot |
| PARITY-RECIPE-* | Recipe | 2026-03-19 | pass | pass | FULL | Team | Recipe parity batch 001-004 sudah align dengan source of truth |
| PARITY-FIFO-* | FIFO/Average | 2026-03-19 | pass | pass | PARTIAL | Team | PARITY-FIFO-001/002/003/004 sudah FULL; bucket masih PARTIAL karena UI-level instrumentation untuk History void dan skenario partial void domain belum diformalisasi |
| PARITY-LOCK-* | Lock Period | - | - | - | NOT STARTED | Team | Extended lock parity bucket |
| PARITY-PURCHASE-* | Purchase | - | - | - | NOT STARTED | Team | Purchase and purchase return bucket |

## Catatan Wajib
- Jika hasil belum identik, jangan tulis selesai. Ubah status menjadi PARTIAL.
- Setiap PARTIAL harus punya gap note dan follow-up issue ID.
- Scenario ini dipakai sebagai bukti parity gate pada issue breakdown dan traceability matrix.