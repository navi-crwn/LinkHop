// Screenshots of the main pages, for comparing the UI before and after a CSS or dependency
// change (e.g. a Tailwind upgrade). Needs the E2E server (tests/e2e/serve.sh, E2E_HOOKS=true).
//
//   bash tests/e2e/serve.sh &                       # app on http://127.0.0.1:8130
//   node scripts/ui-screenshots.mjs shoot before    # with the old build
//   npm run build                                   # with the change
//   node scripts/ui-screenshots.mjs shoot after
//   node scripts/ui-screenshots.mjs diff before after   # writes diff-*.png and report.md
//
// Shots go to $SHOTS_DIR (default storage/app/ui-screenshots, git-ignored). The same
// accounts are reused between runs (personas.json) so only the styling differs.
import { mkdir, readFile, readdir, writeFile } from 'node:fs/promises';
import { existsSync } from 'node:fs';
import { join } from 'node:path';
import { chromium } from '@playwright/test';

const BASE = process.env.E2E_BASE_URL || 'http://127.0.0.1:8130';
const OUT = process.env.SHOTS_DIR || 'storage/app/ui-screenshots';
const [mode, a, b] = process.argv.slice(2);
const launch = () => chromium.launch(process.env.CHROMIUM_PATH ? { executablePath: process.env.CHROMIUM_PATH } : {});

const viewports = { desktop: { width: 1280, height: 900 }, mobile: { width: 390, height: 844 } };
const schemes = ['light', 'dark'];

async function personas() {
  const file = join(OUT, 'personas.json');
  if (existsSync(file)) return JSON.parse(await readFile(file, 'utf8'));
  const make = async (admin) => (await fetch(`${BASE}/_e2e/persona`, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ admin }) })).json();
  const data = { user: await make(false), admin: await make(true) };
  await mkdir(OUT, { recursive: true });
  await writeFile(file, JSON.stringify(data, null, 2));
  return data;
}

async function login(context, who) {
  const page = await context.newPage();
  await page.goto(`${BASE}/login`);
  await page.fill('input[name=email]', who.email);
  await page.fill('input[name=password]', who.password);
  await Promise.all([page.waitForURL((u) => !u.pathname.startsWith('/login')), page.locator('form button[type=submit]').first().click()]);
  return page;
}

async function shoot(set) {
  const { user, admin } = await personas();
  const groups = {
    guest: { who: null, pages: { landing: '/', login: '/login', register: '/register', 'bio-public': `/b/${user.bio}`, 'bio-example': '/b/hel' } },
    user: { who: user, pages: { dashboard: '/dashboard', links: '/links', analytics: '/analytics', settings: '/settings', bio: '/bio', 'bio-editor': 'EDITOR' } },
    admin: { who: admin, pages: { 'admin-users': '/admin/users', 'admin-links': '/admin/links', 'admin-bio': '/admin/bio', 'admin-analytics': '/admin/analytics/global', 'admin-abuse': '/admin/abuse' } },
  };
  const dir = join(OUT, set);
  await mkdir(dir, { recursive: true });
  const browser = await launch();
  for (const [vpName, viewport] of Object.entries(viewports)) {
    for (const colorScheme of schemes) {
      for (const group of Object.values(groups)) {
        const context = await browser.newContext({ viewport, colorScheme, reducedMotion: 'reduce' });
        const page = group.who ? await login(context, group.who) : await context.newPage();
        for (const [name, path] of Object.entries(group.pages)) {
          let url = `${BASE}${path}`;
          if (path === 'EDITOR') {
            await page.goto(`${BASE}/bio`);
            url = await page.locator('a[href*="/edit"]').first().getAttribute('href');
          }
          const response = await page.goto(url, { waitUntil: 'networkidle' });
          await page.addStyleTag({ content: '*,*::before,*::after{animation:none!important;transition:none!important;caret-color:transparent!important}' });
          await page.waitForTimeout(300);
          await page.screenshot({ path: join(dir, `${name}-${vpName}-${colorScheme}.png`), fullPage: true });
          console.log(`${set}: ${name} ${vpName} ${colorScheme} -> ${response?.status()}`);
        }
        await context.close();
      }
    }
  }
  await browser.close();
}

// Pixel diff in Chromium (canvas), so no image library is needed.
async function diff(before, after) {
  const browser = await launch();
  const page = await browser.newPage();
  const rows = [];
  for (const file of (await readdir(join(OUT, before))).filter((f) => f.endsWith('.png')).sort()) {
    if (!existsSync(join(OUT, after, file))) { rows.push([file, 'missing after', '']); continue; }
    const [x, y] = await Promise.all([readFile(join(OUT, before, file)), readFile(join(OUT, after, file))]);
    const result = await page.evaluate(async ([x, y]) => {
      const load = async (b64) => { const i = new Image(); i.src = 'data:image/png;base64,' + b64; await i.decode(); return i; };
      const [i1, i2] = [await load(x), await load(y)];
      const w = Math.max(i1.width, i2.width), h = Math.max(i1.height, i2.height);
      const data = (img) => { const c = new OffscreenCanvas(w, h); const g = c.getContext('2d'); g.drawImage(img, 0, 0); return g.getImageData(0, 0, w, h).data; };
      const [d1, d2] = [data(i1), data(i2)];
      const out = new OffscreenCanvas(w, h); const og = out.getContext('2d'); const od = og.createImageData(w, h);
      let changed = 0;
      for (let p = 0; p < d1.length; p += 4) {
        const delta = Math.abs(d1[p] - d2[p]) + Math.abs(d1[p + 1] - d2[p + 1]) + Math.abs(d1[p + 2] - d2[p + 2]);
        const hit = delta > 48;
        if (hit) changed++;
        od.data[p] = hit ? 255 : d1[p] * 0.3 + 178; od.data[p + 1] = hit ? 0 : d1[p + 1] * 0.3 + 178; od.data[p + 2] = hit ? 64 : d1[p + 2] * 0.3 + 178; od.data[p + 3] = 255;
      }
      og.putImageData(od, 0, 0);
      const blob = await out.convertToBlob({ type: 'image/png' });
      const png = await new Promise((resolve) => { const r = new FileReader(); r.onload = () => resolve(r.result.split(',')[1]); r.readAsDataURL(blob); });
      return { pct: (changed / (w * h)) * 100, size: `${i1.width}x${i1.height} → ${i2.width}x${i2.height}`, png };
    }, [x.toString('base64'), y.toString('base64')]);
    await writeFile(join(OUT, `diff-${file}`), Buffer.from(result.png, 'base64'));
    rows.push([file, result.pct.toFixed(2) + '%', result.size]);
  }
  await browser.close();
  const report = ['| Screenshot | Pixels changed | Size |', '| --- | --- | --- |', ...rows.map((r) => `| ${r.join(' | ')} |`)].join('\n');
  await writeFile(join(OUT, 'report.md'), report + '\n');
  console.log(report);
}

if (mode === 'shoot' && a) await shoot(a);
else if (mode === 'diff' && a && b) await diff(a, b);
else console.log('usage: node scripts/ui-screenshots.mjs shoot <set> | diff <before> <after>');
