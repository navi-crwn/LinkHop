// The family colour set read before first paint (resources/js/theme-boot.js): cookie hop_palette -> data-palette on <html>,
// dashboard only (the script tag says so), classic = no attribute, junk = classic, the cookie written like hop_theme.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/js/theme-boot.js', import.meta.url), 'utf8');

function run({ host = 'localhost', cookie = '', on = true } = {}) {
    const writes = [];
    const attrs = {};
    const document = {
        currentScript: { getAttribute: (name) => (name === 'data-palette' && on ? 'on' : null) },
        documentElement: { classList: { add() {} }, setAttribute: (k, v) => { attrs[k] = v; }, removeAttribute: (k) => { delete attrs[k]; } },
        get cookie() { return cookie; },
        set cookie(v) { writes.push(v); },
    };
    const window = { location: { hostname: host }, localStorage: { getItem: () => null, setItem() {} }, matchMedia: () => ({ matches: false }) };
    vm.runInNewContext(source, { window, document });
    return { attrs, writes, window };
}

test('mist and sand are applied before paint; classic and anything else is no attribute', () => {
    assert.equal(run({ cookie: 'hop_palette=mist' }).attrs['data-palette'], 'mist');
    assert.equal(run({ cookie: 'a=1; hop_palette=sand; b=2' }).attrs['data-palette'], 'sand');
    for (const cookie of ['', 'hop_palette=classic', 'hop_palette=neon', 'hop_palette=', 'hop_palette=MIST']) {
        assert.equal(run({ cookie }).attrs['data-palette'], undefined, cookie);
    }
});

test('a page that does not turn it on (public pages) never gets an attribute and has no setter', () => {
    const r = run({ cookie: 'hop_palette=mist', on: false });
    assert.equal(r.attrs['data-palette'], undefined);
    assert.equal(r.window.hopSetPalette, undefined);
});

test('hopSetPalette writes the cookie like hop_theme and sets or clears the attribute; junk becomes classic', () => {
    const r = run({ host: 'link.hel.ink' });
    r.window.hopSetPalette('mist');
    assert.equal(r.writes.at(-1), 'hop_palette=mist; Path=/; Max-Age=31536000; SameSite=Lax; domain=.hel.ink; Secure');
    assert.equal(r.attrs['data-palette'], 'mist');
    r.window.hopSetPalette('classic');
    assert.equal(r.attrs['data-palette'], undefined);
    r.window.hopSetPalette('<script>');
    assert.match(r.writes.at(-1), /^hop_palette=classic;/);
    assert.doesNotMatch(run({ host: 'localhost' }).window.hopSetPalette('sand') ?? '', /domain/);
});

test('it does not touch the light and dark theme', () => {
    const r = run({ cookie: 'hop_palette=sand' });
    assert.equal(r.writes.length, 0);
});
