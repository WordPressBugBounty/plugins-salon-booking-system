// Playwright configuration for Salon Booking System
// Provides defaults for local development while allowing overrides through env vars.

const { devices } = require('@playwright/test');
require('dotenv').config({ path: process.env.PLAYWRIGHT_DOTENV || '.env.testing' });

const BASE_URL = process.env.SALON_BASE_URL || 'http://localhost:10018';

/** @type {import('@playwright/test').PlaywrightTestConfig} */
const config = {
  testDir: './tests/e2e',
  timeout: 90 * 1000,
  expect: {
    timeout: 10 * 1000,
  },
  retries: process.env.CI ? 1 : 0,
  workers: process.env.CI ? 2 : undefined,
  reporter: [['line'], ['html', { outputFolder: 'build/playwright-report', open: 'never' }]],
  use: {
    baseURL: BASE_URL,
    trace: process.env.CI ? 'on-first-retry' : 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    ignoreHTTPSErrors: true,
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'], viewport: { width: 1365, height: 768 } },
    },
    {
      name: 'firefox',
      use: { ...devices['Desktop Firefox'], viewport: { width: 1365, height: 768 } },
    },
    {
      name: 'webkit',
      use: { ...devices['Desktop Safari'], viewport: { width: 1365, height: 768 } },
    },
  ],
  metadata: {
    product: 'Salon Booking System',
    environment: process.env.SALON_TEST_ENV || 'local',
  },
};

module.exports = config;
