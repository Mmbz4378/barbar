// دسترس‌پذیری با axe — WCAG 2.2 AA، در حالت روشن و تیره.
// خطاهای serious و critical آزمون را می‌شکنند؛ بقیه فقط گزارش می‌شوند.
import { test, expect } from '@playwright/test';
import { createRequire } from 'node:module';
import { PAGES, authFile } from './pages.mjs';

const axePath = createRequire(import.meta.url).resolve('axe-core/axe.min.js');
const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

for (const scheme of ['light', 'dark']) {
  for (const [url, role] of PAGES) {
    test.describe(`${scheme} ${role ?? 'public'}`, () => {
      test.use({ colorScheme: scheme, storageState: authFile(role) });
      test(`a11y ${url}`, async ({ page }, info) => {
        const res = await page.goto(url, { waitUntil: 'networkidle' });
        expect(res.status(), `HTTP ${url}`).toBeLessThan(400);
        await page.addScriptTag({ path: axePath });
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
