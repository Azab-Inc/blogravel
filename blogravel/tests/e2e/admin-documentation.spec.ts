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
});
