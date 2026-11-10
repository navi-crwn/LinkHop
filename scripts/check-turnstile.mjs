// Browser check for the guest form on the home page: Alpine must have initialised the form and the
// Turnstile widget must have been rendered (and, with Cloudflare's always-pass test key, filled the token).
//   TURNSTILE_SITE_KEY=1x00000000000000000000AA TURNSTILE_SECRET_KEY=1x0000000000000000000000000000000AA \
//   php artisan serve --port=8000 &   node scripts/check-turnstile.mjs http://127.0.0.1:8000
// Never submits the form. Needs network access to challenges.cloudflare.com.
import { chromium } from '@playwright/test';

const base = process.argv[2] || 'http://127.0.0.1:8000';
const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
const errors = [];
page.on('pageerror', (e) => errors.push(e.message));
await page.goto(base + '/', { waitUntil: 'load' });
const form = page.locator('form[action$="/shorten"]');
await form.scrollIntoViewIfNeeded();

let state = {};
for (let i = 0; i < 60; i++) {
    state = await page.evaluate(() => {
        const f = document.querySelector('form[action$="/shorten"]');
        const d = f && f._x_dataStack ? window.Alpine.$data(f) : null;
        return { alpine: !!d, widgetId: d?.widgetId ?? null, token: (d?.token || '').length, status: d?.status ?? null };
    });
    if (state.alpine && state.widgetId && state.token) break;
    await page.waitForTimeout(500);
}
await browser.close();

const failures = [];
if (!state.alpine) failures.push('the guest form was not initialised by Alpine');
if (!state.widgetId) failures.push('turnstile.render was never called (no widget id)');
if (!state.token) failures.push('no token arrived (with the always-pass test key one must)');
if (errors.length) failures.push('page errors: ' + errors.join(' | '));
if (failures.length) {
    console.error('FAIL', JSON.stringify(state), failures.join('; '));
    process.exit(1);
}
console.log('ok', JSON.stringify(state));
