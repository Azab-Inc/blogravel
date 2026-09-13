import { expect, test as setup } from '@playwright/test';
import { login } from './helpers';

setup('login and save state', async ({ page }) => {
  await login(page);
  const cookies = await page.context().cookies('http://blogravel.com:8000');
  const sessionCookie = cookies.find(cookie => cookie.name === 'blogravel-session');

  if (!sessionCookie) {
    throw new Error('Expected the login response to emit the session cookie.');
  }

  expect(sessionCookie.domain).toBe('.blogravel.com');
  await page.context().storageState({ path: 'tests/e2e/.auth/admin.json' });
});
