import { expect, test } from '@playwright/test';
import { loginAs, TEST_USERS } from './helpers';

const blankStorage = { cookies: [], origins: [] };

async function openExportAction(page: Parameters<typeof loginAs>[0]) {
  await page.goto('/admin/settings');
  await page.getByRole('button', { name: 'Export tenant data' }).click();
  await expect(page.getByLabel('Format')).toBeVisible();
}

async function queueExport(page: Parameters<typeof loginAs>[0], format: 'CSV' | 'XLSX' = 'CSV') {
  await openExportAction(page);
  await page.getByLabel('Format').selectOption(format.toLowerCase());
  await page.getByRole('button', { name: 'Export tenant data', exact: true }).last().click();
  await expect(page.locator('body')).toContainText('Export queued');
  const body = await page.locator('body').innerText();
  const identifier = body.match(/Export ([0-9a-f-]+) will be available/i)?.[1];
  expect(identifier).toBeTruthy();

  return identifier as string;
}

for (const viewport of [
  { name: 'desktop', size: { width: 1280, height: 900 } },
  { name: 'mobile', size: { width: 390, height: 844 } },
]) {
  test.describe(`${viewport.name} GDPR export settings`, () => {
    test.use({ storageState: blankStorage, viewport: viewport.size });

    test('keeps the export action usable at the viewport size', async ({ page }) => {
      await loginAs(page, 'tenantAdmin');
      await openExportAction(page);
      await expect(page.getByLabel('Format')).toBeVisible();
      await expect(page.getByRole('button', { name: 'Export tenant data', exact: true }).last()).toBeVisible();
    });
  });
}

test.describe('GDPR export authorization', () => {
  test.use({ storageState: blankStorage, viewport: { width: 1280, height: 900 } });

  test('tenant admins can queue only their current tenant export and download it', async ({ page, browser }) => {
    await loginAs(page, 'tenantAdmin');
    const identifier = await queueExport(page, 'CSV');
    const download = page.waitForEvent('download');
    await page.goto(`/admin/tenant-exports/${identifier}`);
    const file = await download;

    expect(file.suggestedFilename()).toBe(`tenant-export-${identifier}.zip`);

    const unauthorizedContext = await browser.newContext({ storageState: blankStorage });
    const unauthorizedPage = await unauthorizedContext.newPage();
    await loginAs(unauthorizedPage, 'otherTenantAdmin');
    const response = await unauthorizedPage.goto(`/admin/tenant-exports/${identifier}`);
    expect(response?.status()).toBe(403);
    await unauthorizedContext.close();
  });

  test('super admins can select a recoverable tenant and XLSX format', async ({ page }) => {
    await loginAs(page, 'otherTenantAdmin');
    await page.goto('/admin/settings');
    await page.getByRole('button', { name: 'Close Account' }).click();
    await page.getByLabel('Type the tenant name or slug to confirm').fill('globex.net');
    await page.getByRole('button', { name: 'Yes, Close My Account' }).click();
    await page.waitForURL('**/admin/login');

    await loginAs(page, 'superAdmin');
    await openExportAction(page);
    await expect(page.getByLabel('Tenant')).toContainText('globex.net (recoverable)');
    await page.getByLabel('Tenant').selectOption({ label: 'globex.net (recoverable)' });
    await page.getByLabel('Format').selectOption('xlsx');
    await page.getByRole('button', { name: 'Export tenant data', exact: true }).last().click();
    await expect(page.locator('body')).toContainText('Export queued');
  });

  test('tenant admins cannot select another tenant', async ({ page }) => {
    await loginAs(page, 'tenantAdmin');
    await openExportAction(page);
    await expect(page.getByLabel('Tenant')).toHaveCount(0);
    await expect(page.getByLabel('Format')).toHaveValue('csv');
  });
});
