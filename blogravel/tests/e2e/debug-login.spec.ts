import { test, expect } from '@playwright/test';

test.use({ storageState: { cookies: [], origins: [] } });

test('successful login then immediate check', async ({ page }) => {
  test.setTimeout(120000);
  
  await page.goto('/admin/login');
  
  await page.locator('input[type="email"]').fill('contact@azaber.com');
  await page.locator('input[type="password"]').fill('password');
  
  console.log('Step 1: Sending Livewire POST with correct password...');
  const start = Date.now();
  
  await Promise.all([
    page.waitForResponse(resp => resp.url().includes('/livewire') && resp.request().method() === 'POST', { timeout: 30000 }),
    page.locator('button[type="submit"]').click(),
  ]);
  const elapsed = Date.now() - start;
  console.log(`Livewire POST complete in ${elapsed}ms`);
  console.log('Current URL:', page.url());
  
  // Wait 2s for any state to settle
  await page.waitForTimeout(2000);
  console.log('After 2s URL:', page.url());
  
  console.log('Step 2: Navigating to debug route...');
  const start2 = Date.now();
  const response = await page.goto('/debug/session-check');
  const elapsed2 = Date.now() - start2;
  const body = await response?.text();
  console.log(`Debug route: ${response?.status()} in ${elapsed2}ms`);
  console.log('Body:', body);
});
