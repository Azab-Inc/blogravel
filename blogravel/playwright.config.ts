import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './tests/e2e',
  timeout: 60000,
  expect: { timeout: 10000 },
  workers: 1,
  use: {
    baseURL: process.env.PLAYWRIGHT_BASE_URL ?? 'http://blogravel.com:8000',
    headless: true,
    screenshot: 'only-on-failure',
    launchOptions: {
      args: ['--host-resolver-rules=MAP blogravel.com 127.0.0.1,MAP *.blogravel.com 127.0.0.1'],
    },
  },
  projects: [
    { name: 'setup', testMatch: /.*\.setup\.ts/ },
    {
      name: 'debug',
      testMatch: /debug-login/,
      use: { browserName: 'chromium' },
    },
    {
      name: 'chromium',
      testIgnore: /api-docs\.spec\.ts/,
      use: {
        browserName: 'chromium',
        storageState: 'tests/e2e/.auth/admin.json',
      },
      dependencies: ['setup'],
    },
    {
      name: 'docs',
      testMatch: /api-docs\.spec\.ts/,
      use: { browserName: 'chromium' },
    },
  ],
});
