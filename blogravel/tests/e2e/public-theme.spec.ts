import { test, expect } from '@playwright/test';

const HOME_URL = 'http://localhost:8000/acmeio';
const PUBLIC_PATHS = ['/', '/post/omnis-qui-assumenda-nisi-in', '/category/howard-walker', '/subscribe', '/contact'];

test.describe('Public Theme Modes', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto(HOME_URL);
    await page.evaluate(() => localStorage.removeItem('theme'));
    await page.reload();
  });

  test('defaults to light mode', async ({ page }) => {
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'light');
    await expect(page.locator('body')).toHaveCSS('background-color', 'rgb(255, 248, 237)');
  });

  test('toggles with the keyboard and persists the preference', async ({ page }) => {
    const toggle = page.getByRole('button', { name: 'Switch to dark mode' });
    await toggle.focus();
    await page.keyboard.press('Enter');

    await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
    await expect(page.getByRole('button', { name: 'Switch to light mode' })).toBeFocused();
    await page.reload();
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
  });

  test('applies the saved mode across representative public pages', async ({ page }) => {
    for (const path of PUBLIC_PATHS) {
      await page.goto(`${HOME_URL}${path}`);
      await page.evaluate(() => localStorage.setItem('theme', 'dark'));
      await page.reload();
      await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
    }
  });
});
