/**
 * The dashboard polish in a real browser: where the Folder picker sits in the create dialog, the QR sheet's Reset,
 * the analytics layout, the dashboard QR codes page, the family row in the sidebar and the top bars on a phone.
 * String checks on the HTML cannot see any of this. Needs the app with E2E_HOOKS=true (bash tests/e2e/serve.sh).
 */
import { test, expect, type Browser, type Page } from '@playwright/test';

const baseURL = process.env.E2E_BASE_URL ?? `http://127.0.0.1:${Number(process.env.E2E_PORT ?? 8130)}`;
test.setTimeout(120_000);
test.use({ actionTimeout: 15_000 });
const slow = { timeout: 20_000 };
let ipCounter = 0;
const fakeIp = () => { const n = (process.pid * 104729 + ++ipCounter * 11) % 65534 + 1; return `198.18.${n >> 8}.${n & 255}`; };

type Opts = { width?: number; height?: number; lang?: string; theme?: string; member?: boolean; mobile?: boolean };

async function open(browser: Browser, o: Opts = {}) {
  const { width = 1440, height = 900, lang = 'en', theme = 'light', member = true, mobile = false } = o;
  const ip = fakeIp();
  const context = await browser.newContext({ baseURL, viewport: { width, height }, extraHTTPHeaders: { 'X-Forwarded-For': ip }, hasTouch: mobile, isMobile: mobile });
  await context.route(url => !url.href.startsWith(baseURL), route => route.abort());
  await context.addInitScript(() => { try { localStorage.setItem('cookie_consent', 'accepted'); } catch (e) { /* private window */ } });
  await context.addCookies([{ name: 'hop_lang', value: lang, url: baseURL }, { name: 'hop_theme', value: theme, url: baseURL }]);
  if (member) {
    const res = await fetch(`${baseURL}/_e2e/persona`, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Forwarded-For': ip }, body: '{}' });
    const me = await res.json() as { email: string };
    const login = await context.request.post('/_e2e/login', { form: { email: me.email } });
    expect(login.ok()).toBe(true);
  }
  const page = await context.newPage();
  page.on('dialog', d => d.accept().catch(() => {}));
  // E2E_CPU_THROTTLE=6 makes this PC as slow as a CI runner, to reproduce timing-dependent failures
  if (process.env.E2E_CPU_THROTTLE) { await (await context.newCDPSession(page)).send('Emulation.setCPUThrottlingRate', { rate: Number(process.env.E2E_CPU_THROTTLE) }); }
  return { page, context };
}

const dialog = (page: Page) => page.locator('[x-data="linkCreationModal()"] [aria-label="Create new link"]');
const sheet = (page: Page, name: string) => page.locator(`[data-sheet="${name}"]`);
const box = async (loc: ReturnType<Page['locator']>) => (await loc.boundingBox())!;

/**
 * A measurement taken in one go inside the page, repeated until two reads in a row agree. The phone drawer slides in
 * (300 ms) and a tab switch repaints, so a single read on a slow machine can land mid-move: two separate reads of
 * two boxes can then disagree by the distance travelled between them. Returns the settled value, or fails with the
 * last value read.
 */
async function settledRead<T>(read: () => Promise<T | null>, what: string): Promise<T> {
  let last = '';
  let value: T | null = null;
  await expect.poll(async () => {
    value = await read();
    const now = JSON.stringify(value);
    const same = value !== null && now === last;
    last = now;
    return same;
  }, { message: `${what} settles`, timeout: 15_000, intervals: [120] }).toBe(true);

  return value as unknown as T;
}

function capturePosts(page: Page) {
  const bodies: Record<string, unknown>[] = [];
  page.on('request', r => { if (r.method() === 'POST' && /\/links$/.test(new URL(r.url()).pathname)) bodies.push(r.postDataJSON()); });
  return bodies;
}

/* ------------------------------------------------------------------ L-1 */
for (const entry of ['/links', '/links?create=qr', '/links?create=deeplink']) {
  test(`the Folder picker is in the left column beside Tags, the right column holds the preview only (${entry})`, async ({ browser }) => {
    const { page, context } = await open(browser);
    await page.goto(entry);
    if (entry === '/links') { await page.locator('.create-link-trigger').first().click(); }
    // a preset opens its sheet over the dialog (the dialog waits behind it): close the sheet to look at the dialog itself
    if (entry !== '/links') { await expect(page.locator('[data-sheet]:visible')).toHaveCount(1, slow); await page.keyboard.press('Escape'); await expect(page.locator('[data-sheet]:visible')).toHaveCount(0); }
    await expect(dialog(page)).toBeVisible(slow);
    const folder = await box(dialog(page).locator('button[data-select="folders"]'));
    const tags = await box(dialog(page).locator('button[data-select="tags"]'));
    const preview = await box(dialog(page).getByText('Link Preview', { exact: true }));
    expect(folder.x + folder.width, 'Folder ends before the preview column starts').toBeLessThan(preview.x);
    expect(folder.y, 'Folder is above Tags').toBeLessThan(tags.y);
    expect(Math.abs(folder.x - tags.x), 'Folder and Tags share the left edge').toBeLessThan(2);
    // nothing called "Folder" is left in the right column
    const folderLabel = await box(dialog(page).locator('label', { hasText: /^\s*Folder\s*$/ }));
    expect(folderLabel.x + folderLabel.width).toBeLessThan(preview.x);
    await context.close();
  });
}

test('a link made with a chosen Folder is saved in that folder, then another link starts clean', async ({ browser }) => {
  const { page, context } = await open(browser);
  const posts = capturePosts(page);
  await page.goto('/links');
  await page.locator('.create-link-trigger').first().click();
  await dialog(page).locator('input[x-model="formData.target_url"]').fill('https://example.com/folder-one');
  await dialog(page).locator('button[data-select="folders"]').click();
  const optionsList = dialog(page).locator('button[data-ctl="option"]');
  await expect(optionsList.first()).toBeVisible();
  const picked = (await optionsList.first().innerText()).trim();
  await optionsList.first().click();
  await expect(dialog(page).locator('button[data-select="folders"]')).toContainText(picked);
  await dialog(page).locator('button[type="submit"]').first().click();
  await expect.poll(() => posts.length).toBe(1);
  expect(posts[0].folder_id, 'the chosen folder travels with the link').toBeTruthy();
  await context.close();
});

/* ------------------------------------------------------------------ L-4 */
test('Reset in the QR sheet only restores the look: the QR stays on, and focus returns to a visible control', async ({ browser }) => {
  const { page, context } = await open(browser);
  await page.goto('/links');
  await page.locator('.create-link-trigger').first().click();
  await page.locator('[data-chip="qr"]').click();
  const toggle = sheet(page, 'qr').locator('[data-qr-toggle]');
  await toggle.check();
  await sheet(page, 'qr').getByRole('button', { name: 'Reset' }).click();
  await expect(toggle, 'the QR is still switched on after Reset').toBeChecked();
  await sheet(page, 'qr').getByRole('button', { name: 'Done' }).click();
  await expect(sheet(page, 'qr')).toBeHidden();
  const focused = await page.evaluate(() => { const a = document.activeElement as HTMLElement; const r = a.getBoundingClientRect(); return { tag: a.tagName, visible: !!a.offsetParent && r.width > 0 }; });
  expect(focused.tag).not.toBe('BODY');
  expect(focused.visible).toBe(true);
  await context.close();
});

test('a sheet opened by a preset has somewhere visible to return focus to', async ({ browser }) => {
  const { page, context } = await open(browser);
  await page.goto('/links?create=qr');
  await expect(sheet(page, 'qr')).toBeVisible(slow);
  await page.keyboard.press('Escape');
  await expect(sheet(page, 'qr')).toBeHidden();
  const focused = await page.evaluate(() => { const a = document.activeElement as HTMLElement; return { tag: a.tagName, visible: !!a.offsetParent }; });
  expect(focused.tag).not.toBe('BODY');
  expect(focused.visible).toBe(true);
  await context.close();
});

/* ------------------------------------------------------------------ L-2 */
test('analytics: the link list is as wide as its button, the chart shares a row with the numbers on a wide screen', async ({ browser }) => {
  const { page, context } = await open(browser, { width: 1440, height: 1000 });
  await page.goto('/analytics');
  await expect(page.locator('#analyticsChart')).toBeVisible(slow);
  const trigger = page.getByRole('button', { name: /All links/ }).first();
  await trigger.click();
  const panel = page.locator('form .glass-popover').first();
  await expect(panel).toBeVisible();
  const t = await box(trigger), p = await box(panel);
  expect(p.width, 'the list is not wider than the button').toBeLessThanOrEqual(t.width + 1);
  expect(p.width).toBeLessThanOrEqual(449);
  await page.keyboard.press('Escape');
  // chart and numbers on one row (from 1280 px), the chart about two thirds
  const chart = await box(page.locator('section', { has: page.locator('#analyticsChart') }));
  const kpi = await box(page.locator('.glass.grid', { hasText: 'Unique visitors' }));
  expect(Math.abs(chart.y - kpi.y), 'same row').toBeLessThan(2);
  expect(chart.width / kpi.width, 'the chart takes two thirds').toBeGreaterThan(1.7);
  expect(chart.height, 'the chart is taller than it was').toBeGreaterThan(200);
  await context.close();
});

test('analytics: below 1280 px the chart and the numbers stack, and nothing scrolls sideways on a phone', async ({ browser }) => {
  for (const width of [1100, 390]) {
    const { page, context } = await open(browser, { width, height: 900, mobile: width < 700 });
    await page.goto('/analytics');
    await expect(page.locator('#analyticsChart')).toBeVisible(slow);
    const chart = await box(page.locator('section', { has: page.locator('#analyticsChart') }));
    const kpi = await box(page.locator('.glass.grid', { hasText: 'Unique visitors' }));
    expect(chart.y, `stacked at ${width}`).toBeGreaterThan(kpi.y + kpi.height - 2);
    const scroll = await page.evaluate(() => ({ s: document.documentElement.scrollWidth, w: window.innerWidth }));
    expect(scroll.s, `no sideways scroll at ${width}`).toBeLessThanOrEqual(width);
    // the tab row wraps instead of scrolling
    const tablist = page.getByRole('tablist', { name: 'Breakdown' });
    const sw = await tablist.evaluate(el => ({ s: el.scrollWidth, c: el.clientWidth }));
    expect(sw.s, `the tabs fit at ${width}`).toBeLessThanOrEqual(sw.c + 1);
    await context.close();
  }
});

test('analytics: every sub-tab row sits left, right under its tab, and looks smaller than the tab', async ({ browser }) => {
  for (const width of [1440, 390]) {
    const { page, context } = await open(browser, { width, height: 1000, mobile: width < 700 });
    await page.goto('/analytics');
    await expect(page.locator('#analyticsChart')).toBeVisible(slow);
    for (const tab of ['Locations', 'Sources', 'Technology', 'Links']) {
      await page.getByRole('tab', { name: tab, exact: true }).click();
      await expect(page.getByRole('tab', { name: tab, exact: true })).toHaveAttribute('aria-selected', 'true');
      const m = await settledRead(() => page.evaluate((name) => {
        const list = document.querySelector('[role="tablist"][aria-label="Breakdown"]') as HTMLElement | null;
        const tabBtn = [...document.querySelectorAll('[role="tab"]')].find(e => e.textContent?.trim() === name) as HTMLElement | undefined;
        const rowEl = [...document.querySelectorAll('.subtab-row')].find(e => (e as HTMLElement).checkVisibility()) as HTMLElement | undefined;
        const subEl = rowEl?.querySelector('.subtab') as HTMLElement | null | undefined;
        if (!list || !tabBtn || !rowEl || !subEl) return null;
        const l = list.getBoundingClientRect(), r = rowEl.getBoundingClientRect();
        return { dx: Math.round(r.x - l.x), gap: Math.round(r.y - (l.y + l.height)), subH: Math.round(subEl.getBoundingClientRect().height), tabH: Math.round(tabBtn.getBoundingClientRect().height) };
      }, tab), `${tab} sub-tabs (${width})`);
      expect(Math.abs(m.dx), `${tab} sub-tabs start at the left edge of the tabs (${width})`).toBeLessThan(2);
      expect(m.gap, `${tab} sub-tabs sit right under the tabs (${width})`).toBeLessThan(24);
      expect(m.gap, `${tab} sub-tabs are under, not beside`).toBeGreaterThan(-1);
      expect(m.subH, `${tab} sub-tabs are smaller than the tabs`).toBeLessThan(m.tabH);
    }
    await context.close();
  }
});

/* ------------------------------------------------------------------ L-3 */
test('QR codes in the dashboard: from the menu, create a Wi-Fi code, save, find it after a reload, download and delete it', async ({ browser }) => {
  const { page, context } = await open(browser);
  await page.goto('/links');
  await page.locator('aside a[href$="/qr-codes"]').click();
  await expect(page).toHaveURL(/\/qr-codes$/);
  await expect(page.locator('aside')).toBeVisible(); // still inside the dashboard frame
  await page.getByRole('button', { name: 'Create QR' }).click();
  await page.locator('#qc-w-ssid').fill('Cafe Test');
  await page.locator('#qc-w-pass').fill('made-up-pass-1');
  await expect(page.locator('#qc-preview canvas')).toBeVisible(slow);
  await page.locator('#qc-save').click();
  await page.waitForURL(/\/qr-codes$/);
  await expect(page.locator('#qc-saved')).toContainText('Cafe Test', slow);
  await page.reload();
  await expect(page.locator('#qc-saved')).toContainText('Cafe Test');
  await expect(page.locator('[data-qc-thumb] canvas').first()).toBeVisible(slow);
  const [download] = await Promise.all([page.waitForEvent('download'), page.locator('[data-qc-dl="png"]').first().click()]);
  expect(download.suggestedFilename()).toBe('qr-cafe-test.png');
  await page.locator('[data-qc-view]').first().click();
  await expect(page.locator('#qc-view')).toBeVisible();
  await page.keyboard.press('Escape');
  await expect(page.locator('#qc-view')).toBeHidden();
  await page.getByRole('button', { name: 'Delete' }).first().click();
  await expect(page.locator('#qc-saved')).toHaveCount(0, slow);
  await context.close();
});

test('a short link made with a QR code shows up in the same list', async ({ browser }) => {
  const { page, context } = await open(browser);
  const slug = 'k7qr' + Math.random().toString(36).slice(2, 7);
  const posts = capturePosts(page);
  await page.goto('/links?create=qr');
  await expect(sheet(page, 'qr')).toBeVisible(slow);
  await sheet(page, 'qr').getByRole('button', { name: 'Done' }).click();
  await dialog(page).locator('input[x-model="formData.target_url"]').fill('https://example.com/with-qr');
  await dialog(page).locator('input[x-model="formData.slug"]').fill(slug);
  await dialog(page).locator('button[type="submit"]').first().click();
  await expect.poll(() => posts.length).toBe(1);
  await expect(page.getByRole('button', { name: 'Create another link' })).toBeVisible(slow);
  await page.goto('/qr-codes');
  await expect(page.locator('#qc-links')).toContainText(`/${slug}`);
  await context.close();
});

/* ------------------------------------------------------------------ L-5 */
test('"Part of Hop Easy Link" is the last thing in the sidebar and appears once, on a wide screen and in the phone drawer', async ({ browser }) => {
  for (const [width, mobile] of [[1440, false], [390, true]] as const) {
    const { page, context } = await open(browser, { width, height: 800, mobile });
    await page.goto('/dashboard');
    if (mobile) { await page.getByRole('button', { name: 'Open menu' }).click(); }
    await expect(page.locator('.hop-family-row')).toHaveCount(1);
    await expect(page.locator('main .hop-family-row')).toHaveCount(0);
    // the footer scrolls with the menu, so on a short screen the end of the sidebar has to be scrolled into view first
    await page.locator('.hop-family-row').scrollIntoViewIfNeeded();
    // the drawer slides in over 300 ms: read the sidebar, the row and the menu items together once it has stopped
    const g = await settledRead(() => page.evaluate(() => {
      const sideEl = document.querySelector('aside > div') as HTMLElement | null;
      const rowEl = document.querySelector('.hop-family-row') as HTMLElement | null;
      if (!sideEl || !rowEl) return null;
      const s = sideEl.getBoundingClientRect(), r = rowEl.getBoundingClientRect();
      const bottoms = [...document.querySelectorAll('aside nav a, aside nav button')].map(e => e.getBoundingClientRect()).filter(b => b.height > 0).map(b => b.bottom);
      return { sx: s.x, sy: s.y, sh: s.height, rx: r.x, ry: r.y, rh: r.height, lowest: Math.max(...bottoms) };
    }), `the sidebar (${width})`);
    expect(g.ry, `the row is inside the sidebar (${width})`).toBeGreaterThan(g.sy);
    expect(g.ry + g.rh, `the row is inside the sidebar (${width})`).toBeLessThanOrEqual(g.sy + g.sh);
    expect(g.sy + g.sh - (g.ry + g.rh), `the row is at the bottom of the sidebar (${width})`).toBeLessThan(24);
    expect(g.rx - g.sx, `the row is at the left (${width})`).toBeLessThan(40);
    // nothing in the sidebar lies under it
    expect(g.lowest).toBeLessThanOrEqual(g.ry + 1);
    await context.close();
  }
});

/* ------------------------------------------------------------------ L-9 */
for (const lang of ['de', 'ru']) {
  for (const width of [375, 390]) {
    test(`top bars fit at ${width} px in ${lang}: public (guest, member) and dashboard`, async ({ browser }) => {
      for (const [path, member] of [['/qr', false], ['/qr', true], ['/about', false], ['/dashboard', true]] as const) {
        const { page, context } = await open(browser, { width, height: 800, lang, member, mobile: true });
        await page.goto(path);
        await expect(page.locator('header').first()).toBeVisible(slow);
        const m = await page.evaluate(([sel, W]) => {
          const bar = document.querySelector(sel as string) as HTMLElement;
          const items = [...bar.querySelectorAll('a, button, summary')].filter((e: any) => e.checkVisibility() && e.getBoundingClientRect().width > 0) as HTMLElement[];
          const boxes = items.map(e => { const r = e.getBoundingClientRect(); return { t: (e.getAttribute('aria-label') || e.textContent || '').trim().slice(0, 20), l: r.left, r: r.right, top: r.top, b: r.bottom }; });
          const clash: string[] = [];
          for (let i = 0; i < items.length; i++) for (let j = i + 1; j < items.length; j++) {
            if (items[i].contains(items[j]) || items[j].contains(items[i])) continue;
            const a = boxes[i], c = boxes[j];
            if (a.l < c.r - 1 && c.l < a.r - 1 && a.top < c.b - 1 && c.top < a.b - 1) clash.push(`${a.t} x ${c.t}`);
          }
          return { scrollW: document.documentElement.scrollWidth, barH: bar.getBoundingClientRect().height, clash, outside: boxes.filter(b => b.r > (W as number) + 1 || b.l < -1).map(b => b.t) };
        }, [path === '/dashboard' ? 'header > div' : 'header nav', width] as const);
        const label = `${path} ${member ? 'member' : 'guest'} ${width} ${lang}`;
        expect(m.scrollW, `no sideways scroll: ${label}`).toBeLessThanOrEqual(width);
        expect(m.barH, `one row: ${label}`).toBeLessThan(70);
        expect(m.clash, `nothing overlaps: ${label}`).toEqual([]);
        expect(m.outside, `nothing outside the screen: ${label}`).toEqual([]);
        // the language menu opens inside the screen
        const sw = page.locator('header .hop-switcher summary').first();
        if (await sw.count()) {
          await sw.click();
          const panelBox = await page.locator('header .hop-switcher[open] .hop-switcher-panel').first().boundingBox();
          if (panelBox) { expect(panelBox.x, `menu left edge: ${label}`).toBeGreaterThanOrEqual(-1); expect(panelBox.x + panelBox.width, `menu right edge: ${label}`).toBeLessThanOrEqual(width + 1); }
        }
        await context.close();
      }
    });
  }
}
