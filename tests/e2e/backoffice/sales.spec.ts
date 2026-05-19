import { test, expect, waitForPageReady } from '../helpers/auth';

test.describe('Backoffice Sales', () => {
  const TEST_TENANT = 'test-tenant';

  test.beforeEach(async ({ page, auth }) => {
    await auth.loginAsTenantAdmin(TEST_TENANT);
    await page.goto(`/${TEST_TENANT}/backoffice/sales`);
    await waitForPageReady(page);
  });

  test('sales page displays list', async ({ page }) => {
    await expect(page.locator('[data-testid="sales-list"]')).toBeVisible();

    const rows = await page.locator('[data-testid="sale-row"]').count();

    if (rows === 0) {
      await expect(page.locator('[data-testid="empty-state"]')).toBeVisible();
    } else {
      await expect(page.locator('[data-testid="sale-row"]').first()).toBeVisible();
    }
  });

  test('filter sales by date range', async ({ page }) => {
    const today = new Date();
    const lastWeek = new Date(today);
    lastWeek.setDate(today.getDate() - 7);

    await page.fill('[data-testid="date-from"]', lastWeek.toISOString().split('T')[0]);
    await page.fill('[data-testid="date-to"]', today.toISOString().split('T')[0]);

    await page.click('[data-testid="apply-filter-btn"]');

    await page.waitForTimeout(500);

    await expect(page.locator('[data-testid="sales-list"]')).toBeVisible();
  });

  test('filter sales by status', async ({ page }) => {
    await page.click('[data-testid="status-filter"]');

    await page.click('[data-testid="status-completed"]');

    await page.waitForTimeout(500);

    await expect(page.locator('[data-testid="sales-list"]')).toBeVisible();
  });

  test('view sale details', async ({ page }) => {
    const firstSale = page.locator('[data-testid="sale-row"]').first();
    const count = await firstSale.count();

    if (count > 0) {
      await firstSale.click();

      await expect(page.locator('[data-testid="sale-detail-modal"]')).toBeVisible();
      await expect(page.locator('[data-testid="sale-detail-items"]')).toBeVisible();
    }
  });
});