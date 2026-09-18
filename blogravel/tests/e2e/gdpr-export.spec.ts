import { expect, test } from '@playwright/test';
import {
  cleanupE2eFixtures,
  createE2eTenantFixture,
  login,
  loginAs,
  waitForExportNotification,
} from './helpers';

const blankStorage = { cookies: [], origins: [] };

async function openExportAction(page: Parameters<typeof loginAs>[0]) {
  await page.goto('/admin/settings');
  await page.getByRole('button', { name: 'Export tenant data' }).click();
  await expect(page.getByLabel('Format')).toBeVisible();
}

async function queueExport(
  page: Parameters<typeof loginAs>[0],
  format: 'CSV' | 'XLSX' = 'CSV',
  tenantLabel?: string,
) {
  await openExportAction(page);
  const dialog = page.getByRole('dialog', { name: 'Export tenant data' });
  const selects = dialog.locator('select');
  if (tenantLabel) await selects.first().selectOption({ label: tenantLabel });
  await selects.last().selectOption(format.toLowerCase());
  await dialog.getByRole('button', { name: 'Submit' }).click();
  await expect(page.locator('body')).toContainText('Export queued');
  const body = await page.locator('body').innerText();
  const identifier = body.match(/Export ([0-9a-f-]+) will be available/i)?.[1];
  expect(identifier).toBeTruthy();
  await waitForExportNotification(identifier as string);

  return identifier as string;
}

for (const viewport of [
  { name: 'desktop', size: { width: 1280, height: 900 } },
  { name: 'mobile', size: { width: 390, height: 844 } },
]) {
  test.describe(`${viewport.name} GDPR export settings`, () => {
    test.use({ storageState: blankStorage, viewport: viewport.size });

    test.afterEach(cleanupE2eFixtures);

    test('keeps the export action usable at the viewport size', async ({ page }) => {
      const fixture = await createE2eTenantFixture();
      await login(page, fixture.admin);
      await openExportAction(page);
      await expect(page.getByLabel('Format')).toBeVisible();
      await expect(page.getByRole('dialog').getByRole('button', { name: 'Submit' })).toBeVisible();
    });
  });
}

test.describe('GDPR export authorization', () => {
  test.use({ storageState: blankStorage, viewport: { width: 1280, height: 900 } });

  test.afterEach(cleanupE2eFixtures);

  test('tenant admins can queue only their current tenant export and download it', async ({ page, browser }) => {
    const fixture = await createE2eTenantFixture();
    const unauthorizedFixture = await createE2eTenantFixture();
    await login(page, fixture.admin);
    const identifier = await queueExport(page, 'CSV');
    const download = page.waitForEvent('download');
    const navigation = page.goto(`/admin/tenant-exports/${identifier}`).catch((error: Error) => {
      if (!error.message.includes('Download is starting')) throw error;
    });
    const [file] = await Promise.all([download, navigation]);

    expect(file.suggestedFilename()).toBe(`tenant-export-${identifier}.zip`);

    const unauthorizedContext = await browser.newContext({ storageState: blankStorage });
    const unauthorizedPage = await unauthorizedContext.newPage();
    await login(unauthorizedPage, unauthorizedFixture.admin);
    const response = await unauthorizedPage.goto(`/admin/tenant-exports/${identifier}`);
    expect(response?.status()).toBe(403);
    await unauthorizedContext.close();
  });

  test('super admins can select a recoverable tenant and XLSX format', async ({ page, browser }) => {
    const fixture = await createE2eTenantFixture();
    await login(page, fixture.admin);
    await page.goto('/admin/settings');
    await page.getByRole('button', { name: 'Close Account' }).click();
    await page.getByLabel('Type the tenant name or slug to confirm').fill(fixture.tenant.name);
    await page.getByRole('button', { name: 'Yes, Close My Account' }).click();
    await page.waitForURL('**/admin/login');

    const superAdminContext = await browser.newContext({ storageState: blankStorage });
    const superAdminPage = await superAdminContext.newPage();
    await loginAs(superAdminPage, 'superAdmin');
    await queueExport(superAdminPage, 'XLSX', `${fixture.tenant.name} (recoverable)`);
    await superAdminContext.close();
  });

  test('tenant admins cannot select another tenant', async ({ page }) => {
    const fixture = await createE2eTenantFixture();
    await login(page, fixture.admin);
    await openExportAction(page);
    await expect(page.getByRole('dialog').getByLabel('Tenant')).toHaveCount(0);
    await expect(page.getByLabel('Format')).toHaveValue('csv');
  });
});
