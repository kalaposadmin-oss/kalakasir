# PR Summary - Web Lockfile 2026-04-09

## Ringkasan

Batch ini menambahkan `package-lock.json` agar dependensi frontend Laravel/Vite reproducible di semua environment.

## Cakupan

- lockfile npm untuk dependensi build Vite, Tailwind, Axios, dan tooling terkait

## Nilai Perubahan

- instalasi frontend menjadi deterministik
- hasil build lebih mudah direproduksi di CI dan workstation lain

## Validasi

- `npm run build`