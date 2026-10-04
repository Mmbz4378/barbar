// آزمون ظاهر و دسترس‌پذیری — docs/design-system.md بخش «آزمون خودکار»
import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: '.',
  testMatch: /.*\.spec\.mjs/,
  timeout: 90_000,
  retries: process.env.CI ? 1 : 0,
  workers: process.env.CI ? 2 : 4,
  reporter: process.env.CI ? [['list'], ['html', { open: 'never', outputFolder: 'playwright-report' }]] : 'list',
  globalSetup: './global-setup.mjs',
  // عکس‌های مبنا بدون پسوند سیستم‌عامل: هم محلی و هم CI روی لینوکس و همین نسخهٔ Chromium
  snapshotPathTemplate: '{testDir}/__screenshots__/{arg}{ext}',
  expect: {
    toHaveScreenshot: { maxDiffPixelRatio: 0.01, animations: 'disabled', caret: 'hide', scale: 'css', stylePath: new URL('./screenshot.css', import.meta.url).pathname },
  },
  use: {
    baseURL: process.env.RESHEN_URL || 'http://127.0.0.1:8080',
    locale: 'fa-IR',
    timezoneId: 'Asia/Tehran',
    serviceWorkers: 'block',
    reducedMotion: 'reduce',
  },
  projects: [{ name: 'chromium', use: { browserName: 'chromium' } }],
});
