// برای هر نقش یک نشست واقعی می‌سازد: لینک ورود یک‌بارمصرف از tools/login-link.php،
// و برای مدیر (که لینک ورود نمی‌گیرد) ورود با رمز دادهٔ نمونه
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
  admin: { username: 'admin', password: process.env.RESHEN_ADMIN_PASSWORD || 'Demo-admin-2026' },
};

export default async function globalSetup(config) {
  const baseURL = config.projects[0].use.baseURL;
  fs.mkdirSync(path.join(here, '.auth'), { recursive: true });
  const browser = await chromium.launch();
  for (const [role, { phone, salon, username, password }] of Object.entries(ROLES)) {
    const context = await browser.newContext({ baseURL, serviceWorkers: 'block' });
    const page = await context.newPage();
    if (username) {
      await page.goto('/login?method=password');
      await page.fill('#identifier', username);
      await page.fill('#password', password);
      await Promise.all([page.waitForURL(/\/platform/), page.click('form[action$="/login/password"] button[type=submit]')]);
    } else {
      const out = execFileSync('php', [path.join(root, 'tools/login-link.php'), phone], { encoding: 'utf8' });
      const token = (out.match(/\/login\/link\/([a-z0-9]+)/) || [])[1];
      if (!token) throw new Error(`لینک ورود برای ${role} ساخته نشد:\n${out}`);
      await page.goto(`/login/link/${token}`);
    }
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
