import { test, expect, type Page } from '@playwright/test';

const PLATFORM_HOST = 'blogravel.com';
const ACME_HOST = 'acmeio.blogravel.com';
const GLOBEX_HOST = 'globexnet.blogravel.com';
const INVALID_HOST = 'unknown.blogravel.com';
const LOCAL_TENANT_HOST = 'acmeio.localhost';

async function gotoHost(page: Page, host: string, path = '/') {
  return page.goto(`http://${host}:8000${path}`);
}

test.describe('Tenant subdomain routing', () => {
  test.describe('guest requests', () => {
    test.use({ storageState: { cookies: [], origins: [] } });

    test('redirects a guest from the platform root to admin login', async ({ page }) => {
      const response = await gotoHost(page, PLATFORM_HOST);

      expect(response?.status()).toBe(200);
      await expect(page).toHaveURL(`http://${PLATFORM_HOST}:8000/admin/login`);
    });
  });

  test('redirects an authenticated user from the platform root to the dashboard', async ({ page }) => {
    const response = await gotoHost(page, PLATFORM_HOST);

    expect(response?.status()).toBe(200);
    await expect(page).toHaveURL(`http://${PLATFORM_HOST}:8000/admin`);
    await expect(page.locator('body')).toContainText('Dashboard');
  });

  test('renders each tenant host with isolated tenant content', async ({ page }) => {
    const acmeResponse = await gotoHost(page, ACME_HOST);
    const acmeBody = await page.locator('body').textContent();
    const globexResponse = await gotoHost(page, GLOBEX_HOST);

    expect(acmeResponse?.status()).toBe(200);
    expect(globexResponse?.status()).toBe(200);
    await expect(page.locator('header h1')).toContainText('globex.net');
    await expect(page.locator('header h1')).not.toContainText('acme.io');
    expect(acmeBody).toContain('acme.io');
  });

  test('returns 404 for an invalid tenant host', async ({ page }) => {
    const response = await gotoHost(page, INVALID_HOST);

    expect(response?.status()).toBe(404);
    await expect(page.locator('body')).toContainText(/not found|404/i);
  });

  test('renders a tenant on the local subdomain', async ({ page }) => {
    const response = await gotoHost(page, LOCAL_TENANT_HOST);

    expect(response?.status()).toBe(200);
    await expect(page.locator('header h1')).toContainText('acme.io');
  });

  test('renders a tenant on the local path', async ({ page }) => {
    const response = await gotoHost(page, 'localhost', '/acmeio/');

    expect(response?.status()).toBe(200);
    await expect(page.locator('header h1')).toContainText('acme.io');
  });

  test('preserves authentication while moving between platform and tenant hosts', async ({ page }) => {
    const rootResponse = await gotoHost(page, PLATFORM_HOST);

    expect(rootResponse?.status()).toBe(200);
    await expect(page).toHaveURL(`http://${PLATFORM_HOST}:8000/admin`);
    await expect(page.locator('body')).toContainText('Dashboard');

    const response = await gotoHost(page, ACME_HOST, '/admin');

    expect(response?.status()).toBe(200);
    await expect(page).toHaveURL(`http://${ACME_HOST}:8000/admin`);
    await expect(page.locator('body')).toContainText('Dashboard');
  });
});
