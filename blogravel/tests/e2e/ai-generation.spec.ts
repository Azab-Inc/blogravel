import { test, expect } from '@playwright/test';

test.describe('AI Post Generation', () => {
  test('opens AI generation modal and fills form', async ({ page }) => {
    await page.goto('/admin/posts/create');
    await page.waitForURL('**/admin/posts/create');

    const aiButton = page.locator('button:has-text("Generate with AI")');
    await expect(aiButton).toBeVisible({ timeout: 10000 });
    await aiButton.click();

    await page.waitForTimeout(2000);

    const promptInput = page.getByLabel('Prompt');
    await expect(promptInput).toBeVisible({ timeout: 10000 });
    await promptInput.fill('Write a blog post about Laravel testing best practices');

    const modelInput = page.getByLabel('Model');
    await expect(modelInput).toBeVisible();
    await modelInput.fill('gpt-4o');

    const lengthInput = page.getByLabel('Length Value');
    await expect(lengthInput).toBeVisible();
    await expect(lengthInput).toHaveValue('4');

    const submitBtn = page.locator('.fi-modal-open button[type="submit"]').first();
    await expect(submitBtn).toBeVisible();
  });
});
