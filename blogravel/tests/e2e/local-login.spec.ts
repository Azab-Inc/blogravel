import { test, expect } from '@playwright/test';

test.describe('Local admin authentication', () => {
  test.use({ storageState: { cookies: [], origins: [] } });

  test('keeps an admin session on localhost after login and refresh', async ({ page, context }) => {
    await page.goto('http://localhost:8000/admin/login');
    await page.locator('input[type="email"]').fill('admin@acme.io');
    await page.locator('input[type="password"]').fill('password');
    await page.locator('button[type="submit"]').click();

    await page.waitForURL('http://localhost:8000/admin');
    await expect(page.locator('body')).toContainText('Dashboard');

    const sessionCookie = (await context.cookies('http://localhost:8000'))
      .find((cookie) => cookie.name === 'blogravel-session');
    expect(sessionCookie?.domain).toBe('localhost');

    await page.reload();
    await expect(page).toHaveURL('http://localhost:8000/admin');
    await expect(page.locator('body')).toContainText('Dashboard');
  });
});
