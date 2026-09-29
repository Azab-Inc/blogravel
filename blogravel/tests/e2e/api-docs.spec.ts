import { test, expect } from '@playwright/test';

test.describe('API documentation', () => {
  test('renders Markdown usage examples for all supported client languages', async ({ page }) => {
    await page.goto('/docs/api');

    await expect(page.getByRole('heading', { name: 'Blogravel API' })).toBeVisible();
    await expect(page.locator('code.language-typescript').first()).toBeVisible();
    await expect(page.locator('code.language-javascript').first()).toBeVisible();
    await expect(page.locator('code.language-php').first()).toBeVisible();
    await expect(page.locator('code.language-csharp').first()).toBeVisible();
    await expect(page.locator('.docs-nav').getByRole('link', { name: 'Authentication' })).toHaveAttribute('href', '#authentication');
  });

  test('keeps the documentation readable on mobile', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 667 });
    await page.goto('/docs/api');

    await expect(page.locator('.docs-shell')).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Public endpoints' })).toBeVisible();
  });
});
