// @ts-check
const path = require('path');
const { defineConfig, devices } = require('@playwright/test');

const projectName = process.env.PROJECT_NAME || 'local';
const artifactsRoot = process.env.E2E_ARTIFACTS_DIR || path.join('/work/release.check', projectName, 'playwright');

module.exports = defineConfig({
  testDir: './tests',
  testMatch: ['**/*.js'],
  timeout: 120000,
  workers: 1,
  fullyParallel: false,
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
    viewport: { width: 2000, height: 2000 }
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] }
    }
  ]
});

