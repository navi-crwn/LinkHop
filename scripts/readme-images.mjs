// README screenshots: docs/readme/*.webp, one light and one dark image per screen.
//
// Public pages come from the live site; signed-in pages come from a local copy with invented
// demo data, so no account, address or visitor of the real service ends up in an image.
//
//   BIO_ENABLED=false in .env (as on link.hel.ink), then
//   bash tests/e2e/serve.sh &                      # throwaway SQLite app (E2E_PORT=8131 to change the port)
//   APP_ENV=e2e php scripts/readme-demo-seed.php   # the demo account and its links and clicks
//   node scripts/readme-images.mjs
//
// Needs Google Chrome (or set CHROME_CHANNEL) and cwebp. LIVE_URL and LOCAL_URL override the hosts.
import { execFileSync } from 'node:child_process';
import { mkdirSync, rmSync, statSync } from 'node:fs';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from '@playwright/test';

const root = fileURLToPath(new URL('../', import.meta.url));
const out = join(root, 'docs/readme');
const tmp = join(root, 'test-results/readme');
const live = (process.env.LIVE_URL || 'https://link.hel.ink').replace(/\/$/, '');
const local = (process.env.LOCAL_URL || 'http://127.0.0.1:8131').replace(/\/$/, '');
mkdirSync(out, { recursive: true });
mkdirSync(tmp, { recursive: true });

const viewport = { width: 1280, height: 800 };

// name, host, path, what to do before the picture is taken
const shots = [
  { name: 'home', base: live, path: '/' },
  { name: 'dashboard', base: local, path: '/dashboard', auth: true },
  { name: 'links', base: local, path: '/links', auth: true },
  { name: 'analytics', base: local, path: '/analytics?hours=72', auth: true, settle: 2500 },
];

const browser = await chromium.launch({ channel: process.env.CHROME_CHANNEL || 'chrome' });

for (const theme of ['light', 'dark']) {
  const context = await browser.newContext({ viewport, deviceScaleFactor: 2, reducedMotion: 'reduce', colorScheme: theme, locale: 'en-US' });
  await context.addInitScript(t => {
    try {
      localStorage.setItem('helink-theme', t);
      localStorage.setItem('hop-theme', t);
      localStorage.setItem('cookie_consent', 'declined');
    } catch (e) {}
  }, theme);
  const page = await context.newPage();
  const signedIn = new Set();

  for (const shot of shots) {
    if (shot.auth && !signedIn.has(shot.base)) {
      const res = await context.request.post(`${shot.base}/_e2e/login`, { data: { email: 'demo@example.test' } });
      if (!res.ok()) throw new Error(`Local sign-in failed (${res.status()}). Is the demo app running and seeded?`);
      signedIn.add(shot.base);
    }
    await page.goto(shot.base + shot.path, { waitUntil: 'load' });
    await page.evaluate(t => document.documentElement.classList.toggle('dark', t === 'dark'), theme);
    await page.evaluate(() => document.fonts.ready);
    await page.waitForTimeout(shot.settle ?? 2500);
    const png = join(tmp, `${shot.name}-${theme}.png`);
    await page.screenshot({ path: png });
    const webp = join(out, `${shot.name}-${theme}.webp`);
    execFileSync('cwebp', ['-quiet', '-q', '82', '-m', '6', png, '-o', webp]);
    console.log(`${shot.name}-${theme}.webp ${Math.round(statSync(webp).size / 1024)} KB`);
  }
  await context.close();
}

await browser.close();
rmSync(tmp, { recursive: true, force: true });
