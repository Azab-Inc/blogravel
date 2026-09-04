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
];

test.describe('Admin Pages Smoke Tests', () => {
  for (const pageData of PAGES) {
    test(`loads ${pageData.name} page`, async ({ page }) => {
      const response = await page.goto(pageData.url);
      expect(response?.status()).toBe(200);
      await expect(page.locator('body')).toContainText(pageData.contains);
    });
  }
});
