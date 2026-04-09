# Web Manual Checklist

## Environment

- Base URL: http://127.0.0.1:8000
- Local database: `app/database/database.sqlite`
- Public entry routes expected to respond: `/register/step-1`, `/pos/login`, `/backoffice/login`
- Optional portal hosts for generic `/login` verification:
  - POS host: `http://pos.127.0.0.1.nip.io:8000/login`
  - Backoffice host: `http://admin.127.0.0.1.nip.io:8000/login`
  - Env config: `POS_PORTAL_HOSTS=pos.127.0.0.1.nip.io` and `BACKOFFICE_PORTAL_HOSTS=admin.127.0.0.1.nip.io`
  - `pos.localhost` and `admin.localhost` tetap bisa dipakai bila resolver lokal mendukung wildcard `localhost`
  - Custom `.test` hosts tetap valid jika dipetakan manual di file hosts OS

## Pre-check

1. Open `http://127.0.0.1:8000` and confirm the app responds.
2. Confirm onboarding page opens at `/register/step-1` when testing fresh-registration flow.
3. Confirm POS login page opens at `/pos/login`.
4. Confirm Backoffice login page opens at `/backoffice/login`.
5. If host-based portal split is enabled, confirm both portal hosts resolve to the same local app instance.

## Onboarding Flow

1. Complete step 1 with a new owner email.
2. Complete step 2 with store name, operational address, and business category.
3. Complete step 3 with valuation method.
4. Complete step 4 with first product, category, HPP, price, and initial stock.
5. Confirm redirect finishes successfully.
6. Confirm the new tenant is created without overwriting an existing tenant.
7. Confirm dashboard/bootstrap data includes:
   - tenant store name
   - business category
   - valuation method
   - default branch
   - default location
   - first product
   - category
   - initial inventory balance

## POS / Shift Flow

1. Login as cashier through `/pos/login`.
2. Open `/pos/checkout` before opening shift and confirm redirect to `/pos/shifts/open`.
3. Open shift with `0` opening cash and confirm it is allowed.
4. Return to checkout and confirm the cashier page is accessible.
5. Close shift and confirm summary page opens successfully.

## Portal Separation

1. Login as cashier and confirm generic login lands on POS dashboard.
2. Login as admin or owner and confirm generic login lands on Backoffice dashboard.
3. Confirm cashier cannot open backoffice-only pages.
4. Confirm warehouse/supervisor/admin access matches their allowed backoffice pages.
5. Confirm legacy routes redirect to canonical `/pos/...` or `/backoffice/...` routes.
6. Open generic `/login` on the POS host and confirm the page renders POS copy.
7. Login as owner from the POS host and confirm redirect stays in `/pos/dashboard`.
8. Open generic `/login` on the Backoffice host and confirm the page renders Backoffice copy.
9. Login as owner from the Backoffice host and confirm redirect stays in `/backoffice/dashboard`.

## Inventory / Reporting Smoke

1. Open sales history from POS and confirm the page renders.
2. Open backoffice sales report and confirm the page renders for allowed roles.
3. Open inventory stock card from backoffice and confirm the page renders.
4. Open valuation settings and valuation audit pages as admin/owner on allowed plan.
5. Confirm those valuation pages are blocked on plan/status combinations that should not allow them.

## Regression Baseline

- Targeted tests last re-run successfully:
  - `OnboardingFirstTimeFlowTest`
  - `PortalWebAccessTest`
  - `ValuationWebSmokeTest`
- Expected result from the last targeted run: `71 passed (377 assertions)`.