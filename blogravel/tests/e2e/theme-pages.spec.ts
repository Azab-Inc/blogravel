import { test, expect } from '@playwright/test';

test.describe('Theme Home Page', () => {
  test('renders the home page with tenant name', async ({ page }) => {
    const response = await page.goto('/?tenant=1');
    expect(response?.status()).toBe(200);
    
    await expect(page.locator('header h1')).toBeVisible();
    await expect(page.locator('main')).toBeVisible();
  });

  test('displays navigation links', async ({ page }) => {
    await page.goto('/?tenant=1');
    
    await expect(page.locator('nav a:has-text("Home")')).toBeVisible();
    await expect(page.locator('nav a:has-text("Subscribe")')).toBeVisible();
    await expect(page.locator('nav a:has-text("Contact")')).toBeVisible();
  });

  test('has RSS/Atom auto-discovery links', async ({ page }) => {
    await page.goto('/?tenant=1');
    
    const rssLink = page.locator('link[type="application/rss+xml"]');
    const atomLink = page.locator('link[type="application/atom+xml"]');
    const jsonFeedLink = page.locator('link[type="application/feed+json"]');
    
    await expect(rssLink).toHaveCount(1);
    await expect(atomLink).toHaveCount(1);
    await expect(jsonFeedLink).toHaveCount(1);
  });

  test('displays footer with Blogravel link', async ({ page }) => {
    await page.goto('/?tenant=1');
    
    await expect(page.locator('footer')).toContainText('Blogravel');
    await expect(page.locator('footer a[href="https://blogravel.com"]')).toBeVisible();
  });
});

test.describe('Theme Responsive Navigation', () => {
  test('shows hamburger menu on mobile', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 667 });
    await page.goto('/?tenant=1');
    
    const navToggle = page.locator('.nav-toggle');
    await expect(navToggle).toBeVisible();
    
    const nav = page.locator('header nav');
    await expect(nav).not.toBeVisible();
    
    await navToggle.click();
    await expect(nav).toBeVisible();
  });

  test('shows nav directly on desktop', async ({ page }) => {
    await page.setViewportSize({ width: 1024, height: 768 });
    await page.goto('/?tenant=1');
    
    const nav = page.locator('header nav');
    await expect(nav).toBeVisible();
    
    const navToggle = page.locator('.nav-toggle');
    await expect(navToggle).not.toBeVisible();
  });
});

test.describe('Theme Subscribe Page', () => {
  test('renders the subscribe form', async ({ page }) => {
    const response = await page.goto('/subscribe?tenant=1');
    expect(response?.status()).toBe(200);
    
    await expect(page.locator('h1:has-text("Subscribe")')).toBeVisible();
    await expect(page.locator('input[type="email"]')).toBeVisible();
    await expect(page.locator('button[type="submit"]:has-text("Subscribe")')).toBeVisible();
  });

  test('subscribe form has proper accessibility', async ({ page }) => {
    await page.goto('/subscribe?tenant=1');
    
    const emailInput = page.locator('input[type="email"]');
    await expect(emailInput).toHaveAttribute('required', '');
    await expect(emailInput).toHaveAttribute('placeholder', 'you@example.com');
    
    const label = page.locator('label[for="email"]');
    await expect(label).toBeVisible();
  });
});

test.describe('Theme Contact Page', () => {
  test('renders the contact form', async ({ page }) => {
    const response = await page.goto('/contact?tenant=1');
    expect(response?.status()).toBe(200);
    
    await expect(page.locator('h1:has-text("Contact")')).toBeVisible();
    await expect(page.locator('input[name="name"]')).toBeVisible();
    await expect(page.locator('input[name="email"]')).toBeVisible();
    await expect(page.locator('textarea[name="message"]')).toBeVisible();
    await expect(page.locator('button[type="submit"]:has-text("Send Message")')).toBeVisible();
  });

  test('contact form has proper accessibility', async ({ page }) => {
    await page.goto('/contact?tenant=1');
    
    const nameInput = page.locator('input[name="name"]');
    await expect(nameInput).toHaveAttribute('required', '');
    
    const emailInput = page.locator('input[name="email"]');
    await expect(emailInput).toHaveAttribute('required', '');
    
    const messageInput = page.locator('textarea[name="message"]');
    await expect(messageInput).toHaveAttribute('required', '');
    
    const nameLabel = page.locator('label[for="name"]');
    await expect(nameLabel).toBeVisible();
    
    const emailLabel = page.locator('label[for="email"]');
    await expect(emailLabel).toBeVisible();
    
    const messageLabel = page.locator('label[for="message"]');
    await expect(messageLabel).toBeVisible();
  });
});

test.describe('Theme 404 Handling', () => {
  test('returns 404 for non-existent tenant', async ({ page }) => {
    const response = await page.goto('/?tenant=non-existent');
    expect(response?.status()).toBe(404);
  });

  test('returns 404 for non-existent post', async ({ page }) => {
    const response = await page.goto('/post/non-existent-slug?tenant=1');
    expect(response?.status()).toBe(404);
  });
});

test.describe('Theme Layout', () => {
  test('has proper meta viewport tag', async ({ page }) => {
    await page.goto('/?tenant=1');
    
    const viewport = page.locator('meta[name="viewport"]');
    await expect(viewport).toHaveAttribute('content', 'width=device-width, initial-scale=1');
  });

  test('has proper lang attribute', async ({ page }) => {
    await page.goto('/?tenant=1');
    
    const html = page.locator('html');
    await expect(html).toHaveAttribute('lang', 'en');
  });

  test('has proper title', async ({ page }) => {
    await page.goto('/?tenant=1');
    
    await expect(page).toHaveTitle(/.+/);
  });
});
