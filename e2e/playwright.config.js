// @ts-check
const { defineConfig, devices } = require('@playwright/test');

/**
 * Tests E2E DreamTravel : un vrai navigateur pilote l'application lancée par
 * "docker compose up -d" (http://localhost:8088, e-mails capturés par Mailpit).
 */
module.exports = defineConfig({
  testDir: './tests',
  globalSetup: require.resolve('./global-setup.js'),
  // Les scénarios partagent la même base : on les joue l'un après l'autre
  fullyParallel: false,
  workers: 1,
  retries: process.env.CI ? 1 : 0,
  // Délais larges : en mode dev sous Windows (code monté), une page met plusieurs secondes
  timeout: 120_000,
  expect: { timeout: 15_000 },
  reporter: [['list'], ['html', { open: 'never' }]],
  use: {
    baseURL: process.env.E2E_BASE_URL || 'http://localhost:8088',
    locale: 'fr-FR',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'], channel: 'chromium' } }],
});
