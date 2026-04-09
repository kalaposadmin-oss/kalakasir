# Commit Message Web Lockfile 2026-04-09

## Subject

`chore(app): add web package lockfile`

## Body

```text
Add the npm lockfile so the frontend toolchain installs deterministically.

- lock Vite, Tailwind, Axios, and related build dependencies
- make local and CI installs reproducible

Validation:
- npm run build
```