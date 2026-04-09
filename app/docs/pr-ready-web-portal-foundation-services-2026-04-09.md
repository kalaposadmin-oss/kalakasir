# PR Ready - Web Portal Foundation Services 2026-04-09

## Judul PR

`feat(app): add web portal foundation services and auth views`

## Fokus Reviewer

- pastikan resolver host/session portal tidak membuka redirect lintas portal yang salah
- pastikan redirect legacy tetap aman terhadap query string mentah
- pastikan onboarding state service hanya mengelola session dan login, bukan mencampur logic route lagi
- pastikan bootstrap produk awal onboarding tetap memakai engine inventory/valuation yang existing
- pastikan gate blade dan menu registry cocok dengan shell portal yang sudah dikomit sebelumnya

## Validasi

- `PortalWebAccessTest`
- `WebAuthOnboardingSmokeTest`
- `OnboardingFirstTimeFlowTest`

## Catatan Boundary

Halaman feature consumer seperti subscription, help, preferences, integrations, marketing, dan platform sengaja ditahan ke commit berikutnya agar batch ini tetap berupa fondasi portal saja.