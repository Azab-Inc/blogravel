import { test, expect } from '@playwright/test';

const ts = () => Date.now();

test.describe('Admin CRUD Operations', () => {
  test('create, edit, and delete a post', async ({ page }) => {
    const title = `Test E2E Post ${ts()}`;
    await page.goto('/admin/posts/create');
    await page.waitForURL('**/admin/posts/create');

    await page.getByLabel('Title').fill(title);
    await page.getByRole('textbox', { name: 'Content*' }).fill('This is test content.');
    await page.getByLabel('Status').selectOption('draft');

    await page.getByRole('button', { name: 'Create', exact: true }).click();
    await page.waitForURL(/\/admin\/posts\/.+\/edit/, { timeout: 15000 });
    await expect(page.getByLabel('Title')).toHaveValue(title);

    const updatedTitle = `${title} Updated`;
    await page.getByLabel('Title').fill(updatedTitle);
    await page.getByRole('button', { name: 'Save changes' }).click();
    await page.waitForTimeout(2000);
    await expect(page.getByLabel('Title')).toHaveValue(updatedTitle);

    await page.getByRole('button', { name: 'Delete' }).click();
    await page.waitForTimeout(1000);
    await page.getByRole('button', { name: 'Delete', exact: true }).last().click();
    await page.waitForURL('**/admin/posts', { timeout: 15000 });
  });

  test('create and delete a category', async ({ page }) => {
    const name = `Test E2E Cat ${ts()}`;
    await page.goto('/admin/categories/create');
    await page.waitForURL('**/admin/categories/create');

    await page.getByLabel('Name').fill(name);
    await page.getByRole('button', { name: 'Create', exact: true }).click();
    await page.waitForURL(/\/admin\/categories\/.+\/edit/, { timeout: 15000 });
    await expect(page.getByLabel('Name')).toHaveValue(name);

    await page.getByRole('button', { name: 'Delete' }).click();
    await page.waitForTimeout(1000);
    await page.getByRole('button', { name: 'Delete', exact: true }).last().click();
    await page.waitForURL('**/admin/categories', { timeout: 15000 });
  });

  test('create and delete a tag', async ({ page }) => {
    const name = `Test E2E Tag ${ts()}`;
    await page.goto('/admin/tags/create');
    await page.waitForURL('**/admin/tags/create');

    await page.getByLabel('Name').fill(name);
    await page.getByRole('button', { name: 'Create', exact: true }).click();
    await page.waitForURL(/\/admin\/tags\/.+\/edit/, { timeout: 15000 });
    await expect(page.getByLabel('Name')).toHaveValue(name);

    await page.getByRole('button', { name: 'Delete' }).click();
    await page.waitForTimeout(1000);
    await page.getByRole('button', { name: 'Delete', exact: true }).last().click();
    await page.waitForURL('**/admin/tags', { timeout: 15000 });
  });

  test('create and delete a page', async ({ page }) => {
    const title = `Test E2E Page ${ts()}`;
    await page.goto('/admin/pages/create');
    await page.waitForURL('**/admin/pages/create');

    await page.getByLabel('Title').fill(title);
    await page.getByRole('textbox', { name: 'Content*' }).fill('Page test content.');
    await page.getByLabel('Status').selectOption('draft');

    await page.getByRole('button', { name: 'Create', exact: true }).click();
    await page.waitForURL(/\/admin\/pages\/.+\/edit/, { timeout: 15000 });
    await expect(page.getByLabel('Title')).toHaveValue(title);

    await page.getByRole('button', { name: 'Delete' }).click();
    await page.waitForTimeout(1000);
    await page.getByRole('button', { name: 'Delete', exact: true }).last().click();
    await page.waitForURL('**/admin/pages', { timeout: 15000 });
  });
});
