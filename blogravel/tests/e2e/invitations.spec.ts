import { test, expect } from '@playwright/test';

test.describe('Invitation Management', () => {
  test('loads invitations list page', async ({ page }) => {
    const response = await page.goto('/admin/invitations');
    expect(response?.status()).toBe(200);
    await expect(page.locator('body')).toContainText('Invitations');
  });

  test('create email invitation', async ({ page }) => {
    await page.goto('/admin/invitations/create');

    // Select email invitation type via radio button
    await page.getByRole('radio', { name: 'Email Invitation' }).click();

    // Fill email
    await page.getByRole('textbox', { name: 'Email*' }).fill('test-invite@example.com');

    // Role defaults to Author - no change needed
    // Submit
    await page.getByRole('button', { name: 'Create', exact: true }).click();
    await page.waitForTimeout(3000);

    // Should see the invited email in list
    await expect(page.locator('body')).toContainText('test-invite@example.com');
  });

  test('create shareable link invitation', async ({ page }) => {
    await page.goto('/admin/invitations/create');

    // Select shareable link type
    await page.getByRole('radio', { name: 'Shareable Link' }).click();

    // Open role dropdown and select Editor
    const selectBtn = page.locator('button.fi-select-input-btn').first();
    await selectBtn.click();
    await page.waitForTimeout(500);
    await page.getByRole('option', { name: 'Editor' }).click();

    // Submit
    await page.getByRole('button', { name: 'Create', exact: true }).click();
    await page.waitForTimeout(3000);

    // Should see shareable link in list
    await expect(page.locator('body')).toContainText('Shareable Link');
  });

  test('delete invitation', async ({ page }) => {
    await page.goto('/admin/invitations');

    // Find a row with checkboxes and check it
    const checkbox = page.locator('input[type="checkbox"]').first();
    if (await checkbox.isVisible()) {
      await checkbox.check();

      // Click bulk delete button if visible
      const deleteBtn = page.locator('button:has-text("Delete")').first();
      if (await deleteBtn.isVisible()) {
        await deleteBtn.click();
        await page.waitForTimeout(2000);
      }
    }
  });
});
