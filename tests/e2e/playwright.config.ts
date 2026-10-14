import { defineConfig, devices } from '@playwright/test';

const port = Number(process.env.E2E_PORT ?? 8130);
const baseURL = process.env.E2E_BASE_URL ?? `http://127.0.0.1:${port}`;

export default defineConfig({
  testDir: './specs',
  outputDir: './reports/runs/artifacts',
  globalSetup: './lib/global-setup.ts',
  globalTeardown: './lib/coverage.ts',
  fullyParallel: true,
  workers: Number(process.env.E2E_WORKERS ?? 4),
  retries: 0, // a flaky journey is a finding, not noise
  reporter: process.env.CI ? [['list'], ['html', { outputFolder: './reports/runs/html', open: 'never' }], ['github']] : [['list']],
  use: {
    baseURL,
    trace: 'off',
    screenshot: 'off', // journeys run in their own contexts; the failing page is attached by the spec
    launchOptions: process.env.PLAYWRIGHT_CHROMIUM_PATH ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_PATH } : {},
  },
  projects: [
    { name: 'desktop', use: { ...devices['Desktop Chrome'] } },
    ...(process.env.E2E_MODE === 'nightly' ? [{ name: 'mobile', use: { ...devices['Pixel 7'] }, grep: /rnd-/ }] : []),
  ],
  webServer: process.env.E2E_BASE_URL ? undefined : {
    command: 'bash tests/e2e/serve.sh',
    cwd: '../..',
    url: `${baseURL}/up`,
    reuseExistingServer: !process.env.CI,
    timeout: 120_000,
    stdout: 'ignore',
    stderr: 'pipe',
  },
});
