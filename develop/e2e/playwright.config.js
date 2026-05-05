// @ts-check
const path = require('path');
const { defineConfig, devices } = require('@playwright/test');

const projectName = process.env.PROJECT_NAME || 'local';
const artifactsRoot = process.env.E2E_ARTIFACTS_DIR || path.join('/work/release.check', projectName, 'playwright');

/** Shared browser config reused by both projects */
const browserConfig = {
  ...devices['Desktop Chrome'],
  viewport: { width: 1700, height: 1300 },
  video: { mode: 'on', size: { width: 1700, height: 1300 } },
};

module.exports = defineConfig({
  testDir: './tests',
  timeout: 120000,
  workers: 2,
  fullyParallel: true,
  retries: 0,
  reporter: [
    ['list'],
    ['html', { open: 'never', outputFolder: path.join(artifactsRoot, 'html-report') }]
  ],
  outputDir: path.join(artifactsRoot, 'test-results'),
  use: {
    baseURL: process.env.BASE_URL || 'http://nginx',
    trace: 'on',
    video: 'on',
    screenshot: 'on',
    viewport: { width: 1700, height: 1300 }
  },
  projects: [
    // ── Setup project: runs first, creates all shared DB fixtures ──────────────
    // Matches only tests/setup.js; all other projects declare it as a dependency.
    {
      name: 'setup',
      testMatch: /setup\.js/,
      use: browserConfig,
    },

    // ── Main test project: runs after setup, supports parallel execution ───────
    // Matches numbered test files (01.xxx.js, 02.xxx.js, …) but NOT setup.js.
    {
      name: 'chromium',
      testMatch: /\d+\..+\.js/,
      dependencies: ['setup'],
      use: browserConfig,
    },
  ]
});
