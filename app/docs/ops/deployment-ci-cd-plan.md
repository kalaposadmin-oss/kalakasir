# Deployment & CI/CD Plan

Dokumen ini menjelaskan pipeline CI/CD dan SOP deploy.

## CI
- Unit tests
- Feature tests (valuation, write-flow)
- Lint/format (opsional)

## CD
- Manual deploy via workflow_dispatch
- Dry run default
- Deploy step menggunakan SSH + script server

## Secrets Required
- DEPLOY_HOST
- DEPLOY_USER
- DEPLOY_PORT
- DEPLOY_SSH_KEY

## Staging Defaults
- DEPLOY_PATH: /var/www/kalakasir
- APP_ENV=staging
- APP_DEBUG=false

## Deploy Steps (Server)
- git pull --ff-only
- composer install --no-dev --optimize-autoloader
- php artisan config:cache
- php artisan route:cache
- php artisan view:cache
- php artisan migrate --force
- php artisan queue:restart
- php artisan optimize

## Rollback Checklist
- Restore previous release
- Revert database (snapshot)
- Clear cache

## Notes
- Deployment harus dilakukan di maintenance window untuk migrasi schema berat.
