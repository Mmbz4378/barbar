// آزمون تصویری روی گالری سیستم طراحی (/system/design) — هر بخش جدا.
// به‌روزکردن عکس‌های مبنا پس از تغییر عمدی ظاهر:  npm run update
import { test, expect } from '@playwright/test';
import { authFile } from './pages.mjs';

const SECTIONS = ['tokens', 'type', 'space', 'buttons', 'fields', 'status', 'alerts', 'cards', 'tabs', 'data', 'table', 'empty', 'overlays', 'booking', 'identity', 'pagination'];
const VARIANTS = [
  { name: 'desktop-light', width: 1280, scheme: 'light', theme: 'forest', sections: SECTIONS },
  { name: 'desktop-dark', width: 1280, scheme: 'dark', theme: 'forest', sections: SECTIONS },
  { name: 'mobile-light', width: 390, scheme: 'light', theme: 'forest', sections: SECTIONS },
  { name: 'desktop-rose', width: 1280, scheme: 'light', theme: 'rose', sections: ['buttons', 'fields', 'data', 'booking'] },
];

for (const v of VARIANTS) {
  test.describe(v.name, () => {
    test.use({ viewport: { width: v.width, height: 900 }, colorScheme: v.scheme, storageState: authFile('admin') });
    test(`gallery ${v.name}`, async ({ page }) => {
      await page.goto('/system/design', { waitUntil: 'networkidle' });
      await page.evaluate(() => document.fonts.ready);
      if (v.theme !== 'forest') await page.selectOption('#ds-theme', v.theme);
      for (const id of v.sections) {
        await expect.soft(page.locator(`#ds-${id}`), `بخش ${id}`).toHaveScreenshot(`${v.name}-${id}.png`);
      }
    });
  });
}
