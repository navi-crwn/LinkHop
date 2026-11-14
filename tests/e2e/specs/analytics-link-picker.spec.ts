/**
 * The Analytics link picker in a real browser: it opens with a few links, finds the rest by searching, and a link
 * chosen from it stays chosen after the page reloads. Needs the app with E2E_HOOKS=true (bash tests/e2e/serve.sh).
 */
import { test, expect } from '@playwright/test';

const baseURL = process.env.E2E_BASE_URL ?? `http://127.0.0.1:${Number(process.env.E2E_PORT ?? 8130)}`;
test.setTimeout(90_000);

test('the picker lists a few links, searches for the rest and keeps the choice', async ({ browser }) => {
  const ip = '198.18.78.1';
  const context = await browser.newContext({ baseURL, viewport: { width: 1440, height: 900 }, extraHTTPHeaders: { 'X-Forwarded-For': ip } });
  await context.route(url => !url.href.startsWith(baseURL), route => route.abort());
  await context.addInitScript(() => { try { localStorage.setItem('cookie_consent', 'accepted'); } catch (e) { /* private window */ } });
  const res = await fetch(`${baseURL}/_e2e/persona`, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Forwarded-For': ip }, body: '{}' });
  const me = await res.json() as { email: string; links: string[] };
  expect((await context.request.post('/_e2e/login', { form: { email: me.email } })).ok()).toBe(true);
  const page = await context.newPage();
  await page.goto('/analytics');

  const form = page.locator('form[x-ref="form"]');
  await form.locator('[data-ctl="select"]').first().click();
  const options = form.locator('[data-ctl="option"]');
  await expect(options).toHaveCount(1 + me.links.length); // "All links" and the persona's own links

  const wanted = me.links[1];
  await form.getByPlaceholder('Search links…').fill(wanted);
  await expect(options).toHaveCount(2);
  await expect(options.nth(1)).toContainText(`/${wanted}`);

  await options.nth(1).click();
  await page.waitForURL(/link_id=\d+/);
  await expect(page.locator('form [data-ctl="select"]').first()).toContainText(`/${wanted}`);
  await context.close();
});
