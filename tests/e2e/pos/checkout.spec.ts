import { test, expect, waitForPageReady } from '../helpers/auth';

test.describe('POS Checkout', () => {
  const TEST_TENANT = 'test-tenant';
  const TEST_BRANCH = 'test-branch';

  test.beforeEach(async ({ page, auth }) => {
    await auth.loginAsPosUser(TEST_TENANT);
    await page.goto(`/${TEST_TENANT}/${TEST_BRANCH}/pos`);
    await waitForPageReady(page);
  });

  test('POS page displays cart and product grid', async ({ page }) => {
    await expect(page.locator('[data-testid="pos-layout"]')).toBeVisible();
    await expect(page.locator('[data-testid="cart-panel"]')).toBeVisible();
    await expect(page.locator('[data-testid="product-grid"]')).toBeVisible();
  });

  test('add product to cart', async ({ page }) => {
    const firstProduct = page.locator('[data-testid="product-card"]').first();
    const count = await firstProduct.count();

    if (count > 0) {
      const productName = await firstProduct.locator('[data-testid="product-name"]').textContent();
      await firstProduct.click();

      await expect(page.locator('[data-testid="cart-item"]')).toBeVisible();

      const cartItemName = await page.locator('[data-testid="cart-item"]').first().textContent();
      expect(cartItemName).toContain(productName);
    }
  });

  test('remove item from cart', async ({ page }) => {
    const firstProduct = page.locator('[data-testid="product-card"]').first();
    const count = await firstProduct.count();

    if (count > 0) {
      await firstProduct.click();

      await expect(page.locator('[data-testid="cart-item"]')).toBeVisible();

      await page.click('[data-testid="cart-item"] [data-testid="remove-btn"]');

      await page.waitForTimeout(500);

      const cartCount = await page.locator('[data-testid="cart-item"]').count();
      expect(cartCount).toBe(0);
    }
  });

  test('update quantity in cart', async ({ page }) => {
    const firstProduct = page.locator('[data-testid="product-card"]').first();
    const count = await firstProduct.count();

    if (count > 0) {
      await firstProduct.click();

      await page.click('[data-testid="cart-item"] [data-testid="quantity-plus"]');

      await page.waitForTimeout(200);

      const quantity = await page.locator('[data-testid="cart-item"] [data-testid="quantity-display"]').textContent();
      expect(quantity).toBe('2');
    }
  });

  test('search products', async ({ page }) => {
    const searchInput = page.locator('[data-testid="product-search"]');
    await searchInput.fill('test');

    await page.waitForTimeout(500);

    await expect(page.locator('[data-testid="product-grid"]')).toBeVisible();
  });

  test('filter products by category', async ({ page }) => {
    const categoryTab = page.locator('[data-testid="category-tab"]').first();
    const count = await categoryTab.count();

    if (count > 0) {
      await categoryTab.click();

      await page.waitForTimeout(500);

      await expect(page.locator('[data-testid="product-grid"]')).toBeVisible();
    }
  });

  test('cart displays total', async ({ page }) => {
    const firstProduct = page.locator('[data-testid="product-card"]').first();
    const count = await firstProduct.count();

    if (count > 0) {
      await firstProduct.click();

      await expect(page.locator('[data-testid="cart-total"]')).toBeVisible();

      const totalText = await page.locator('[data-testid="cart-total"]').textContent();
      expect(totalText).toBeTruthy();
    }
  });
});