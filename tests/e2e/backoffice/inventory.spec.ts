import { test, expect, waitForPageReady } from '../helpers/auth';

test.describe('Backoffice Inventory', () => {
  const TEST_TENANT = 'test-tenant';

  test.beforeEach(async ({ page, auth }) => {
    await auth.loginAsTenantAdmin(TEST_TENANT);
    await page.goto(`/${TEST_TENANT}/backoffice/inventory`);
    await waitForPageReady(page);
  });

  test('inventory page displays stats', async ({ page }) => {
    await expect(page.locator('[data-testid="inventory-stats"]')).toBeVisible();

    await expect(page.locator('[data-testid="stat-total-items"]')).toBeVisible();
    await expect(page.locator('[data-testid="stat-total-value"]')).toBeVisible();
    await expect(page.locator('[data-testid="stat-low-stock"]')).toBeVisible();
  });

  test('inventory table displays products', async ({ page }) => {
    await expect(page.locator('[data-testid="inventory-table"]')).toBeVisible();

    const rows = await page.locator('[data-testid="inventory-row"]').count();

    if (rows === 0) {
      await expect(page.locator('[data-testid="empty-state"]')).toBeVisible();
    } else {
      await expect(page.locator('[data-testid="inventory-row"]').first()).toBeVisible();
    }
  });

  test('filter by low stock', async ({ page }) => {
    await page.click('[data-testid="filter-low-stock"]');

    await page.waitForTimeout(500);

    await expect(page.locator('[data-testid="inventory-table"]')).toBeVisible();
  });

  test('filter by category', async ({ page }) => {
    await page.click('[data-testid="category-filter"]');

    await page.click('[data-testid="category-option"]:first-child');

    await page.waitForTimeout(500);

    await expect(page.locator('[data-testid="inventory-table"]')).toBeVisible();
  });

  test('search inventory', async ({ page }) => {
    const searchInput = page.locator('[data-testid="inventory-search"]');
    await searchInput.fill('test');

    await page.waitForTimeout(500);

    await expect(page.locator('[data-testid="inventory-table"]')).toBeVisible();
  });
});