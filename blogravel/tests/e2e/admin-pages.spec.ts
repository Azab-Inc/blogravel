import { test, expect } from '@playwright/test';

const PAGES = [
  { name: 'Dashboard', url: '/admin', contains: 'Dashboard' },
  { name: 'Posts', url: '/admin/posts', contains: 'Posts' },
  { name: 'Pages', url: '/admin/pages', contains: 'Pages' },
  { name: 'Categories', url: '/admin/categories', contains: 'Categories' },
  { name: 'Tags', url: '/admin/tags', contains: 'Tags' },
  { name: 'Comments', url: '/admin/comments', contains: 'Comments' },
  { name: 'Media', url: '/admin/media', contains: 'Media' },
  { name: 'Users', url: '/admin/users', contains: 'Users' },
  { name: 'API Keys', url: '/admin/api-keys', contains: 'API Keys' },
  { name: 'Settings', url: '/admin/settings', contains: 'Settings' },
  { name: 'AI Settings', url: '/admin/ai-settings', contains: 'AI Settings' },
  { name: 'Import WordPress', url: '/admin/import-word-press', contains: 'Import WordPress' },
  { name: 'Invitations', url: '/admin/invitations', contains: 'Invitations' },
];

test.describe('Admin Pages Smoke Tests', () => {
  for (const pageData of PAGES) {
    test(`loads ${pageData.name} page`, async ({ page }) => {
      const response = await page.goto(pageData.url);
      expect(response?.status()).toBe(200);
      await expect(page.locator('body')).toContainText(pageData.contains);
    });
  }

  test('lays out settings sections in columns below the large breakpoint', async ({ page }) => {
    await page.setViewportSize({ width: 945, height: 917 });
    await page.goto('/admin/settings');

    const sections = page.locator('.fi-sc-section > .fi-section');
    const permissions = await sections.nth(0).boundingBox();
    const site = await sections.nth(1).boundingBox();
    const account = await sections.nth(2).boundingBox();

    expect(permissions).not.toBeNull();
    expect(site).not.toBeNull();
    expect(account).not.toBeNull();
    expect(Math.abs((permissions?.x ?? 0) - (site?.x ?? 0))).toBeGreaterThan(100);
    expect(account?.width ?? 0).toBeGreaterThan((permissions?.width ?? 0) * 1.8);
  });
});
