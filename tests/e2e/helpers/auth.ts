import { test as base, type Page } from '@playwright/test';

export interface AuthContext {
  loginAsAdmin: () => Promise<void>;
  loginAsTenantAdmin: (tenantSlug: string) => Promise<void>;
  logout: () => Promise<void>;
  getCurrentUser: () => Promise<string>;
}

export const test = base.extend<{
  auth: AuthContext;
}>({
  auth: async ({ page }, use) => {
    const auth: AuthContext = {
      async loginAsAdmin() {
        await page.goto('/admin/login');
        await page.fill('input[name="email"]', 'admin@kalakasir.test');
        await page.fill('input[name="password"]', 'password');
        await page.click('button[type="submit"]');
        await page.waitForURL('/admin', { timeout: 10000 });
      },

      async loginAsTenantAdmin(tenantSlug: string) {
        await page.goto(`/${tenantSlug}/backoffice/login`);
        await page.fill('input[name="email"]', `${tenantSlug}-admin@kalakasir.test`);
        await page.fill('input[name="password"]', 'password');
        await page.click('button[type="submit"]');
        await page.waitForURL(`/${tenantSlug}/backoffice`, { timeout: 10000 });
      },

      async logout() {
        await page.click('[data-testid="logout-button"]');
        await page.waitForURL(/\/(login|auth)/, { timeout: 5000 });
      },

      async getCurrentUser() {
        const userElement = await page.locator('[data-testid="current-user"]').textContent();
        return userElement?.trim() || '';
      },
    };
    await use(auth);
  },
});

export { expect } from '@playwright/test';

export async function waitForPageReady(page: Page): Promise<void> {
  await page.waitForLoadState('networkidle');
  await page.waitForSelector('body', { state: 'attached' });
}

export async function navigateToBackofficeMenu(
  page: Page,
  tenantSlug: string,
  menuItem: string
): Promise<void> {
  await page.click(`[data-testid="menu-${menuItem.toLowerCase()}"]`);
  await page.waitForURL(new RegExp(`/${tenantSlug}/backoffice/${menuItem.toLowerCase()}`), {
    timeout: 5000,
  });
}