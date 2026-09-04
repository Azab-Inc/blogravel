import { test as setup } from '@playwright/test';
import { login } from './helpers';

setup('login and save state', async ({ page }) => {
  await login(page);
  await page.context().storageState({ path: 'tests/e2e/.auth/admin.json' });
});
