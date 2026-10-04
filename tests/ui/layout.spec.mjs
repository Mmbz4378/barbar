// چیدمان: در عرض ۳۲۰ و ۱۲۸۰ بدون اسکرول افقی و بدون خطای جاوااسکریپت
import { test, expect } from '@playwright/test';
import { PAGES, authFile } from './pages.mjs';

for (const width of [320, 1280]) {
  for (const [url, role] of PAGES) {
    test.describe(`${width}px ${role ?? 'public'}`, () => {
      test.use({ viewport: { width, height: 800 }, storageState: authFile(role) });
      test(`layout ${url}`, async ({ page }) => {
        const errors = [];
        page.on('pageerror', (e) => errors.push(e.message));
        page.on('console', (m) => { if (m.type() === 'error' && !/favicon|Failed to load resource/.test(m.text())) errors.push(m.text()); });
        await page.goto(url, { waitUntil: 'networkidle' });
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(overflow, `اسکرول افقی در ${url} با عرض ${width}`).toBeLessThanOrEqual(0);
        expect(errors, `خطای JS در ${url}`).toEqual([]);
      });
    });
  }
}
