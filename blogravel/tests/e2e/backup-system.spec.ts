import { test, expect } from '@playwright/test';

const ts = () => Date.now();

test.describe('Backup Rules', () => {
  test('creates and edits a backup rule with cron scheduling', async ({ page }) => {
    const name = `Test Backup Rule ${ts()}`;

    // Create
    await page.goto('/admin/backup-rules/create');
    await page.waitForURL('**/admin/backup-rules/create');

    await page.getByLabel('Name').fill(name);
    await page.getByLabel('Advanced cron expression').check();
    await page.locator('#form\\.schedule').fill('0 3 * * *');

    await page.getByRole('button', { name: 'Create', exact: true }).click();
    await page.waitForURL(/\/admin\/backup-rules\/.+\/edit/, { timeout: 15000 });
    await expect(page.getByLabel('Name')).toHaveValue(name);

    // Edit
    const updatedName = `${name} Updated`;
    await page.getByLabel('Name').fill(updatedName);
    await page.getByRole('button', { name: 'Save changes' }).click();
    await page.waitForTimeout(2000);
    await expect(page.getByLabel('Name')).toHaveValue(updatedName);

  });

  test('creates a simple interval schedule', async ({ page }) => {
    const name = `Simple Schedule Test ${ts()}`;

    await page.goto('/admin/backup-rules/create');
    await page.waitForURL('**/admin/backup-rules/create');

    await page.getByLabel('Name').fill(name);
    await expect(page.getByLabel('Email recipient')).toHaveValue('contact@azaber.com');
    await page.getByLabel('Run every').fill('2');
    await page.getByLabel('Unit').selectOption('week');
    await page.locator('#form\\.schedule_weekday').selectOption('1');
    await page.getByRole('button', { name: 'Create', exact: true }).click();
    await page.waitForURL(/\/admin\/backup-rules\/.+\/edit/, { timeout: 15000 });

    await expect(page.getByLabel('Simple schedule')).toBeChecked();
    await expect(page.getByLabel('Run every')).toHaveValue('2');
    await expect(page.getByLabel('Unit')).toHaveValue('week');

  });

  test('toggle backup rule enabled state', async ({ page }) => {
    const name = `Toggle Test ${ts()}`;

    await page.goto('/admin/backup-rules/create');
    await page.waitForURL('**/admin/backup-rules/create');

    await page.getByLabel('Name').fill(name);
    await page.getByRole('button', { name: 'Create', exact: true }).click();
    await page.waitForURL(/\/admin\/backup-rules\/.+\/edit/, { timeout: 15000 });

    // Toggle enabled off
    const toggle = page.getByLabel('Enabled');
    await toggle.uncheck();
    await page.getByRole('button', { name: 'Save changes' }).click();
    await page.waitForTimeout(2000);
    await expect(toggle).not.toBeChecked();

    // Toggle enabled back on
    await toggle.check();
    await page.getByRole('button', { name: 'Save changes' }).click();
    await page.waitForTimeout(2000);
    await expect(toggle).toBeChecked();

  });

  test('FTP fields appear when destination is FTP or Both', async ({ page }) => {
    const name = `FTP Test ${ts()}`;

    await page.goto('/admin/backup-rules/create');
    await page.waitForURL('**/admin/backup-rules/create');

    await page.getByLabel('Name').fill(name);

    // FTP section should be hidden by default (destination = Email)
    const ftpSection = page.getByText('FTP/SFTP Settings');
    await expect(ftpSection).not.toBeVisible();

    // Select Both destination
    await page.getByLabel('Destination').selectOption('both');
    await page.waitForTimeout(500);
    await expect(ftpSection).toBeVisible();

    // Select FTP only
    await page.getByLabel('Destination').selectOption('ftp');
    await page.waitForTimeout(500);
    await expect(ftpSection).toBeVisible();

    // Select Email only - FTP should hide
    await page.getByLabel('Destination').selectOption('email');
    await page.waitForTimeout(500);
    await expect(ftpSection).not.toBeVisible();

    await page.getByRole('button', { name: 'Create', exact: true }).click();
    await page.waitForURL(/\/admin\/backup-rules\/.+\/edit/, { timeout: 15000 });
  });

  test('backup rules list shows in admin', async ({ page }) => {
    await page.goto('/admin/backup-rules');
    await page.waitForURL('**/admin/backup-rules');
    await expect(page.getByRole('heading', { name: 'Backup Rules' })).toBeVisible();
  });
});

test.describe('Backup History', () => {
  test('backup history page loads', async ({ page }) => {
    await page.goto('/admin/backups');
    await page.waitForURL('**/admin/backups');
    await expect(page.getByRole('heading', { name: 'Backups', exact: true })).toBeVisible();
  });
});
