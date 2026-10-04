// دسترس‌پذیری با axe — WCAG 2.2 AA، در حالت روشن و تیره.
// خطاهای serious و critical آزمون را می‌شکنند؛ بقیه فقط گزارش می‌شوند.
import { test, expect } from '@playwright/test';
import { createRequire } from 'node:module';
import { readFileSync } from 'node:fs';
import { PAGES, authFile } from './pages.mjs';

const axePath = createRequire(import.meta.url).resolve('axe-core/axe.min.js');
// axe را با page.evaluate (از راه CDP) تزریق می‌کنیم، نه addScriptTag:
// صفحه حالا CSP سفت دارد و تگ اسکریپت درون‌خطی مسدود می‌شود. تزریق از
// راه CDP مشمول CSP صفحه نیست، پس آزمون روی همان CSP واقعیِ تولید اجرا
// می‌شود، نه یک نسخهٔ سست‌شده.
const axeSource = readFileSync(axePath, 'utf8') + '\nnull;';
const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

for (const scheme of ['light', 'dark']) {
  for (const [url, role] of PAGES) {
    test.describe(`${scheme} ${role ?? 'public'}`, () => {
      test.use({ colorScheme: scheme, storageState: authFile(role) });
      test(`a11y ${url}`, async ({ page }, info) => {
        const res = await page.goto(url, { waitUntil: 'networkidle' });
        expect(res.status(), `HTTP ${url}`).toBeLessThan(400);
        await page.evaluate(axeSource);
        const result = await page.evaluate((tags) => window.axe.run(document, { runOnly: { type: 'tag', values: tags }, resultTypes: ['violations'] }), TAGS);
        const blocking = result.violations.filter((v) => ['serious', 'critical'].includes(v.impact));
        const minor = result.violations.filter((v) => !blocking.includes(v));
        if (minor.length) info.annotations.push({ type: 'a11y-minor', description: minor.map((v) => `${v.id} (${v.nodes.length})`).join(', ') });
        const report = blocking.map((v) => `${v.impact} ${v.id}: ${v.help}\n` + v.nodes.slice(0, 5).map((n) => `    ${n.target.join(' ')} — ${n.failureSummary.split('\n')[1] ?? ''}`).join('\n')).join('\n');
        expect(blocking, `${url} (${scheme}):\n${report}`).toEqual([]);
      });
    });
  }
}
