import { test, expect } from '@playwright/test';

test.describe('API documentation', () => {
  test('renders Markdown usage examples for all supported client languages', async ({ page }) => {
    await page.goto('/docs/api?tenant=acmeio');

    await expect(page.getByRole('heading', { name: 'Build with the Blogravel API.' })).toBeVisible();
    await expect(page.locator('.docs-tenant-form')).toBeVisible();
    await expect(page.locator('code.language-typescript').first()).toBeVisible();
    await expect(page.locator('code.language-javascript').first()).toBeVisible();
    await expect(page.locator('code.language-php').first()).toBeVisible();
    await expect(page.locator('code.language-csharp').first()).toBeVisible();
    await expect(page.locator('.docs-nav').getByRole('link', { name: 'Authentication' })).toHaveAttribute('href', '#authentication');
    await expect(page.locator('[data-copy-code]')).not.toHaveCount(0);
    await page.locator('[data-copy-code]').first().click();
    await expect(page.locator('[data-copy-code]').first()).toHaveText('Copied');
  });

  test('keeps the documentation readable on mobile', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 667 });
    await page.goto('/docs/api?tenant=acmeio');

    await expect(page.locator('.docs-shell')).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Public endpoints' })).toBeVisible();
    await expect(page.locator('.docs-tenant-form')).toBeVisible();
  });

  test('prompts for a tenant before generating examples', async ({ page }) => {
    await page.goto('/docs/api');

    await expect(page.getByLabel('Tenant slug')).toBeVisible();
    await expect(page.getByText('Enter your tenant slug to view generated documentation.')).toBeVisible();
    await expect(page.locator('[data-copy-code]')).toHaveCount(0);
  });
});
