import { test, expect } from '@playwright/test';

test.describe('WordPress Import', () => {
  test('loads import page', async ({ page }) => {
    await page.goto('/admin/import-word-press');
    await page.waitForURL('**/admin/import-word-press');
    await expect(page.locator('body')).toContainText('Import WordPress');
  });

  test('upload WXR file and start import', async ({ page }) => {
    await page.goto('/admin/import-word-press');
    await page.waitForURL('**/admin/import-word-press');

    const fileInput = page.locator('input[type="file"]');
    await fileInput.setInputFiles('tests/fixtures/test-import.xml');
    await page.waitForTimeout(1000);

    const importButton = page.getByRole('button', { name: 'Start Import' });
    await expect(importButton).toBeVisible({ timeout: 5000 });
    await importButton.click();

    await page.waitForTimeout(3000);
    await expect(page.locator('.fi-notification, [role="alert"]').first()).toBeVisible({ timeout: 10000 });
  });
});
