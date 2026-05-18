# Kalakasir

Kalakasir is a POS web application. The active web project is the Laravel app in
`app/`.

## Repository Layout

```txt
kalakasir/
+-- app/              # Laravel web application
+-- mobile/
|   +-- android/      # Android app source, currently paused
+-- apk/              # Local APK build artifacts, ignored by Git
+-- docs/             # Repository-level notes
+-- README.md
```

## Development Focus

- Primary development: Laravel web app in `app/`.
- Android development: paused; source is kept under `mobile/android/`.
- APK files and Android build outputs are local artifacts and should not be
  committed.

## Deployment

Panduan upload GitHub dan hosting ada di [docs/deployment-hosting.md](docs/deployment-hosting.md).
Intinya, jalankan semua command Laravel dari folder `app/`, dan arahkan document root hosting ke `app/public`.
