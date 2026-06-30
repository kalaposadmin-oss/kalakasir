import { expect, test } from '@playwright/test';

test('portal chooser and login entries are reachable', async ({ page }) => {
  await page.goto('/');

  await expect(page.locator('body')).toBeVisible();
  await expect(page.locator('a[href="/pos/login"]')).toBeVisible();
  await expect(page.locator('a[href="/backoffice"]')).toBeVisible();

  await page.goto('/pos/login');
  await expect(page.locator('#login-form')).toBeVisible();
  await expect(page.locator('#email')).toBeVisible();
  await expect(page.locator('#password')).toBeVisible();

  await page.goto('/backoffice');
  await expect(page.locator('#login-form')).toBeVisible();
});

test('new shell is constrained for mobile and tablet', async ({ page }) => {
  await page.goto('/backoffice');

  const shell = page.locator('.app-shell-page');
  await expect(shell).toBeVisible();

  const box = await shell.boundingBox();
  expect(box).not.toBeNull();
  expect(box!.width).toBeLessThanOrEqual(768);
});

test('legacy drawer is not rendered', async ({ page }) => {
  await page.goto('/pos/login');

  await expect(page.locator('#menu-drawer')).toHaveCount(0);
  await expect(page.locator('#menu-overlay')).toHaveCount(0);
});
