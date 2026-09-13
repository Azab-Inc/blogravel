import { type Page } from '@playwright/test';

export const TEST_EMAIL = 'contact@azaber.com';
export const TEST_PASSWORD = 'password';

async function clearMailpit() {
  try {
    await fetch('http://localhost:8025/api/v1/messages', { method: 'DELETE' });
  } catch {}
}

async function getCodeFromMailpit(): Promise<string | null> {
  for (let i = 0; i < 20; i++) {
    try {
      const response = await fetch('http://localhost:8025/api/v1/messages?limit=1');
      const data = await response.json();
      if (data.messages && data.messages.length > 0) {
        const msgId = data.messages[0].ID;
        const rawResponse = await fetch(`http://localhost:8025/api/v1/message/${msgId}`);
        const msgData = await rawResponse.json();
        const match = (msgData.Text || '').match(/(\d{6})/);
        if (match) return match[1];
      }
    } catch {}
    await new Promise(r => setTimeout(r, 1000));
  }
  return null;
}

async function fillMfaCode(page: Page, code: string) {
  const inputs = page.locator('.fi-one-time-code-input-digit');
  const count = await inputs.count();
  if (count === 6) {
    for (let i = 0; i < 6; i++) {
      await inputs.nth(i).click();
      await inputs.nth(i).pressSequentially(code[i], { delay: 50 });
    }
  }
}

async function isOnMfaPage(page: Page): Promise<boolean> {
  const url = page.url();
  if (url.includes('multi-factor')) return true;
  const h1 = await page.locator('h1').textContent().catch(() => '');
  if (h1.includes('Verify your identity')) return true;
  if (h1.includes('Set up')) return true;
  return false;
}

async function handleMfaChallenge(page: Page) {
  if (!(await isOnMfaPage(page))) return;

  if (page.url().includes('set-up')) {
    const setupBtn = page.locator('button:has-text("Set up")').first();
    if (await setupBtn.count() > 0) {
      await setupBtn.click();
      await page.waitForTimeout(3000);
    }
  }

  const code = await getCodeFromMailpit();
  if (!code) return;

  await fillMfaCode(page, code);

  const confirmBtn = page.locator('button:has-text("Confirm sign in")');
  if (await confirmBtn.count() > 0) {
    await confirmBtn.click();
    await page.waitForTimeout(5000);
  }
}

export async function login(page: Page) {
  await clearMailpit();
  await page.goto('/admin/login');

  await page.locator('input[type="email"]').click();
  await page.locator('input[type="email"]').pressSequentially(TEST_EMAIL, { delay: 10 });
  await page.locator('input[type="password"]').click();
  await page.locator('input[type="password"]').pressSequentially(TEST_PASSWORD, { delay: 10 });

  await Promise.all([
    page.waitForNavigation({ timeout: 15000 }).catch(() => {}),
    page.locator('button[type="submit"]').click(),
  ]);

  await handleMfaChallenge(page);

  if (await isOnMfaPage(page)) {
    await handleMfaChallenge(page);
  }

  try {
    await page.waitForURL((url) => {
      const path = url.pathname;
      return path === '/admin' || path === '/admin/';
    }, { timeout: 15000 });
  } catch {}
}
