// برای هر نقش یک نشست واقعی می‌سازد (لینک ورود یک‌بارمصرف از tools/login-link.php)
// دادهٔ نمونه: tools/seed-demo.php
import { chromium } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(here, '../..');
export const ROLES = {
  owner: { phone: '09120000001', salon: 'araishgah-parsa' },
  reception: { phone: '09120000002' },
  staff: { phone: '09120000003' },
  admin: { phone: '09120000009' },
};

export default async function globalSetup(config) {
  const baseURL = config.projects[0].use.baseURL;
  fs.mkdirSync(path.join(here, '.auth'), { recursive: true });
  const browser = await chromium.launch();
  for (const [role, { phone, salon }] of Object.entries(ROLES)) {
    const out = execFileSync('php', [path.join(root, 'tools/login-link.php'), phone], { encoding: 'utf8' });
    const token = (out.match(/\/login\/link\/([a-z0-9]+)/) || [])[1];
    if (!token) throw new Error(`لینک ورود برای ${role} ساخته نشد:\n${out}`);
    const context = await browser.newContext({ baseURL, serviceWorkers: 'block' });
    const page = await context.newPage();
    await page.goto(`/login/link/${token}`);
    if (salon) {
      // صاحب هر دو سالن است؛ سالن مردانه را انتخاب کن
      const id = await page.evaluate(async () => {
        const html = await (await fetch('/salons')).text();
        const ids = (html.match(/\/salons\/(\d+)\/switch/g) || []).map((s) => +s.match(/\d+/)[0]);
        return ids.length ? Math.min(...ids) : null; // seed-demo: اولین سالن، آرایشگاه مردانه
      });
      if (id) await page.goto(`/salons/${id}/switch`);
    }
    await context.storageState({ path: path.join(here, '.auth', `${role}.json`) });
    await context.close();
  }
  await browser.close();
}
