// playwright.config.js — suite E2E de Convoca Courses contra la demo.
//
// La demo es un sitio real: los datos del fixture los siembra `e2e/seed-demo.sh` (wp-cli por ssh) y
// los borra `e2e/cleanup-demo.sh`. El fixture **no** se inventa desde el test: lo que se mide es el
// plugin desplegado, con WordPress y MySQL de verdad.
const { defineConfig, devices } = require('@playwright/test');

const BASE = process.env.E2E_BASE || 'https://demo.getconvoca.app';

module.exports = defineConfig({
  testDir: './e2e',
  timeout: 60_000,
  expect: { timeout: 15_000 },
  fullyParallel: false,
  workers: 1,
  retries: process.env.CI ? 1 : 0,
  reporter: [
    ['list'],
    ['html', { open: 'never', outputFolder: 'playwright-report' }],
    ['json', { outputFile: 'playwright-report/resultados.json' }],
  ],
  use: {
    baseURL: BASE,
    viewport: { width: 1280, height: 900 },
    locale: 'es-ES',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    ignoreHTTPSErrors: true,
  },
  projects: [
    { name: 'escritorio', use: { ...devices['Desktop Chrome'] } },
    { name: 'movil', use: { ...devices['Pixel 5'] } },
  ],
});
