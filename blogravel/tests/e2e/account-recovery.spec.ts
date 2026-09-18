import { expect, test } from '@playwright/test';
import { loginAs, TEST_USERS } from './helpers';

const blankStorage = { cookies: [], origins: [] };

async function recoverAccount(page: Parameters<typeof loginAs>[0], email: string, password: string) {
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
  test.describe.configure({ mode: 'serial' });

  test('recovers a self-closed tenant administrator', async ({ page }) => {
    await loginAs(page, 'tenantAdmin');
    await closeTenantAccount(page, 'acme.io');
    await recoverAccount(page, TEST_USERS.tenantAdmin.email, TEST_USERS.tenantAdmin.password);

    await expect(page).toHaveURL(/\/admin\/login$/);
    await expect(page.locator('body')).toContainText(/account and tenant have been recovered/i);
  });

  test('shows a generic error for invalid recovery credentials', async ({ page }) => {
    await recoverAccount(page, `missing-${Date.now()}@example.com`, 'wrong-password');

    await expect(page.locator('body')).toContainText('The email or password is incorrect.');
  });

  test('requires the tenant name before closing the last administrator account', async ({ page }) => {
    await loginAs(page, 'tenantAdmin');
    await page.goto('/admin/settings');
    await page.getByRole('button', { name: 'Close Account' }).click();
    await page.getByLabel('Type the tenant name or slug to confirm').fill('wrong-tenant');
    await page.getByRole('button', { name: 'Yes, Close My Account' }).click();

    await expect(page.locator('body')).toContainText('Type the tenant name or slug to confirm closure.');
    await expect(page).toHaveURL(/\/admin\/settings$/);
  });

  test('denies recovery for an administrator-removed account', async ({ page }) => {
    await loginAs(page, 'superAdmin');
    await page.goto('/admin/users');
    await page.getByRole('searchbox', { name: 'Search' }).fill('author@acme.io');
    const row = page.getByRole('row', { name: /author@acme\.io/ });
    await row.getByRole('checkbox').check();
    await page.getByRole('button', { name: /delete/i }).click();
    await page.getByRole('button', { name: 'Delete', exact: true }).last().click();
    await expect(row).not.toBeVisible();

    await recoverAccount(page, 'author@acme.io', TEST_USERS.tenantAdmin.password);
    await expect(page.locator('body')).toContainText(/removed by an administrator/i);
  });

  test('shows the deleted-tenant recovery denial', async ({ page }) => {
    await loginAs(page, 'otherTenantAdmin');
    await closeTenantAccount(page, 'globex.net');
    await recoverAccount(page, 'author@globex.net', TEST_USERS.tenantAdmin.password);

    await expect(page.locator('body')).toContainText(/tenant was closed/i);
  });

  test('redirects a recovered administrator to tenant setup', async ({ page }) => {
    await recoverAccount(page, 'recovery-needs-tenant@example.com', TEST_USERS.tenantAdmin.password);

    await expect(page).toHaveURL(/\/admin\/tenant-setup$/);
    await expect(page.getByRole('heading', { name: /tenant setup/i })).toBeVisible();
  });
});
