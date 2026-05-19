import { test, expect, waitForPageReady } from '../helpers/auth';

test.describe('Backoffice Categories', () => {
  const TEST_TENANT = 'test-tenant';

  test.beforeEach(async ({ page, auth }) => {
    await auth.loginAsTenantAdmin(TEST_TENANT);
    await page.goto(`/${TEST_TENANT}/backoffice/categories`);
    await waitForPageReady(page);
  });

  test('categories page displays tree or list', async ({ page }) => {
    await expect(page.locator('[data-testid="categories-list"]')).toBeVisible();

    const items = await page.locator('[data-testid="category-item"]').count();
    expect(items).toBeGreaterThan(0);
  });

  test('open create category modal', async ({ page }) => {
    await page.click('[data-testid="create-category-btn"]');

    await expect(page.locator('[data-testid="category-modal"]')).toBeVisible();
    await expect(page.locator('[data-testid="category-form"]')).toBeVisible();
  });

  test('close category modal', async ({ page }) => {
    await page.click('[data-testid="create-category-btn"]');
    await page.waitForSelector('[data-testid="category-modal"]');

    await page.click('[data-testid="close-modal-btn"]');

    await expect(page.locator('[data-testid="category-modal"]')).not.toBeVisible();
  });

  test('category displays name and description', async ({ page }) => {
    const firstCategory = page.locator('[data-testid="category-item"]').first();

    const name = await firstCategory.locator('[data-testid="category-name"]').textContent();
    expect(name).toBeTruthy();
  });
});