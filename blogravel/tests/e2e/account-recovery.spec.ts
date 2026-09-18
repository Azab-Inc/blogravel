import { expect, test } from '@playwright/test';
import {
  cleanupE2eFixtures,
  clearRecoveryRateLimiter,
  createClosedTenantRecoveryFixture,
  createE2eTenantFixture,
  createExpiredTenantRecoveryFixture,
  login,
  loginAs,
} from './helpers';

const blankStorage = { cookies: [], origins: [] };

async function recoverAccount(page: Parameters<typeof loginAs>[0], email: string, password: string) {
  await clearRecoveryRateLimiter();
  await page.goto('/admin/recover-account');
  await page.locator('input[type="email"]').fill(email);
  await page.locator('input[type="password"]').fill(password);
  await page.getByRole('button', { name: 'Recover account' }).click();
}

async function closeTenantAccount(page: Parameters<typeof loginAs>[0], tenantName: string) {
  await page.goto('/admin/settings');
  await page.getByRole('button', { name: 'Close Account' }).click();
  await expect(page.getByRole('heading', { name: 'Close Account' })).toBeVisible();
  await page.getByLabel('Type the tenant name or slug to confirm').fill(tenantName);
  await page.getByRole('button', { name: 'Yes, Close My Account' }).click();
  await page.waitForURL('**/admin/login');
}

for (const viewport of [
  { name: 'desktop', size: { width: 1280, height: 900 } },
  { name: 'mobile', size: { width: 390, height: 844 } },
]) {
  test.describe(`${viewport.name} account recovery entry point`, () => {
    test.use({ storageState: blankStorage, viewport: viewport.size });

    test('shows account recovery from the login page', async ({ page }) => {
      await page.goto('/admin/login');
      await expect(page.getByRole('link', { name: /recover account/i })).toBeVisible();
      await page.getByRole('link', { name: /recover account/i }).click();
      await expect(page.getByRole('heading', { name: /recover account/i })).toBeVisible();
    });
  });
}

test.describe('Account recovery outcomes', () => {
  test.use({ storageState: blankStorage, viewport: { width: 1280, height: 900 } });

  test.afterEach(cleanupE2eFixtures);

  test('recovers a self-closed tenant administrator', async ({ page }) => {
    const fixture = await createE2eTenantFixture();
    await login(page, fixture.admin);
    await closeTenantAccount(page, fixture.tenant.name);
    await recoverAccount(page, fixture.admin.email, fixture.admin.password);

    await expect(page).toHaveURL(/\/admin\/login$/);
    await expect(page.locator('body')).toContainText(/account and tenant have been recovered/i);
  });

  test('shows a generic error for invalid recovery credentials', async ({ page }) => {
    await recoverAccount(page, `missing-${Date.now()}@example.com`, 'wrong-password');

    await expect(page.locator('body')).toContainText('The email or password is incorrect.');
  });

  test('requires the tenant name before closing the last administrator account', async ({ page }) => {
    const fixture = await createE2eTenantFixture();
    await login(page, fixture.admin);
    await page.goto('/admin/settings');
    await page.getByRole('button', { name: 'Close Account' }).click();
    await page.getByLabel('Type the tenant name or slug to confirm').fill('wrong-tenant');
    await page.getByRole('button', { name: 'Yes, Close My Account' }).click();

    await expect(page.locator('body')).toContainText(/selected.*tenant name.*invalid/i);
    await expect(page).toHaveURL(/\/admin\/settings$/);
  });

  test('denies recovery for an administrator-removed account', async ({ page }) => {
    const fixture = await createE2eTenantFixture({ author: true, adminRole: 'super_admin' });
    await login(page, fixture.admin);
    await page.goto('/admin/users');
    await page.getByRole('searchbox', { name: 'Search' }).fill(fixture.author?.email ?? '');
    const row = page.getByRole('row', { name: new RegExp(fixture.author?.email ?? '') });
    await row.getByRole('checkbox').check();
    await page.getByRole('button', { name: /delete/i }).click();
    await page.getByRole('button', { name: 'Delete', exact: true }).last().click();
    await expect(row).not.toBeVisible();

    await recoverAccount(page, fixture.author?.email ?? '', fixture.author?.password ?? '');
    await expect(page.locator('body')).toContainText(/removed by an administrator/i);
  });

  test('shows the deleted-tenant recovery denial', async ({ page }) => {
    const fixture = await createClosedTenantRecoveryFixture();
    await recoverAccount(page, fixture.author?.email ?? '', fixture.author?.password ?? '');

    await expect(page.locator('body')).toContainText(/tenant was closed/i);
  });

  test('redirects a recovered administrator to tenant setup', async ({ page }) => {
    const fixture = await createExpiredTenantRecoveryFixture();
    await recoverAccount(page, fixture.admin.email, fixture.admin.password);

    await expect(page).toHaveURL(/\/admin\/tenant-setup$/);
    await expect(page.getByRole('heading', { name: /tenant setup/i })).toBeVisible();
  });
});
