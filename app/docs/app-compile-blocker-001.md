# APP-COMPILE-BLOCKER-001

Status:
- RESOLVED (no longer reproduces on latest verification run 2026-03-19)

Judul:
- Root-cause unresolved references across app module after workspace drift

Tanggal dibuat:
- 2026-03-19

Scope:
- Non-FIFO task (terpisah dari parity FIFO evidence)
- Fokus restore buildability modul app Android

Background:
- FIFO logic parity evidence sudah tervalidasi di level berikut:
  - Web valuation parity tests: PASS
  - Android repository FIFO allocation/reversal tests: PASS
- Verifikasi runtime app untuk FIFO tertahan karena blocker kompilasi modul app.

Problem Statement:
- Sebelumnya kompilasi modul app gagal dengan unresolved references di MainActivity.kt dan file terkait.
- Pada verifikasi terbaru, kegagalan tersebut tidak lagi reproduktif.

Suspected Impact:
- (historical) assembleDebug gagal
- Verifikasi E2E runtime FIFO UI/history/void payload tetap perlu bukti terpisah

Primary Investigation Targets:
- mobile-android-umkm-offline/app/src/main/java/com/umkm/offline/app/MainActivity.kt
- mobile-android-umkm-offline/app/src/main/java/com/umkm/offline/app/AppGovernance.kt
- mobile-android-umkm-offline/app/src/main/java/com/umkm/offline/app/AppExtraScreens.kt
- mobile-android-umkm-offline/app/src/main/java/com/umkm/offline/app/SubscriptionPlan.kt

Out of Scope:
- Perubahan behavior FIFO tanpa evidence test
- Refactor besar yang tidak diperlukan untuk memulihkan kompilasi

Acceptance Criteria:
- assembleDebug sukses
- simbol governance/screen/extension kembali ter-resolve
- tidak ada perubahan fungsional FIFO tanpa evidence test

Validation Plan:
- Run: :app:compileDebugKotlin
- Run: :app:testDebugUnitTest (setelah compile restore)
- Run: :app:assembleDebug
- Jalankan ulang parity FIFO tests terdampak untuk memastikan tidak ada regresi

Validation Evidence (2026-03-19):
- PASS `:app:assembleDebug`
- PASS `:feature-pos:testDebugUnitTest --tests com.umkm.offline.feature.pos.data.PosRepositoryImplTest`
- PASS `php artisan test --filter=ValuationGapCoverageTest`

Deliverables:
- Root cause summary (ringkas dan terverifikasi)
- Patch minimal untuk memulihkan kompilasi
- Bukti command output build/test sesudah perbaikan
- Update catatan traceability jika ada dampak terhadap jalur parity

Classification:
- Known unrelated app compile issue (historical)
- Tracked as separate non-FIFO task

## Update 2026-03-20 - Runtime Instrumentation Environment Blocker

Status:
- OPEN (environment-only blocker for runtime E2E evidence)

Context:
- Tiga instrumentation test runtime inti sudah ditambahkan di app module:
  - `E2ECheckoutFifoTest`
  - `E2EVoidFifoTest`
  - `E2EStatusConsistencyTest`
- Selektor UI untuk aksi void juga sudah dibuat deterministic via contentDescription.

Validation Evidence (2026-03-20):
- Run `:app:connectedDebugAndroidTest`
- Result: FAILED with `com.android.builder.testing.api.DeviceException: No connected devices!`

Conclusion:
- Gap runtime E2E saat ini bukan lagi unresolved reference compile issue.
- Gap yang tersisa adalah ketersediaan device/emulator terhubung untuk mengeksekusi instrumentation.
