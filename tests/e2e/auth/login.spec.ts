import { test, expect, waitForPageReady } from '../helpers/auth';

test.describe('Authentication Flow', () => {
  const TEST_TENANT = 'test-tenant';

  test('backoffice login page loads', async ({ page }) => {
    await page.goto(`/${TEST_TENANT}/backoffice/login`);

    await expect(page.locator('[data-testid="login-form"]')).toBeVisible();
    await expect(page.locator('[data-testid="email-input"]')).toBeVisible();
    await expect(page.locator('[data-testid="password-input"]')).toBeVisible();
    await expect(page.locator('[data-testid="login-button"]')).toBeVisible();
  });

  test('backoffice login with valid credentials', async ({ page, auth }) => {
    await auth.loginAsTenantAdmin(TEST_TENANT);

    await waitForPageReady(page);

    await expect(page).toHaveURL(`/${TEST_TENANT}/backoffice/dashboard`);
  });

  test('backoffice login redirects authenticated user', async ({ page, auth }) => {
    await auth.loginAsTenantAdmin(TEST_TENANT);

    await page.goto(`/${TEST_TENANT}/backoffice/login`);

    await expect(page).toHaveURL(`/${TEST_TENANT}/backoffice/dashboard`);
  });

  test('POS login page loads', async ({ page }) => {
    await page.goto(`/${TEST_TENANT}/test-branch/pos/login`);

    await expect(page.locator('[data-testid="login-form"]')).toBeVisible();
    await expect(page.locator('[data-testid="pin-input"]')).toBeVisible();
    await expect(page.locator('[data-testid="login-button"]')).toBeVisible();
  });

  test('POS login with valid PIN', async ({ page, auth }) => {
    await auth.loginAsPosUser(TEST_TENANT);

    await waitForPageReady(page);

    await expect(page).toHaveURL(new RegExp(`/${TEST_TENANT}/[^/]+/pos`));
  });

  test('POS login redirects authenticated user', async ({ page, auth }) => {
    await auth.loginAsPosUser(TEST_TENANT);

    await page.goto(`/${TEST_TENANT}/test-branch/pos/login`);

    await expect(page.url()).toMatch(new RegExp(`/${TEST_TENANT}/[^/]+/pos`));
  });

  test('logout from backoffice', async ({ page, auth }) => {
    await auth.loginAsTenantAdmin(TEST_TENANT);
    await waitForPageReady(page);

    await page.click('[data-testid="user-menu"]');
    await page.click('[data-testid="logout-btn"]');

    await expect(page).toHaveURL(`/${TEST_TENANT}/backoffice/login`);
  });

  test('logout from POS', async ({ page, auth }) => {
    await auth.loginAsPosUser(TEST_TENANT);
    await waitForPageReady(page);

    await page.click('[data-testid="logout-btn"]');

    await expect(page.url()).toMatch(new RegExp(`/${TEST_TENANT}/[^/]+/pos/login`));
  });

  test('session persists after refresh', async ({ page, auth }) => {
    await auth.loginAsTenantAdmin(TEST_TENANT);
    await waitForPageReady(page);

    await page.reload();
    await waitForPageReady(page);

    await expect(page).toHaveURL(`/${TEST_TENANT}/backoffice/dashboard`);
  });
});