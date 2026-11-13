/**
 * The create dialog and the analytics page in a real browser: what string checks on the HTML cannot see.
 * Needs the app with E2E_HOOKS=true (bash tests/e2e/serve.sh).
 */
import { test, expect, type Browser, type Page } from '@playwright/test';

const baseURL = process.env.E2E_BASE_URL ?? `http://127.0.0.1:${Number(process.env.E2E_PORT ?? 8130)}`;
test.setTimeout(90_000);
test.use({ actionTimeout: 15_000 });
const slow = { timeout: 20_000 };
let ipCounter = 0;
const fakeIp = () => { const n = (process.pid * 104729 + ++ipCounter * 7) % 65534 + 1; return `198.19.${n >> 8}.${n & 255}`; };

async function signIn(browser: Browser, viewport = { width: 1440, height: 900 }) {
  const ip = fakeIp();
  const res = await fetch(`${baseURL}/_e2e/persona`, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Forwarded-For': ip }, body: '{}' });
  const me = await res.json() as { email: string };
  const context = await browser.newContext({ baseURL, viewport, extraHTTPHeaders: { 'X-Forwarded-For': ip } });
  await context.route(url => !url.href.startsWith(baseURL), route => route.abort());
  const login = await context.request.post('/_e2e/login', { form: { email: me.email } });
  expect(login.ok()).toBe(true);
  const page = await context.newPage();
  page.on('dialog', d => d.accept().catch(() => {}));
  return { page, context };
}

const dialog = (page: Page) => page.locator('[x-data="linkCreationModal()"] [aria-label="Create new link"]');
const url = (page: Page) => page.locator('[x-data="linkCreationModal()"] input[x-model="formData.target_url"]');
const sheet = (page: Page, name: string) => page.locator(`[data-sheet="${name}"]`);

/** Collect the JSON bodies posted to the link store. */
function capturePosts(page: Page) {
  const bodies: Record<string, unknown>[] = [];
  page.on('request', r => { if (r.method() === 'POST' && /\/links$/.test(new URL(r.url()).pathname)) bodies.push(r.postDataJSON()); });
  return bodies;
}

async function createLink(page: Page, target: string, bodies: unknown[]) {
  const before = bodies.length;
  await url(page).fill(target);
  await dialog(page).locator('button[type="submit"]').first().click();
  await expect.poll(() => bodies.length).toBe(before + 1);
  await expect(page.getByRole('button', { name: 'Create another link' })).toBeVisible(slow);
}

test('the QR chip only opens its sheet; the QR is on only when asked for, and never rides to the next link', async ({ browser }) => {
  const { page, context } = await signIn(browser);
  const posts = capturePosts(page);
  await page.goto('/links');
  await page.locator('.create-link-trigger').first().click();
  await expect(url(page)).toBeVisible();

  // just looking at the design must not switch the QR on
  await page.locator('[data-chip="qr"]').click();
  await expect(sheet(page, 'qr')).toBeVisible();
  await expect(sheet(page, 'qr').locator('[data-qr-toggle]')).not.toBeChecked();
  await page.keyboard.press('Escape');
  await expect(sheet(page, 'qr')).toBeHidden();
  await createLink(page, 'https://example.com/one', posts);
  expect(posts[0].generate_qr).toBe(false);

  // the switch inside the sheet turns it on for this link only
  await page.getByRole('button', { name: 'Create another link' }).click();
  await page.locator('[data-chip="qr"]').click();
  await sheet(page, 'qr').locator('[data-qr-toggle]').check();
  await sheet(page, 'qr').getByRole('button', { name: 'Done' }).click();
  await createLink(page, 'https://example.com/two', posts);
  expect(posts[1].generate_qr).toBe(true);

  await page.getByRole('button', { name: 'Create another link' }).click();
  await createLink(page, 'https://example.com/three', posts);
  expect(posts[2].generate_qr).toBe(false);
  await context.close();
});

test('Create > QR code switches the QR on for that link and the next plain link does not carry it', async ({ browser }) => {
  const { page, context } = await signIn(browser);
  const posts = capturePosts(page);
  await page.goto('/links?create=qr');
  await expect(sheet(page, 'qr')).toBeVisible();
  await expect(sheet(page, 'qr').locator('[data-qr-toggle]')).toBeChecked();
  await sheet(page, 'qr').getByRole('button', { name: 'Done' }).click();
  await createLink(page, 'https://example.com/qr-one', posts);
  expect(posts[0].generate_qr).toBe(true);
  await page.getByRole('button', { name: 'Create another link' }).click();
  await createLink(page, 'https://example.com/plain', posts);
  expect(posts[1].generate_qr).toBe(false);
  await context.close();
});

test('?create=… is read once: reloading after a link was made does not reopen the dialog', async ({ browser }) => {
  const { page, context } = await signIn(browser);
  const posts = capturePosts(page);
  await page.goto('/links?create=qr');
  await expect(sheet(page, 'qr')).toBeVisible();
  await expect.poll(() => new URL(page.url()).searchParams.has('create')).toBe(false);
  await sheet(page, 'qr').getByRole('button', { name: 'Done' }).click();
  await createLink(page, 'https://example.com/once', posts);
  // closing the dialog reloads the page for the new link
  await dialog(page).locator('button[aria-label="Close"]').first().click();
  await page.waitForLoadState('load');
  await page.waitForTimeout(1500); // the reload is the page's answer to a new link; give it time to settle
  await expect(dialog(page)).toBeHidden();
  await expect(sheet(page, 'qr')).toBeHidden();
  await context.close();
});

test('the UTM sheet starts from the new address, not from the link before', async ({ browser }) => {
  const { page, context } = await signIn(browser);
  const posts = capturePosts(page);
  await page.goto('/links');
  await page.locator('.create-link-trigger').first().click();
  await url(page).fill('https://example.com/a?utm_source=newsletter');
  await page.locator('[data-chip="utm"]').click();
  await expect(sheet(page, 'utm').locator('input[x-model="f.source"]')).toHaveValue('newsletter');
  await sheet(page, 'utm').locator('button.btn-primary').last().click();
  await createLink(page, 'https://example.com/a?utm_source=newsletter', posts);
  await page.getByRole('button', { name: 'Create another link' }).click();
  await url(page).fill('https://example.com/b');
  await page.locator('[data-chip="utm"]').click();
  await expect(sheet(page, 'utm').locator('input[x-model="f.source"]')).toHaveValue('');
  await expect(sheet(page, 'utm').locator('[role="status"]')).toBeHidden();
  await context.close();
});

test('the rotator and the app store link sheets say why they are paused', async ({ browser }) => {
  const { page, context } = await signIn(browser);
  await page.goto('/links');
  await page.locator('.create-link-trigger').first().click();
  await page.locator('[data-chip="app"]').click();
  await sheet(page, 'app').locator('[data-store-links] input[type="checkbox"]').first().check();
  await sheet(page, 'app').getByRole('button', { name: 'Done' }).click();
  await page.locator('[data-chip="wa"]').click();
  await expect(sheet(page, 'wa').locator('[data-chip-blocked="wa"]')).toBeVisible();
  await context.close();
});

test('the Done and Reset buttons of the QR and preview sheets stay on screen in a short window', async ({ browser }) => {
  for (const viewport of [{ width: 1440, height: 700 }, { width: 844, height: 390 }]) {
    const { page, context } = await signIn(browser, viewport);
    await page.goto('/links');
    await page.locator('.create-link-trigger').first().click();
    for (const name of ['qr', 'og']) {
      await page.locator(`[data-chip="${name}"]`).click();
      const done = sheet(page, name).getByRole('button', { name: 'Done' });
      await expect(done).toBeAttached();
      // the preview sheet scrolls as one page, so its buttons are reached by scrolling; the QR sheet keeps them in view
      if (name === 'og') { await done.scrollIntoViewIfNeeded(); }
      const box = (await done.boundingBox())!;
      expect(box.y + box.height, `${name} Done inside ${viewport.width}x${viewport.height}`).toBeLessThanOrEqual(viewport.height);
      await done.click();
    }
    await context.close();
  }
});

test('Escape closes one layer at a time: sheet, then the tag list, then the dialog', async ({ browser }) => {
  const { page, context } = await signIn(browser);
  await page.goto('/links');
  await page.locator('.create-link-trigger').first().click();
  await expect(dialog(page)).toBeVisible();
  await page.locator('[data-chip="utm"]').click();
  await expect(sheet(page, 'utm')).toBeVisible();
  await page.keyboard.press('Escape');
  await expect(sheet(page, 'utm')).toBeHidden();
  await expect(dialog(page)).toBeVisible();
  await dialog(page).locator('button[data-ctl="select"]').first().click();
  await page.keyboard.press('Escape');
  await expect(dialog(page)).toBeVisible();
  await page.keyboard.press('Escape');
  await expect(dialog(page)).toBeHidden();
  await context.close();
});

test('analytics keeps its open tab when the filter or the time range changes, and has a main heading', async ({ browser }) => {
  const { page, context } = await signIn(browser);
  const errors: string[] = [];
  page.on('pageerror', e => errors.push(e.message));
  await page.goto('/analytics');
  await expect(page.locator('h1')).toHaveCount(1);
  await page.getByRole('tab', { name: 'Technology' }).click();
  await expect(page.getByRole('tab', { name: 'Technology' })).toHaveAttribute('aria-selected', 'true');
  await page.getByRole('group', { name: 'Time range' }).getByRole('button', { name: /^48\s?h$/ }).click();
  await page.waitForURL(/hours=48/);
  await expect(page.getByRole('tab', { name: 'Technology' })).toHaveAttribute('aria-selected', 'true');
  await page.getByRole('tab', { name: 'Networks' }).click();
  await page.getByRole('group', { name: 'Time range' }).getByRole('button', { name: /^12\s?h$/ }).click();
  await page.waitForURL(/hours=12/);
  await expect(page.getByRole('tab', { name: 'Networks' })).toHaveAttribute('aria-selected', 'true');
  expect(errors).toEqual([]);
  await context.close();
});
