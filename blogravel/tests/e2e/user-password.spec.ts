import { test, expect } from '@playwright/test';

test.describe('User password generation', () => {
  test('generate button fills password field with a secure random password', async ({ page }) => {
    await page.goto('/admin/users/create');

    const passwordInput = page.locator('#form\\.password');
    await expect(passwordInput).toHaveValue('');

    await page.getByRole('button', { name: 'Generate secure password' }).click();
    await expect(passwordInput).not.toHaveValue('');

    const value = await passwordInput.inputValue();
    expect(value.length).toBeGreaterThanOrEqual(16);

    // regenerating produces a different value
    await page.getByRole('button', { name: 'Generate secure password' }).click();
    await expect
      .poll(async () => passwordInput.inputValue())
      .not.toBe(value);
  });

  test('generated password completes user creation', async ({ page }) => {
    const uniqueEmail = `generated-password-${Date.now()}@example.com`;

    await page.goto('/admin/users/create');

    await page.getByLabel('First name').fill('Generated');
    await page.getByLabel('Last name').fill('Password');
    await page.getByLabel('Email').fill(uniqueEmail);
    await page.getByRole('button', { name: 'Generate secure password' }).click();

    await page.locator('button.fi-select-input-btn').first().click();
    await page.getByRole('option', { name: 'Author' }).click();

    await page.getByRole('button', { name: 'Create', exact: true }).click();
    await page.waitForURL(/\/admin\/users\/.+\/edit/);

    await page.goto('/admin/users');
    await expect(page.locator('body')).toContainText(uniqueEmail);
  });
});
