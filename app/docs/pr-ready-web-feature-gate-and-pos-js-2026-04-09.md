# PR Ready - Web Feature Gate and POS JS 2026-04-09

## Judul PR

`feat(app): add feature gate and pos page javascript`

## Fokus Reviewer

- pastikan gating client-side hanya memengaruhi affordance UI, bukan authorization server-side
- pastikan entry point POS hanya menyuntik script inti sekali per halaman
- pastikan build Vite menghasilkan asset POS terpisah tanpa merusak bundle utama

## Validasi

- `web: build`