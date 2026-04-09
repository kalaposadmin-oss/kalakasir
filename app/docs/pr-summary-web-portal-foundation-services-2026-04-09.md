# PR Summary - Web Portal Foundation Services 2026-04-09

## Ringkasan

Batch ini menutup fondasi service/support/view yang menopang portal web baru, tetapi belum ikut membawa seluruh halaman feature di atasnya.

Cakupan utamanya:

- resolver portal aktif berbasis host dan session
- helper redirect legacy agar query string lama tetap aman saat dibawa ke route kanonis
- state onboarding lintas step sampai auto-login ke POS
- bootstrap produk awal onboarding agar stok awal dan valuation receive berjalan dari service terpisah
- registry menu dan gate blade yang dipakai shell web baru
- halaman autentikasi dasar untuk login dan logout

## File Utama

- `app/Services/LegacyWebRedirectService.php`
- `app/Services/OnboardingFlowStateService.php`
- `app/Services/OnboardingInitialProductService.php`
- `app/Support/WebPortalResolver.php`
- `app/Support/WebEntityFlowContext.php`
- `app/Support/WebSalesFlowContext.php`
- `config/menu-registry.php`
- `config/cors.php`
- `resources/views/auth-login.blade.php`
- `resources/views/logout.blade.php`
- `resources/views/components/gate.blade.php`
- `resources/views/partials/owner-pos-panel.blade.php`

## Batas Batch

Yang sengaja belum ikut di batch ini:

- halaman feature settings/help/marketing/subscription
- halaman platform admin
- metrics/reporting service

Batch ini hanya memasukkan fondasi yang dipakai lintas halaman tersebut supaya commit tetap defensible dan mudah diuji.

## Validasi

- `php artisan test tests/Feature/PortalWebAccessTest.php tests/Feature/WebAuthOnboardingSmokeTest.php tests/Feature/OnboardingFirstTimeFlowTest.php`