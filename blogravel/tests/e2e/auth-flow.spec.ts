import { test, expect } from '@playwright/test';
import { login } from './helpers';

test.describe('Authentication Flows', () => {
  test.use({ storageState: { cookies: [], origins: [] } });

  test('login with valid credentials', async ({ page }) => {
    await login(page);
    await expect(page.locator('body')).toContainText('Dashboard');
  });

  test('login with invalid password shows error', async ({ page }) => {
    await page.goto('/admin/login');
    await page.locator('input[type="email"]').pressSequentially('contact@azaber.com', { delay: 10 });
    await page.locator('input[type="password"]').pressSequentially('wrongpassword', { delay: 10 });

    await Promise.all([
      page.waitForNavigation({ timeout: 15000 }).catch(() => {}),
      page.locator('button[type="submit"]').click(),
    ]);

    await page.waitForTimeout(2000);
    await expect(page.locator('body')).toContainText(/credentials|invalid|failed/i);
  });

  test('register a new user', async ({ page }) => {
    await page.goto('/admin/register');
    await page.waitForURL('**/admin/register');

    const timestamp = Date.now();
    await page.getByLabel('First Name').fill('Test');
    await page.getByLabel('Last Name').fill('User');
    await page.locator('input[type="email"]').fill(`testuser${timestamp}@example.com`);
    await page.locator('input[type="password"]').first().fill('password123');
    await page.locator('input[type="password"]').last().fill('password123');

    await Promise.all([
      page.waitForNavigation({ timeout: 15000 }).catch(() => {}),
      page.locator('button[type="submit"]').click(),
    ]);

    await page.waitForTimeout(5000);
    const url = page.url();
    const isOnAdmin = url.endsWith('/admin') || url.endsWith('/admin/');
    const isOnMfa = url.includes('multi-factor');
    expect(isOnAdmin || isOnMfa).toBeTruthy();
  });
});
