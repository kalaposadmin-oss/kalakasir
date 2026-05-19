import { test, expect, waitForPageReady } from '../helpers/auth';

test.describe('POS Shift', () => {
  const TEST_TENANT = 'test-tenant';
  const TEST_BRANCH = 'test-branch';

  test.beforeEach(async ({ page, auth }) => {
    await auth.loginAsPosUser(TEST_TENANT);
    await page.goto(`/${TEST_TENANT}/${TEST_BRANCH}/pos`);
    await waitForPageReady(page);
  });

  test('open shift modal', async ({ page }) => {
    await page.click('[data-testid="shift-button"]');

    await expect(page.locator('[data-testid="shift-modal"]')).toBeVisible();
  });

  test('start shift', async ({ page }) => {
    await page.click('[data-testid="shift-button"]');

    const shiftStatus = await page.locator('[data-testid="shift-status"]').textContent();

    if (shiftStatus === 'idle' || shiftStatus === 'closed') {
      await page.click('[data-testid="start-shift-btn"]');

      await page.waitForTimeout(500);

      const newStatus = await page.locator('[data-testid="shift-status"]').textContent();
      expect(newStatus).toBe('open');
    }
  });

  test('end shift', async ({ page }) => {
    await page.click('[data-testid="shift-button"]');

    const shiftStatus = await page.locator('[data-testid="shift-status"]').textContent();

    if (shiftStatus === 'open') {
      await page.click('[data-testid="end-shift-btn"]');

      await page.waitForTimeout(500);

      const newStatus = await page.locator('[data-testid="shift-status"]').textContent();
      expect(newStatus).toBe('closed');
    }
  });

  test('view shift summary', async ({ page }) => {
    await page.click('[data-testid="shift-button"]');

    await expect(page.locator('[data-testid="shift-summary"]')).toBeVisible();
    await expect(page.locator('[data-testid="shift-start-time"]')).toBeVisible();
    await expect(page.locator('[data-testid="shift-sales-count"]')).toBeVisible();
    await expect(page.locator('[data-testid="shift-total-amount"]')).toBeVisible();
  });

  test('close shift modal', async ({ page }) => {
    await page.click('[data-testid="shift-button"]');
    await page.waitForSelector('[data-testid="shift-modal"]');

    await page.click('[data-testid="close-modal-btn"]');

    await expect(page.locator('[data-testid="shift-modal"]')).not.toBeVisible();
  });
});