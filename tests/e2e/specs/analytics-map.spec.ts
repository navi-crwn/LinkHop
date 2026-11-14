/**
 * The Analytics world map in a real browser. Leaving the page while the country shapes are still downloading must not
 * be reported as a map error (the nightly journeys used to blame the next page for it); a map that is really broken
 * must still be reported and show its message. Needs the app with E2E_HOOKS=true (bash tests/e2e/serve.sh).
 */
import { test, expect } from '@playwright/test';

const baseURL = process.env.E2E_BASE_URL ?? `http://127.0.0.1:${Number(process.env.E2E_PORT ?? 8130)}`;
test.setTimeout(90_000);
const SHAPES = '**/vendor/geo/countries.geo.json';

async function signedIn(browser: import('@playwright/test').Browser, ip: string) {
  const context = await browser.newContext({ baseURL, viewport: { width: 1440, height: 900 }, extraHTTPHeaders: { 'X-Forwarded-For': ip } });
  await context.route(url => !url.href.startsWith(baseURL), route => route.abort());
  await context.addInitScript(() => { try { localStorage.setItem('cookie_consent', 'accepted'); } catch (e) { /* private window */ } });
  const res = await fetch(`${baseURL}/_e2e/persona`, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Forwarded-For': ip }, body: '{}' });
  const me = await res.json() as { email: string };
  expect((await context.request.post('/_e2e/login', { form: { email: me.email } })).ok()).toBe(true);
  const page = await context.newPage();
  const errors: string[] = [];
  page.on('console', m => { if (m.type() === 'error') errors.push(m.text()); });
  return { page, context, errors };
}

test('leaving the page while the country shapes download is not a map error', async ({ browser }) => {
  const { page, context, errors } = await signedIn(browser, '198.18.77.1');
  let asked!: () => void;
  const requested = new Promise<void>(resolve => { asked = resolve; });
  // The download never finishes, so it is still running when the visitor moves on.
  await page.route(SHAPES, () => { asked(); });
  await page.goto('/analytics');
  await requested;
  await page.goto('/dashboard');
  await page.waitForTimeout(1500);
  expect(errors.filter(e => e.includes('Map error'))).toEqual([]);
  await context.close();
});

test('a map that really fails to load is still reported and says so', async ({ browser }) => {
  const { page, context, errors } = await signedIn(browser, '198.18.77.2');
  await page.route(SHAPES, route => route.fulfill({ status: 404, body: 'gone' }));
  await page.goto('/analytics');
  await expect(page.locator('#world-map')).toContainText('Map unavailable right now');
  expect(errors.filter(e => e.includes('Map error'))).toHaveLength(1);
  await context.close();
});
