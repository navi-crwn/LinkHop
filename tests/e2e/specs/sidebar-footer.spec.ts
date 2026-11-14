/**
 * The sidebar footer in a real browser: Help, Terms, Privacy, Changelog, GitHub and Announcement, one per line in that
 * order, then "Part of Hop Easy Link" below them. On a phone the footer scrolls with the menu and can be scrolled
 * into view. Needs the app with E2E_HOOKS=true (bash tests/e2e/serve.sh).
 */
import { test, expect } from '@playwright/test';

const baseURL = process.env.E2E_BASE_URL ?? `http://127.0.0.1:${Number(process.env.E2E_PORT ?? 8130)}`;
test.setTimeout(120_000);
let n = 0;

async function open(browser: import('@playwright/test').Browser, width: number, height: number, mobile: boolean, theme: 'light' | 'dark', lang = 'en') {
  const ip = `198.18.79.${++n + (process.pid % 200)}`;
  const context = await browser.newContext({ baseURL, viewport: { width, height }, hasTouch: mobile, isMobile: mobile, extraHTTPHeaders: { 'X-Forwarded-For': ip } });
  await context.route(url => !url.href.startsWith(baseURL), route => route.abort());
  await context.addInitScript(() => { try { localStorage.setItem('cookie_consent', 'accepted'); } catch (e) { /* private window */ } });
  await context.addCookies([{ name: 'hop_lang', value: lang, url: baseURL }, { name: 'hop_theme', value: theme, url: baseURL }]);
  const res = await fetch(`${baseURL}/_e2e/persona`, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Forwarded-For': ip }, body: '{}' });
  const me = await res.json() as { email: string };
  expect((await context.request.post('/_e2e/login', { form: { email: me.email } })).ok()).toBe(true);
  const page = await context.newPage();
  await page.goto('/dashboard');
  if (mobile) { await page.getByRole('button', { name: 'Open menu' }).click(); }
  await expect(page.locator('[data-sidebar-footer]')).toBeVisible();
  return { page, context };
}

const settled = async <T>(read: () => Promise<T>) => {
  let last = '';
  let value!: T;
  await expect.poll(async () => { value = await read(); const now = JSON.stringify(value); const same = now === last; last = now; return same; }, { timeout: 15_000, intervals: [150] }).toBe(true);
  return value;
};

for (const [width, height, mobile] of [[1440, 900, false], [390, 800, true], [390, 600, true]] as const) {
  test(`footer is a column of six links with "Part of" below, ${width}x${height}`, async ({ browser }) => {
    const { page, context } = await open(browser, width, height, mobile, 'light');
    const links = page.locator('[data-sidebar-footer] li a');
    await expect(links).toHaveCount(6);
    await expect(links).toHaveText(['Help', 'Terms', 'Privacy', 'Changelog', 'GitHub', 'Announcement']);

    // the phone drawer slides in over 300 ms, and a short phone scrolls: bring "Part of" into view first
    await page.locator('.hop-family-row').scrollIntoViewIfNeeded();
    const g = await settled(() => page.evaluate(() => {
      const li = [...document.querySelectorAll('[data-sidebar-footer] li a')].map(a => { const r = a.getBoundingClientRect(); return { top: Math.round(r.top), left: Math.round(r.left), h: Math.round(r.height) }; });
      const p = document.querySelector('.hop-family-row')!.getBoundingClientRect();
      const side = document.querySelector('aside > div')!.getBoundingClientRect();
      return { li, part: { top: Math.round(p.top), bottom: Math.round(p.bottom) }, side: { top: Math.round(side.top), bottom: Math.round(side.bottom) }, wide: document.documentElement.scrollWidth > document.documentElement.clientWidth };
    }));
    for (let i = 1; i < g.li.length; i++) {
      expect(g.li[i].top, `entry ${i} is below the one before`).toBeGreaterThan(g.li[i - 1].top);
      expect(g.li[i].left, `entry ${i} starts at the same left edge`).toBe(g.li[0].left);
    }
    for (const row of g.li) { expect(row.h).toBeGreaterThanOrEqual(26); expect(row.h).toBeLessThanOrEqual(30); }
    expect(g.part.top, '"Part of" is below the last entry').toBeGreaterThan(g.li[5].top);
    expect(g.part.bottom, '"Part of" is inside the sidebar').toBeLessThanOrEqual(g.side.bottom);
    expect(g.part.top).toBeGreaterThanOrEqual(g.side.top);
    expect(g.wide, 'no sideways scroll').toBe(false);
    await context.close();
  });
}

test('every footer entry goes to a page that answers', async ({ browser }) => {
  const { page, context } = await open(browser, 1440, 900, false, 'light');
  const hrefs = await page.locator('[data-sidebar-footer] li a').evaluateAll(as => as.map(a => (a as HTMLAnchorElement).href));
  expect(hrefs).toHaveLength(6);
  for (const href of hrefs) {
    if (!href.startsWith(baseURL)) { expect(['https://github.com/navi-crwn/LinkHop', 'https://hel.ink/announcements']).toContain(href); continue; }
    const res = await context.request.get(href, { maxRedirects: 0 });
    expect(res.status(), href).toBeLessThan(400);
  }
  await context.close();
});

test('the footer fits the phone drawer in German and Russian too', async ({ browser }) => {
  for (const lang of ['de', 'ru']) {
    const { page, context } = await open(browser, 390, 800, true, 'dark', lang);
    await page.locator('.hop-family-row').scrollIntoViewIfNeeded();
    const wide = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
    expect(wide, lang).toBe(false);
    const clipped = await page.evaluate(() => [...document.querySelectorAll('[data-sidebar-footer] li a')].filter(a => a.scrollWidth > a.clientWidth + 1).length);
    expect(clipped, lang).toBe(0);
    await context.close();
  }
});
