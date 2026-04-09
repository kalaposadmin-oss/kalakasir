# PR Ready - Web Consumer Pages 2026-04-09

## Judul PR

`feat(app): add subscription support and marketing portal pages`

## Fokus Reviewer

- pastikan tiap view memakai role guard yang sesuai untuk area backoffice
- pastikan CTA, section title, dan fallback UI cocok dengan smoke test yang sudah ada
- pastikan halaman hanya mengonsumsi endpoint existing dan tidak mengasumsikan API baru yang belum ada

## Validasi

- `WebSubscriptionSupportSmokeTest`
- `WebSupportMarketingSmokeTest`

## Yang Belum Ikut

- halaman platform admin
- refund legacy audit page
- sales receipt / halaman POS flow lain