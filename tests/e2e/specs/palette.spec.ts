/**
 * The family colour set in a real browser: choosing Mist or Sand writes the cookie and the attribute, a reload keeps it,
 * a junk cookie is Classic, public pages stay Classic, the three choices stand in one row (also in the phone drawer), the
 * sidebar can still be scrolled to "Part of" on a 1280x720 screen, and the main text and the main button keep 4.5:1.
 * Needs the app with E2E_HOOKS=true (bash tests/e2e/serve.sh).
 */
import { test, expect, type Browser, type BrowserContext, type Page } from '@playwright/test';

const baseURL = process.env.E2E_BASE_URL ?? `http://127.0.0.1:${Number(process.env.E2E_PORT ?? 8130)}`;
test.setTimeout(120_000);
let n = 0;

async function open(browser: Browser, o: { width?: number; height?: number; mobile?: boolean; theme?: string; cookie?: string; member?: boolean } = {}) {
  const { width = 1440, height = 900, mobile = false, theme = 'light', cookie, member = true } = o;
  const ip = `198.18.83.${++n + (process.pid % 150)}`;
  const context = await browser.newContext({ baseURL, viewport: { width, height }, hasTouch: mobile, isMobile: mobile, extraHTTPHeaders: { 'X-Forwarded-For': ip } });
  await context.route(url => !url.href.startsWith(baseURL), route => route.abort());
  await context.addInitScript(() => { try { localStorage.setItem('cookie_consent', 'accepted'); } catch (e) { /* private window */ } });
  await context.addCookies([{ name: 'hop_lang', value: 'en', url: baseURL }, { name: 'hop_theme', value: theme, url: baseURL }, ...(cookie ? [{ name: 'hop_palette', value: cookie, url: baseURL }] : [])]);
  if (member) {
    const res = await fetch(`${baseURL}/_e2e/persona`, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Forwarded-For': ip }, body: '{}' });
    const me = await res.json() as { email: string };
    expect((await context.request.post('/_e2e/login', { form: { email: me.email } })).ok()).toBe(true);
  }
  return { page: await context.newPage(), context };
}

const palette = (page: Page) => page.evaluate(() => document.documentElement.getAttribute('data-palette'));
const cookieOf = async (context: BrowserContext, name: string) => (await context.cookies()).find(c => c.name === name)?.value;

test('choosing writes the cookie and the attribute, a reload keeps it, Classic clears it', async ({ browser }) => {
  const { page, context } = await open(browser);
  await page.goto('/dashboard');
  expect(await palette(page)).toBeNull();
  await page.locator('[data-palette-choice="mist"]').click();
  expect(await palette(page)).toBe('mist');
  expect(await cookieOf(context, 'hop_palette')).toBe('mist');
  await expect(page.locator('[data-palette-choice="mist"]')).toHaveAttribute('aria-pressed', 'true');
  await page.reload();
  expect(await palette(page)).toBe('mist');
  await expect(page.locator('[data-palette-choice="mist"]')).toHaveAttribute('aria-pressed', 'true');
  await page.locator('[data-palette-choice="sand"]').click();
  expect(await palette(page)).toBe('sand');
  await page.locator('[data-palette-choice="classic"]').click();
  expect(await palette(page)).toBeNull();
  expect(await cookieOf(context, 'hop_palette')).toBe('classic');
  await context.close();
});

test('a junk cookie is Classic; the theme is left alone; the keyboard works', async ({ browser }) => {
  const { page, context } = await open(browser, { cookie: 'neon' });
  await page.goto('/dashboard');
  expect(await palette(page)).toBeNull();
  await expect(page.locator('[data-palette-choice="classic"]')).toHaveAttribute('aria-pressed', 'true');
  await page.locator('[data-palette-choice="sand"]').focus();
  await page.keyboard.press('Enter');
  expect(await palette(page)).toBe('sand');
  expect(await cookieOf(context, 'hop_theme')).toBe('light');
  expect(await page.evaluate(() => document.documentElement.classList.contains('dark'))).toBe(false);
  await context.close();
});

test('public pages stay Classic whatever is chosen', async ({ browser }) => {
  const { page, context } = await open(browser, { cookie: 'mist', member: false });
  for (const path of ['/', '/about']) {
    await page.goto(path);
    expect(await palette(page), path).toBeNull();
  }
  await context.close();
});

for (const [width, height, mobile] of [[1440, 900, false], [1280, 720, false], [390, 844, true]] as const) {
  test(`three equal boxes in one row, and the sidebar still scrolls down to "Part of", ${width}x${height}`, async ({ browser }) => {
    const { page, context } = await open(browser, { width, height, mobile, cookie: 'mist' });
    await page.goto('/dashboard');
    if (mobile) { await page.getByRole('button', { name: 'Open menu' }).click(); }
    await page.locator('.hop-family-row').scrollIntoViewIfNeeded();
    await page.waitForTimeout(500);
    const g = await page.evaluate(() => {
      const b = [...document.querySelectorAll('[data-palette-choice]')].map(e => e.getBoundingClientRect());
      const row = document.querySelector('.hop-family-row')!.getBoundingClientRect();
      const side = document.querySelector('aside > div')!.getBoundingClientRect();
      const dot = document.querySelector('[data-palette-choice] .palette-dot')!.getBoundingClientRect();
      const name = document.querySelector('[data-palette-choice] span:last-child')!.getBoundingClientRect();
      return { tops: b.map(r => Math.round(r.top)), widths: b.map(r => Math.round(r.width)), rowBottom: row.bottom, sideBottom: side.bottom, dotAbove: dot.bottom <= name.top + 1, wide: document.documentElement.scrollWidth > document.documentElement.clientWidth };
    });
    expect(new Set(g.tops).size, 'the three boxes share one top').toBe(1);
    expect(Math.max(...g.widths) - Math.min(...g.widths), 'equal widths').toBeLessThanOrEqual(1);
    expect(g.dotAbove, 'the dot is above the name').toBe(true);
    expect(g.rowBottom, '"Part of" is reachable').toBeLessThanOrEqual(g.sideBottom);
    expect(g.wide).toBe(false);
    await context.close();
  });
}

const lum = (rgb: number[]) => { const c = rgb.map(v => { v /= 255; return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4; }); return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2]; };
const ratio = (a: number[], b: number[]) => { const [x, y] = [lum(a), lum(b)].sort((p, q) => q - p); return (x + 0.05) / (y + 0.05); };

for (const pal of ['mist', 'sand']) for (const theme of ['light', 'dark']) {
  test(`main text and main button keep 4.5:1 in ${pal}, ${theme}`, async ({ browser }) => {
    const { page, context } = await open(browser, { cookie: pal, theme });
    await page.goto('/dashboard');
    await page.waitForTimeout(500);
    const m = await page.evaluate(() => {
      const rgb = (s: string) => (s.match(/[\d.]+/g) ?? []).map(Number).slice(0, 3);
      const h1 = document.querySelector('h1') as HTMLElement;
      const card = document.querySelector('main .glass') as HTMLElement;
      const btn = document.querySelector('header .btn-primary') as HTMLElement;
      return { text: rgb(getComputedStyle(card).color), headText: rgb(getComputedStyle(h1).color), cardBg: rgb(getComputedStyle(card).backgroundColor), pageBg: rgb(getComputedStyle(document.body).backgroundColor), btnText: rgb(getComputedStyle(btn).color), btnBg: rgb(getComputedStyle(btn).backgroundColor) };
    });
    expect(ratio(m.headText, m.pageBg), 'heading on the page').toBeGreaterThanOrEqual(4.5);
    expect(ratio(m.text, m.cardBg), 'text on a card').toBeGreaterThanOrEqual(4.5);
    expect(ratio(m.btnText, m.btnBg), 'main button').toBeGreaterThanOrEqual(4.5);
    await context.close();
  });
}
