// The first-paint theme reader (resources/js/theme-boot.js): cookie hop_theme, then the old localStorage key,
// then the system setting; the domain rule of the family cookies; hopSetTheme writes both stores.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/js/theme-boot.js', import.meta.url), 'utf8');

function run({ host = 'localhost', cookie = '', stored = {}, systemDark = false, oldKey = null, storageBlocked = false } = {}) {
    const writes = [];
    const classes = new Set();
    const store = { ...stored };
    const document = {
        currentScript: oldKey ? { getAttribute: (name) => (name === 'data-old-key' ? oldKey : null) } : null,
        documentElement: { classList: { add: (c) => classes.add(c) } },
        get cookie() { return cookie; },
        set cookie(v) { writes.push(v); },
    };
    const localStorage = {
        getItem: (k) => { if (storageBlocked) throw new Error('blocked'); return k in store ? store[k] : null; },
        setItem: (k, v) => { if (storageBlocked) throw new Error('blocked'); store[k] = v; },
    };
    const window = { location: { hostname: host }, localStorage, matchMedia: () => ({ matches: systemDark }) };
    vm.runInNewContext(source, { window, document });
    return { dark: classes.has('dark'), writes, store, window };
}

test('the cookie wins over the old key and the system', () => {
    assert.equal(run({ cookie: 'a=1; hop_theme=light', stored: { 'helink-theme': 'dark' }, systemDark: true }).dark, false);
    assert.equal(run({ cookie: 'hop_theme=dark', stored: { 'helink-theme': 'light' } }).dark, true);
});

test('any other cookie value follows the system and does not fall back to the old key', () => {
    assert.equal(run({ cookie: 'hop_theme=system', stored: { 'helink-theme': 'dark' }, systemDark: false }).dark, false);
    assert.equal(run({ cookie: 'hop_theme=system', systemDark: true }).dark, true);
});

test('without the cookie an old choice is used once and copied into the cookie', () => {
    const r = run({ stored: { 'helink-theme': 'dark' } });
    assert.equal(r.dark, true);
    assert.deepEqual(r.writes, ['hop_theme=dark; Path=/; Max-Age=31536000; SameSite=Lax']);
});

test('the preview page reads its own old key', () => {
    const r = run({ stored: { theme: 'dark', 'helink-theme': 'light' }, oldKey: 'theme' });
    assert.equal(r.dark, true);
    assert.match(r.writes[0], /^hop_theme=dark;/);
});

test('nothing saved: the system decides and nothing is written', () => {
    assert.equal(run({ systemDark: true }).dark, true);
    const r = run({ systemDark: false, storageBlocked: true });
    assert.equal(r.dark, false);
    assert.deepEqual(r.writes, []);
});

test('on *.hel.ink the cookie is shared with the family; elsewhere it stays host-only', () => {
    for (const host of ['hel.ink', 'link.hel.ink', 'hub.hel.ink']) {
        assert.equal(run({ host, stored: { 'helink-theme': 'light' } }).writes[0], 'hop_theme=light; Path=/; Max-Age=31536000; SameSite=Lax; domain=.hel.ink; Secure');
    }
    for (const host of ['localhost', 'links.example.com', 'nothel.ink', 'hel.ink.example.com']) {
        assert.doesNotMatch(run({ host, stored: { 'helink-theme': 'light' } }).writes[0], /domain=/);
    }
});

test('hopSetTheme writes the cookie and keeps the old key', () => {
    const r = run({ host: 'link.hel.ink' });
    r.window.hopSetTheme('dark');
    assert.equal(r.writes.at(-1), 'hop_theme=dark; Path=/; Max-Age=31536000; SameSite=Lax; domain=.hel.ink; Secure');
    assert.equal(r.store['helink-theme'], 'dark');
});
