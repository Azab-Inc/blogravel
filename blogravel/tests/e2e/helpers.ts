import { execFile } from 'node:child_process';
import { randomUUID } from 'node:crypto';
import { promisify } from 'node:util';
import { expect, type Page } from '@playwright/test';

const execFileAsync = promisify(execFile);

export const TEST_EMAIL = 'contact@azaber.com';
export const TEST_PASSWORD = 'password';

export const TEST_USERS = {
  superAdmin: { email: TEST_EMAIL, password: TEST_PASSWORD },
  tenantAdmin: { email: 'admin@acme.io', password: 'password' },
  otherTenantAdmin: { email: 'admin@globex.net', password: 'password' },
} as const;

export type TestUserRole = keyof typeof TEST_USERS;

export type TestCredentials = {
  id?: string;
  email: string;
  password: string;
};

export type E2eTenantFixture = {
  tenant: {
    id: string;
    name: string;
    slug: string;
  };
  admin: TestCredentials;
  author?: TestCredentials;
};

const fixtureTenantIds = new Set<string>();
const fixtureUserIds = new Set<string>();

async function runFixtureSetup(expression: string): Promise<string> {
  const { stdout } = await execFileAsync(
    'docker',
    ['compose', 'exec', '-T', 'laravel.test', 'php', 'artisan', 'tinker', '--execute', expression],
    { cwd: process.cwd(), maxBuffer: 1024 * 1024 },
  );

  return stdout.trim();
}

export async function clearRecoveryRateLimiter(): Promise<void> {
  const expression = `foreach (['127.0.0.1', '::1', '172.16.0.1', '172.17.0.1', '172.18.0.1', '172.19.0.1'] as $ip) { \\Illuminate\\Support\\Facades\\RateLimiter::clear('livewire-rate-limiter:'.sha1(\\App\\Filament\\Pages\\Auth\\RecoverAccount::class.'|recover|'.$ip)); }`;

  await runFixtureSetup(expression);
}

function phpString(value: string): string {
  return JSON.stringify(value);
}

export async function createE2eTenantFixture(options: { author?: boolean; adminRole?: 'admin' | 'super_admin' } = {}): Promise<E2eTenantFixture> {
  const suffix = randomUUID();
  const tenantName = `E2E Tenant ${suffix}`;
  const tenantDomain = `e2e-${suffix}.test`;
  const adminEmail = `e2e-admin-${suffix}@example.test`;
  const authorEmail = `e2e-author-${suffix}@example.test`;
  const adminRole = options.adminRole === 'super_admin' ? 'SuperAdmin' : 'Admin';
  const authorCode = options.author
    ? `$author = \\App\\Models\\User::factory()->forTenant($tenant)->create(['email' => ${phpString(authorEmail)}, 'role' => \\App\\Enums\\Role::Author]);`
    : '$author = null;';

  const expression = [
    `$tenant = \\App\\Models\\Tenant::factory()->create(['name' => ${phpString(tenantName)}, 'domain' => ${phpString(tenantDomain)}]);`,
    `$admin = \\App\\Models\\User::factory()->forTenant($tenant)->create(['email' => ${phpString(adminEmail)}, 'role' => \\App\\Enums\\Role::${adminRole}]);`,
    authorCode,
    'echo json_encode([\'tenant\' => [\'id\' => (string) $tenant->getKey(), \'name\' => $tenant->name, \'slug\' => $tenant->slug], \'admin\' => [\'id\' => (string) $admin->getKey(), \'email\' => $admin->email, \'password\' => \'password\'], \'author\' => $author ? [\'id\' => (string) $author->getKey(), \'email\' => $author->email, \'password\' => \'password\'] : null]);',
  ].join(' ');
  const fixture = JSON.parse(await runFixtureSetup(expression)) as E2eTenantFixture;

  fixtureTenantIds.add(fixture.tenant.id);
  if (fixture.admin.id) fixtureUserIds.add(fixture.admin.id);
  if (fixture.author?.id) fixtureUserIds.add(fixture.author.id);

  return fixture;
}

export async function createClosedTenantRecoveryFixture(): Promise<E2eTenantFixture> {
  const fixture = await createE2eTenantFixture({ author: true });
  const expression = [
    `$tenant = \\App\\Models\\Tenant::withoutGlobalScopes()->withTrashed()->findOrFail(${phpString(fixture.tenant.id)});`,
    `$author = \\App\\Models\\User::withoutGlobalScopes()->where('email', ${phpString(fixture.author?.email ?? '')})->firstOrFail();`,
    '$tenant->delete();',
    '$author->forceFill([\'deletion_reason\' => \\App\\Enums\\DeletionReason::TenantClosed])->saveQuietly();',
    '$author->delete();',
  ].join(' ');

  await runFixtureSetup(expression);

  return fixture;
}

export async function createExpiredTenantRecoveryFixture(): Promise<E2eTenantFixture> {
  const fixture = await createE2eTenantFixture();
  const expression = [
    `$tenant = \\App\\Models\\Tenant::withoutGlobalScopes()->withTrashed()->findOrFail(${phpString(fixture.tenant.id)});`,
    `$admin = \\App\\Models\\User::withoutGlobalScopes()->where('email', ${phpString(fixture.admin.email)})->firstOrFail();`,
    '$tenant->delete();',
    '$tenant->forceFill([\'deleted_at\' => now()->subDays(31)])->saveQuietly();',
    '$admin->forceFill([\'deletion_reason\' => \\App\\Enums\\DeletionReason::SelfClosed])->saveQuietly();',
    '$admin->delete();',
    '$admin->forceFill([\'deleted_at\' => now()->subDay()])->saveQuietly();',
  ].join(' ');

  await runFixtureSetup(expression);

  return fixture;
}

export async function cleanupE2eFixtures(): Promise<void> {
  if (fixtureTenantIds.size === 0) return;

  const tenantIds = JSON.stringify([...fixtureTenantIds]);
  const userIds = JSON.stringify([...fixtureUserIds]);
  const expression = [
    `\\App\\Models\\User::withoutGlobalScopes()->withTrashed()->whereIn('id', ${userIds})->forceDelete();`,
    `\\App\\Models\\Tenant::withoutGlobalScopes()->withTrashed()->whereIn('id', ${tenantIds})->forceDelete();`,
  ].join(' ');

  await runFixtureSetup(expression);
  fixtureUserIds.clear();
  fixtureTenantIds.clear();
}

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

export async function waitForExportNotification(identifier: string): Promise<void> {
  await expect.poll(async () => {
    try {
      const response = await fetch('http://localhost:8025/api/v1/messages?limit=50');
      const data = await response.json();

      for (const message of data.messages ?? []) {
        const detailResponse = await fetch(`http://localhost:8025/api/v1/message/${message.ID}`);
        const detail = await detailResponse.json();
        if (String(detail.Text ?? '').includes(identifier)) return true;
      }
    } catch {}

    return false;
  }, { timeout: 30000, intervals: [250, 500, 1000] }).toBe(true);
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

export async function login(page: Page, credentials = TEST_USERS.superAdmin) {
  let lastError: Error | undefined;

  for (let attempt = 0; attempt < 2; attempt++) {
    await clearMailpit();
    await page.goto('/admin/login');

    await page.locator('input[type="email"]').click();
    await page.locator('input[type="email"]').pressSequentially(credentials.email, { delay: 10 });
    await page.locator('input[type="password"]').click();
    await page.locator('input[type="password"]').pressSequentially(credentials.password, { delay: 10 });

    await Promise.all([
      page.waitForNavigation({ timeout: 15000 }).catch(() => {}),
      page.locator('button[type="submit"]').click(),
    ]);

    await handleMfaChallenge(page);

    if (await isOnMfaPage(page)) {
      await handleMfaChallenge(page);
    }

    try {
      await expect(page).toHaveURL((url) => {
        const path = url.pathname;
        return path === '/admin' || path === '/admin/';
      }, { timeout: 15000 });
      return;
    } catch (error) {
      lastError = error as Error;
    }
  }

  throw lastError;
}

export async function loginAs(page: Page, role: TestUserRole) {
  await login(page, TEST_USERS[role]);
}
