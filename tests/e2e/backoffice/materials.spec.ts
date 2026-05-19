import { test, expect, waitForPageReady } from '../helpers/auth';

test.describe('Backoffice Materials', () => {
  const TEST_TENANT = 'test-tenant';

  test.beforeEach(async ({ page, auth }) => {
    await auth.loginAsTenantAdmin(TEST_TENANT);
    await page.goto(`/${TEST_TENANT}/backoffice/materials`);
    await waitForPageReady(page);
  });

  test('materials page displays table', async ({ page }) => {
    await expect(page.locator('[data-testid="materials-table"]')).toBeVisible();

    await expect(page.locator('[data-testid="material-row"]').first()).toBeVisible();

    const rows = await page.locator('[data-testid="material-row"]').count();
    expect(rows).toBeGreaterThan(0);
  });

  test('search filters materials', async ({ page }) => {
    const searchInput = page.locator('[data-testid="material-search"]');
    await searchInput.fill('material');

    await page.waitForTimeout(500);

    await expect(page.locator('[data-testid="materials-table"]')).toBeVisible();
  });

  test('filter by unit', async ({ page }) => {
    await page.click('[data-testid="unit-filter"]');

    await page.click('[data-testid="unit-option"]:first-child');

    await page.waitForTimeout(500);

    await expect(page.locator('[data-testid="materials-table"]')).toBeVisible();
  });

  test('open create material modal', async ({ page }) => {
    await page.click('[data-testid="create-material-btn"]');

    await expect(page.locator('[data-testid="material-modal"]')).toBeVisible();
    await expect(page.locator('[data-testid="material-form"]')).toBeVisible();
  });

  test('close material modal', async ({ page }) => {
    await page.click('[data-testid="create-material-btn"]');
    await page.waitForSelector('[data-testid="material-modal"]');

    await page.click('[data-testid="close-modal-btn"]');

    await expect(page.locator('[data-testid="material-modal"]')).not.toBeVisible();
  });

  test('view material details', async ({ page }) => {
    const firstMaterial = page.locator('[data-testid="material-row"]').first();
    await firstMaterial.click();

    await expect(page.locator('[data-testid="material-detail-modal"]')).toBeVisible();
    await expect(page.locator('[data-testid="material-detail-name"]')).toBeVisible();
    await expect(page.locator('[data-testid="material-detail-sku"]')).toBeVisible();
  });
});