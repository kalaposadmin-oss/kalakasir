# E2E Tests

End-to-end tests for KASIRR using Playwright.

## Structure

```
tests/e2e/
├── helpers/
│   └── auth.ts          # Test helpers for authentication and common utilities
├── auth/
│   └── login.spec.ts    # Authentication flow tests
├── backoffice/
│   ├── navigation.spec.ts     # Backoffice navigation tests
│   ├── products.spec.ts       # Products CRUD tests
│   ├── sales.spec.ts          # Sales management tests
│   └── inventory.spec.ts      # Inventory management tests
├── pos/
│   ├── checkout.spec.ts       # POS checkout flow tests
│   └── shift.spec.ts          # POS shift management tests
└── README.md
```

## Running Tests

### Run all tests
```bash
npm run test:e2e
```

### Run specific test file
```bash
npx playwright test auth/login.spec.ts
```

### Run with UI mode
```bash
npx playwright test --ui
```

### Run with headed mode
```bash
npx playwright test --headed
```

### Debug a specific test
```bash
npx playwright test auth/login.spec.ts --debug
```

## Test Data Attributes

Tests use `data-testid` attributes for element selection. Ensure these are added to the UI components:

| Component | Test ID |
|-----------|---------|
| Login Form | `login-form` |
| Email Input | `email-input` |
| Password Input | `password-input` |
| PIN Input | `pin-input` |
| Login Button | `login-button` |
| Dashboard | `dashboard` |
| Cart Panel | `cart-panel` |
| Product Grid | `product-grid` |
| Product Card | `product-card` |
| Product Name | `product-name` |
| Cart Item | `cart-item` |
| Cart Total | `cart-total` |
| Shift Button | `shift-button` |
| Shift Modal | `shift-modal` |
| Inventory Table | `inventory-table` |
| Sales List | `sales-list` |

## Environment Variables

The tests can be configured via environment variables:

- `BASE_URL` - Base URL for the application (default: `http://localhost:3000`)
- `TEST_TENANT` - Test tenant identifier (default: `test-tenant`)

## CI Integration

The tests are configured to run in CI using GitHub Actions. See [`.github/workflows/playwright.yml`](../../.github/workflows/playwright.yml).