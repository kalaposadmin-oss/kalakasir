import { test, expect, waitForPageReady, navigateToBackofficeMenu } from '../helpers/auth';

test.describe('Backoffice Navigation', () => {
  const TEST_TENANT = 'test-tenant';

  test.beforeEach(async ({ page, auth }) => {
    await auth.loginAsTenantAdmin(TEST_TENANT);
    await waitForPageReady(page);
  });

  test('dashboard loads correctly', async ({ page }) => {
    await expect(page).toHaveURL(new RegExp(`/${TEST_TENANT}/backoffice$`));

    await expect(page.locator('h1')).toContainText('Dashboard', { timeout: 5000 });

    await expect(page.locator('[data-testid="stat-sales"]')).toBeVisible();
    await expect(page.locator('[data-testid="stat-revenue"]')).toBeVisible();
    await expect(page.locator('[data-testid="stat-orders"]')).toBeVisible();
  });

  test('navigate to Products page', async ({ page }) => {
    await navigateToBackofficeMenu(page, TEST_TENANT, 'products');

    await expect(page).toHaveURL(new RegExp(`/${TEST_TENANT}/backoffice/products`));
    await expect(page.locator('h1')).toContainText('Products');

    await expect(page.locator('[data-testid="products-table"]')).toBeVisible();
  });

  test('navigate to Categories page', async ({ page }) => {
    await navigateToBackofficeMenu(page, TEST_TENANT, 'categories');

    await expect(page).toHaveURL(new RegExp(`/${TEST_TENANT}/backoffice/categories`));
    await expect(page.locator('h1')).toContainText('Categories');
  });

  test('navigate to Materials page', async ({ page }) => {
    await navigateToBackofficeMenu(page, TEST_TENANT, 'materials');

    await expect(page).toHaveURL(new RegExp(`/${TEST_TENANT}/backoffice/materials`));
    await expect(page.locator('h1')).toContainText('Materials');
  });

  test('navigate to Purchases page', async ({ page }) => {
    await navigateToBackofficeMenu(page, TEST_TENANT, 'purchases');

    await expect(page).toHaveURL(new RegExp(`/${TEST_TENANT}/backoffice/purchases`));
    await expect(page.locator('h1')).toContainText('Purchases');

    await expect(page.locator('[data-testid="purchases-list"]')).toBeVisible();
  });

  test('navigate to Sales page', async ({ page }) => {
    await navigateToBackofficeMenu(page, TEST_TENANT, 'sales');

    await expect(page).toHaveURL(new RegExp(`/${TEST_TENANT}/backoffice/sales`));
    await expect(page.locator('h1')).toContainText('Sales');

    await expect(page.locator('[data-testid="sales-list"]')).toBeVisible();
  });

  test('navigate to Transfers page', async ({ page }) => {
    await navigateToBackofficeMenu(page, TEST_TENANT, 'transfers');

    await expect(page).toHaveURL(new RegExp(`/${TEST_TENANT}/backoffice/transfers`));
    await expect(page.locator('h1')).toContainText('Transfers');
  });

  test('navigate to Inventory page', async ({ page }) => {
    await navigateToBackofficeMenu(page, TEST_TENANT, 'inventory');

    await expect(page).toHaveURL(new RegExp(`/${TEST_TENANT}/backoffice/inventory`));
    await expect(page.locator('h1')).toContainText('Inventory');

    await expect(page.locator('[data-testid="inventory-stats"]')).toBeVisible();
  });

  test('navigate to Reports page', async ({ page }) => {
    await navigateToBackofficeMenu(page, TEST_TENANT, 'reports');

    await expect(page).toHaveURL(new RegExp(`/${TEST_TENANT}/backoffice/reports`));
    await expect(page.locator('h1')).toContainText('Reports');
  });

  test('navigate to Settings page', async ({ page }) => {
    await navigateToBackofficeMenu(page, TEST_TENANT, 'settings');

    await expect(page).toHaveURL(new RegExp(`/${TEST_TENANT}/backoffice/settings`));
    await expect(page.locator('h1')).toContainText('Settings');

    await expect(page.locator('[data-testid="settings-sections"]')).toBeVisible();
  });

  test('navigate to Finance Dashboard', async ({ page }) => {
    await navigateToBackofficeMenu(page, TEST_TENANT, 'finance');

    await expect(page).toHaveURL(new RegExp(`/${TEST_TENANT}/backoffice/finance`));
    await expect(page.locator('h1')).toContainText('Finance');

    await expect(page.locator('[data-testid="finance-dashboard"]')).toBeVisible();
  });

  test('navigate to Branches page', async ({ page }) => {
    await navigateToBackofficeMenu(page, TEST_TENANT, 'branches');

    await expect(page).toHaveURL(new RegExp(`/${TEST_TENANT}/backoffice/branches`));
    await expect(page.locator('h1')).toContainText('Branches');
  });
});