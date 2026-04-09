# APP-COMPILE-BLOCKER-001 - Jira Copy-Paste

Summary:
- APP-COMPILE-BLOCKER-001: Root-cause unresolved references across app module after workspace drift

Type:
- Bug

Priority:
- High

Labels:
- android
- build
- compile
- non-fifo
- parity-blocker

Description:
- FIFO parity evidence saat ini valid di level Web + repository APK.
- Runtime parity verification di app module tertahan oleh compile blocker yang tidak terkait langsung dengan patch FIFO.
- Error utama berupa unresolved references yang tersebar lintas file.

Validated Context:
- Web valuation parity tests: PASS
- Android repository FIFO allocation/reversal tests: PASS
- FIFO bucket status tetap: PARTIAL (runtime blocked)

Primary Investigation Targets:
- mobile-android-umkm-offline/app/src/main/java/com/umkm/offline/app/MainActivity.kt
- mobile-android-umkm-offline/app/src/main/java/com/umkm/offline/app/AppGovernance.kt
- mobile-android-umkm-offline/app/src/main/java/com/umkm/offline/app/AppExtraScreens.kt
- mobile-android-umkm-offline/app/src/main/java/com/umkm/offline/app/SubscriptionPlan.kt

Out of Scope:
- Perubahan behavior FIFO tanpa evidence test
- Refactor besar yang tidak diperlukan untuk restore compile

Acceptance Criteria:
- assembleDebug sukses
- simbol governance/screen/extension kembali ter-resolve
- tidak ada perubahan fungsional FIFO tanpa evidence test

Validation Steps:
- Run :app:compileDebugKotlin
- Run :app:testDebugUnitTest
- Run :app:assembleDebug
- Re-run parity FIFO tests yang terdampak

Done Definition:
- Root cause tercatat jelas
- Patch minimal diterapkan
- Bukti output command dilampirkan
- Tidak ada regresi pada evidence FIFO yang sudah FULL

References:
- docs/apk-parity-test-scenarios-sprint1-2026-03-18.md
- docs/apk-traceability-matrix-2026-03-18.md
- docs/app-compile-blocker-001.md
