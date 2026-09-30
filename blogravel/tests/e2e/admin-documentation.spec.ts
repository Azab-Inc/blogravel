import { expect, test } from '@playwright/test';

test.describe('Admin API documentation', () => {
  test('renders only the authenticated tenant documentation', async ({ page }) => {
    await page.goto('/admin/api-documentation');

    await expect(page.getByRole('heading', { name: 'API Documentation' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Current tenant' })).toBeVisible();
    await expect(page.locator('.blogravel-api-docs__host code')).toBeVisible();
    await expect(page.locator('#selectedTenantSlug')).toHaveCount(0);
    await expect(page.locator('.docs-section')).toHaveCount(7);
    await expect(page.locator('[data-copy-code]')).not.toHaveCount(0);
  });

  test('preserves dark mode across the admin documentation shell', async ({ page }) => {
    await page.addInitScript(() => localStorage.setItem('theme', 'dark'));
    await page.goto('/admin/api-documentation');

    await expect(page.locator('html')).toHaveClass(/dark/);
    await expect(page.locator('body')).toHaveCSS('background-color', 'rgb(15, 23, 42)');
    await expect(page.locator('.fi-sidebar')).toHaveCSS('background-color', 'rgb(17, 24, 39)');
    await expect(page.locator('.fi-topbar')).toHaveCSS('background-color', 'rgb(15, 23, 42)');
    await expect(page.locator('.blogravel-api-docs__hero h2')).toHaveCSS('color', 'rgb(248, 250, 252)');
    await expect(page.locator('.blogravel-api-docs__eyebrow')).toHaveCSS('color', 'rgb(238, 175, 98)');
    await expect(page.locator('.blogravel-api-docs .docs-section a').first()).toHaveCSS('color', 'rgb(238, 175, 98)');
    await expect(page.locator('.blogravel-api-docs .docs-section').first()).toHaveCSS('background-color', 'rgb(17, 24, 39)');
  });
});
