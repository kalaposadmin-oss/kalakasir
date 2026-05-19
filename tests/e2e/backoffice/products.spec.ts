import { test, expect, waitForPageReady } from '../helpers/auth';

test.describe('Backoffice Products', () => {
  const TEST_TENANT = 'test-tenant';

  test.beforeEach(async ({ page, auth }) => {
    await auth.loginAsTenantAdmin(TEST_TENANT);
    await page.goto(`/${TEST_TENANT}/backoffice/products`);
    await waitForPageReady(page);
  });

  test('products page displays table', async ({ page }) => {
    await expect(page.locator('[data-testid="products-table"]')).toBeVisible();

    await expect(page.locator('[data-testid="product-row"]').first()).toBeVisible();

    const rows = await page.locator('[data-testid="product-row"]').count();
    expect(rows).toBeGreaterThan(0);
  });

  test('search filters products', async ({ page }) => {
    const searchInput = page.locator('[data-testid="product-search"]');
    await searchInput.fill('test product');

    await page.waitForTimeout(500);

    const rows = await page.locator('[data-testid="product-row"]').count();

    if (rows > 0) {
      const firstRowName = await page.locator('[data-testid="product-row"]').first().textContent();
      expect(firstRowName?.toLowerCase()).toContain('test product');
    }
  });

  test('filter by category', async ({ page }) => {
    await page.click('[data-testid="category-filter"]');

    await page.click('[data-testid="category-option"]:first-child');

    await page.waitForTimeout(500);

    await expect(page.locator('[data-testid="products-table"]')).toBeVisible();
  });

  test('open create product modal', async ({ page }) => {
    await page.click('[data-testid="create-product-btn"]');

    await expect(page.locator('[data-testid="product-modal"]')).toBeVisible();
    await expect(page.locator('[data-testid="product-form"]')).toBeVisible();
  });

  test('close product modal', async ({ page }) => {
    await page.click('[data-testid="create-product-btn"]');
    await page.waitForSelector('[data-testid="product-modal"]');

    await page.click('[data-testid="close-modal-btn"]');

    await expect(page.locator('[data-testid="product-modal"]')).not.toBeVisible();
  });

  test('view product details', async ({ page }) => {
    const firstProduct = page.locator('[data-testid="product-row"]').first();
    await firstProduct.click();

    await expect(page.locator('[data-testid="product-detail-modal"]')).toBeVisible();
    await expect(page.locator('[data-testid="product-detail-name"]')).toBeVisible();
    await expect(page.locator('[data-testid="product-detail-sku"]')).toBeVisible();
    await expect(page.locator('[data-testid="product-detail-price"]')).toBeVisible();
  });
});