# PR Summary Checkout Discount Foundation 2026-04-09

## Scope

PR ini hanya membawa fondasi diskon checkout: kalkulasi discount stacking, persist breakdown discount pada checkout, dan expose discount rows di sale detail. Variant, voucher, loyalty, pricing rules, dan production batch tetap di luar scope.

## Included Files

- `app/Http/Controllers/Api/SaleDetailController.php`
- `app/Models/Discount.php`
- `app/Models/Sale.php`
- `app/Services/CheckoutService.php`
- `app/Services/DiscountCalculationService.php`
- `database/migrations/2026_03_09_100070_create_discounts_table.php`
- `tests/Concerns/BuildsCheckoutRequestFixture.php`
- `tests/Feature/CheckoutDiscountFlowTest.php`
- `tests/Unit/DiscountCalculationServiceTest.php`

## What Changed

- menambahkan service kalkulasi diskon untuk item-level dan transaction-level discount stacking
- menyimpan breakdown diskon item dan transaksi ke tabel `discounts` saat checkout sukses
- menyelaraskan subtotal dan discount sale dari hasil kalkulasi ter-normalisasi
- menambahkan payload `discounts` pada sale detail agar breakdown diskon bisa dibaca kembali
- memperbarui fixture checkout agar test bisa bekerja pada schema register dan payment method yang sudah ada

## Validation

- `php artisan test tests/Unit/DiscountCalculationServiceTest.php tests/Feature/CheckoutDiscountFlowTest.php tests/Feature/SaleDetailTenantScopingTest.php` passed

## Explicitly Excluded

- voucher dan loyalty account flow
- pricing rules dan price list runtime
- product variant, register, dan payment method feature surface di luar kebutuhan fixture/test
- production batch dan auto-consume work