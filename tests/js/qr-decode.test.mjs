// Decode proof for the QR payloads, run in CI (node --test tests/js).
//
// Renders each payload to a PNG with the vendored qr-code-styling in headless Chromium (harness.html, served from
// this repo), decodes the PNG again with jsQR (a dev dependency, never shipped) and compares the decoded bytes with
// the payload the builder promised. A second, independent parser then reads the text back into the typed fields.
// jsQR is a stand-in for a phone camera: a real phone is NOT tested here.
//
// Locally: CHROME=/path/to/chrome node --test tests/js   (CI: npx playwright install chromium)
import test from 'node:test';
import fs from 'node:fs';
import http from 'node:http';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from '@playwright/test';
import jsQR from 'jsqr';
import { PNG } from 'pngjs';
import * as Q from '../../resources/js/qr/payloads.js';

const ROOT = path.join(path.dirname(fileURLToPath(import.meta.url)), '..', '..');
const TYPES = { '.html': 'text/html; charset=utf-8', '.js': 'text/javascript; charset=utf-8' };

function serve() {
  const server = http.createServer((req, res) => {
    const file = path.join(ROOT, decodeURIComponent(new URL(req.url, 'http://x').pathname));
    if (!file.startsWith(ROOT) || !fs.existsSync(file) || fs.statSync(file).isDirectory()) { res.writeHead(404).end(); return; }
    res.writeHead(200, { 'Content-Type': TYPES[path.extname(file)] || 'application/octet-stream' }).end(fs.readFileSync(file));
  });
  return new Promise((resolve) => server.listen(0, '127.0.0.1', () => resolve(server)));
}

/* ---------- independent parsers (do not reuse the builder's code) ---------- */

// Wi-Fi: WIFI:T:..;S:..;P:..;H:..;;  with \ escapes, as ZXing and the phone parsers read it.
function parseWifi(text) {
  assertEq(text.slice(0, 5), 'WIFI:', 'prefix');
  const out = {};
  let i = 5, key = '', val = '', inKey = true;
  for (; i < text.length; i++) {
    const c = text[i];
    if (c === '\\') { val += text[++i]; continue; }
    if (inKey) { if (c === ':') inKey = false; else key += c; continue; }
    if (c === ';') { out[key] = val; key = ''; val = ''; inKey = true; if (text[i + 1] === ';') break; continue; }
    val += c;
  }
  return out;
}

// vCard 3.0: unfold, split on CRLF, split name/params from value at the first unescaped colon, unescape text.
function parseVcard(text) {
  const lines = text.replace(/\r\n[ \t]/g, '').split('\r\n');
  const unescapeText = (s) => s.replace(/\\([\\;,nN])/g, (_, c) => (c === 'n' || c === 'N' ? '\n' : c));
  const splitSemi = (s) => { const parts = []; let cur = ''; for (let i = 0; i < s.length; i++) { if (s[i] === '\\') { cur += s[i] + s[++i]; } else if (s[i] === ';') { parts.push(cur); cur = ''; } else cur += s[i]; } parts.push(cur); return parts; };
  const props = [];
  for (const l of lines) {
    const k = l.indexOf(':');
    props.push({ name: l.slice(0, k).split(';')[0], params: l.slice(0, k), raw: l.slice(k + 1) });
  }
  return { props, unescapeText, splitSemi };
}

function assertEq(a, b, label) {
  if (a !== b) throw new Error(`${label}: expected ${JSON.stringify(b)} got ${JSON.stringify(a)}`);
}

/* ---------- cases ---------- */

const hardWifi = { ssid: 'Kopi;Hop,"Lt:2"\\', password: 'p;a,s:s"w\\o é日本語😀', security: 'WPA', hidden: true };
const hardVcard = {
  firstName: 'Ann;Marie', lastName: 'Doe, Jr.', org: 'Kopi, Hop; Lt.2', title: 'Café ☕ 日本語',
  mobile: '+62 812-0000-0000', work: '(021) 555 0100', email: 'ann.doe@example.com', url: 'example.com/menu?a=1&b=2',
  street: 'Jl. Merdeka No. 1, Blok A; Lt. 2', city: 'Jakarta', region: 'DKI', postal: '10110', country: 'Indonesia',
  note: 'Baris 1\nBaris 2 日本語 Кириллица\r\nBaris 3: dengan titik dua, koma; titik koma \\ garis miring'
};

const cases = [
  { id: 'wifi-simple', kind: 'wifi', input: { ssid: 'Cafe Hop', password: 'hop-wifi-2026', security: 'WPA' } },
  { id: 'wifi-hard', kind: 'wifi', input: hardWifi },
  { id: 'wifi-open', kind: 'wifi', input: { ssid: 'Open Net', security: 'nopass' } },
  { id: 'vcard-simple', kind: 'vcard', input: { firstName: 'Dewi', lastName: 'Lestari', mobile: '+62 812 0000 0000', email: 'dewi@example.com' } },
  { id: 'vcard-hard', kind: 'vcard', input: hardVcard },
  { id: 'text-url', kind: 'text', input: { text: 'https://link.hel.ink/qr?src=test&x=1#frag' } },
  { id: 'text-nonlatin', kind: 'text', input: { text: 'Halo dunia / Привет, мир / 你好，世界 / こんにちは 😀' } },
  // Styles
  { id: 'style-rounded', kind: 'wifi', input: hardWifi, opts: { dots: 'rounded', corners: 'extra-rounded', cornerDots: 'dot' } },
  { id: 'style-dots', kind: 'wifi', input: hardWifi, opts: { dots: 'dots', corners: 'dot', cornerDots: 'dot' } },
  { id: 'style-colour', kind: 'vcard', input: hardVcard, opts: { fg: '#1e1b4b', bg: '#fef3c7' } },
  { id: 'style-logo-25', kind: 'wifi', input: hardWifi, opts: { ecc: 'H', logo: true, logoSize: 0.25 } },
  { id: 'size-256', kind: 'wifi', input: hardWifi, opts: { size: 256 } },
  { id: 'size-1024', kind: 'vcard', input: hardVcard, opts: { size: 1024 } },
  // Expected to be hard or impossible: these back the warnings in the page.
  { id: 'warn-low-contrast', kind: 'wifi', input: hardWifi, opts: { fg: '#888888', bg: '#999999' }, expect: 'fail' },
  { id: 'warn-inverted-strict', kind: 'wifi', input: hardWifi, opts: { fg: '#ffffff', bg: '#000000' }, strictScanner: true, expect: 'fail' },
  { id: 'info-inverted-jsqr-default', kind: 'wifi', input: hardWifi, opts: { fg: '#ffffff', bg: '#000000' }, expect: 'info' },
  { id: 'info-logo-45-ecc-H', kind: 'wifi', input: hardWifi, opts: { ecc: 'H', logo: true, logoSize: 0.45 }, expect: 'info' },
  { id: 'info-logo-25-ecc-L', kind: 'wifi', input: hardWifi, opts: { ecc: 'L', logo: true, logoSize: 0.25 }, expect: 'info' },
];

const LOGO = 'data:image/svg+xml;utf8,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64"><circle cx="32" cy="32" r="30" fill="#4f46e5"/><text x="32" y="42" font-size="30" text-anchor="middle" fill="#fff" font-family="Arial">H</text></svg>');

function build(c) {
  if (c.kind === 'wifi') return Q.buildWifi(c.input);
  if (c.kind === 'vcard') return Q.buildVcard(c.input);
  return Q.buildText(c.input);
}

// Hand-written expectation for the two Wi-Fi cases the task named, so the builder is not only compared with itself.
const handWritten = {
  'wifi-hard': 'WIFI:T:WPA;S:Kopi\\;Hop\\,\\"Lt\\:2\\"\\\\;P:p\\;a\\,s\\:s\\"w\\\\o é日本語😀;H:true;;',
  'wifi-simple': 'WIFI:T:WPA;S:Cafe Hop;P:hop-wifi-2026;;',
  'wifi-open': 'WIFI:T:nopass;S:Open Net;;',
};

test('every payload is drawn by the vendored library and read back by an independent decoder', async () => {
  const server = await serve();
  const browser = await chromium.launch(process.env.CHROME ? { executablePath: process.env.CHROME, headless: true } : { headless: true });
  const page = await browser.newPage();
  await page.goto('http://127.0.0.1:' + server.address().port + '/tests/js/harness.html');
  await page.waitForFunction(() => window.ready === true);
  const log = [];
  const say = (s) => { log.push(s); console.log(s); };
  let failures = 0;

  say('qr-code-styling: vendored copy (public/vendor); decoder: jsQR ' + JSON.parse(fs.readFileSync(path.join(ROOT, 'node_modules/jsqr/package.json'))).version);

  for (const c of cases) {
    const built = build(c);
    const expectedText = built.payload;
    const row = { id: c.id, expect: c.expect || 'pass' };
    try {
      if (!expectedText) throw new Error('builder returned no payload: ' + built.errors.join(','));
      if (handWritten[c.id]) assertEq(expectedText, handWritten[c.id], 'builder vs hand-written payload');
      const opts = Object.assign({ binary: true }, c.opts || {});
      if (opts.logo) opts.logoDataUrl = LOGO;
      const r = await page.evaluate(([t, o]) => window.renderPng(t, o), [expectedText, opts]);
      const buf = Buffer.from(r.b64, 'base64');
            const png = PNG.sync.read(buf);
      const q = jsQR(new Uint8ClampedArray(png.data), png.width, png.height, c.strictScanner ? { inversionAttempts: 'dontInvert' } : undefined);
      if (!q) throw new Error('not decoded');
      const decoded = new TextDecoder('utf-8', { fatal: true }).decode(Uint8Array.from(q.binaryData));
      assertEq(decoded, expectedText, 'decoded vs payload');
      // Independent parse, back to the typed values.
      if (c.kind === 'wifi') {
        const w = parseWifi(decoded);
        assertEq(w.T, c.input.security, 'T'); assertEq(w.S, c.input.ssid, 'S');
        if (c.input.security !== 'nopass') assertEq(w.P, c.input.password, 'P');
        assertEq(w.H || '', c.input.hidden ? 'true' : '', 'H');
      } else if (c.kind === 'vcard') {
        const v = parseVcard(decoded);
        const get = (n) => v.props.filter((p) => p.name === n);
        assertEq(v.props[0].name + ':' + v.props[0].raw, 'BEGIN:VCARD', 'begin');
        assertEq(get('VERSION')[0].raw, '3.0', 'version');
        const n = v.splitSemi(get('N')[0].raw).map(v.unescapeText);
        assertEq(n[0], c.input.lastName, 'N family'); assertEq(n[1], c.input.firstName, 'N given');
        assertEq(v.unescapeText(get('FN')[0].raw), [c.input.firstName, c.input.lastName].join(' '), 'FN');
        if (c.input.org) assertEq(v.unescapeText(get('ORG')[0].raw), c.input.org, 'ORG');
        if (c.input.title) assertEq(v.unescapeText(get('TITLE')[0].raw), c.input.title, 'TITLE');
        if (c.input.note) assertEq(v.unescapeText(get('NOTE')[0].raw), c.input.note.replace(/\r\n|\r/g, '\n'), 'NOTE');
        if (c.input.street) {
          const a = v.splitSemi(get('ADR')[0].raw).map(v.unescapeText);
          assertEq(a.slice(2).join('|'), [c.input.street, c.input.city, c.input.region, c.input.postal, c.input.country].join('|'), 'ADR');
        }
        if (c.input.email) assertEq(get('EMAIL')[0].raw, c.input.email, 'EMAIL');
        // every physical line is a known property: no injected lines
        const known = new Set(['BEGIN', 'VERSION', 'N', 'FN', 'ORG', 'TITLE', 'TEL', 'EMAIL', 'URL', 'ADR', 'NOTE', 'END']);
        for (const p of v.props) if (!known.has(p.name)) throw new Error('unknown property line: ' + p.name);
      } else {
        assertEq(decoded, c.input.text, 'text');
      }
      row.result = 'decoded';
      row.modules = r.modules; row.bytes = q.binaryData.length;
    } catch (e) {
      row.result = 'failed: ' + e.message;
    }
    const ok = row.expect === 'pass' ? row.result === 'decoded' : row.expect === 'fail' ? row.result !== 'decoded' : true;
    if (!ok) failures++;
    say(`${ok ? 'OK  ' : 'FAIL'} ${c.id.padEnd(28)} expected:${row.expect.padEnd(5)} -> ${row.result}${row.modules ? `  (${row.modules} modules, ${row.bytes} bytes)` : ''}`);
  }

  // Control: the same hard Wi-Fi text handed to the library WITHOUT the byte conversion (binary: false).
  {
    const t = Q.buildWifi(hardWifi).payload;
    const r = await page.evaluate(([t]) => window.renderPng(t, { binary: false }), [t]);
    const png = PNG.sync.read(Buffer.from(r.b64, 'base64'));
    const q = jsQR(new Uint8ClampedArray(png.data), png.width, png.height);
    let decoded = null;
    try { decoded = q && new TextDecoder('utf-8', { fatal: true }).decode(Uint8Array.from(q.binaryData)); } catch (e) { decoded = '(not valid UTF-8)'; }
    say('');
    say('Control, library fed the raw string (no UTF-8 byte conversion): ' + (decoded === t ? 'decodes correctly' : 'DOES NOT round-trip -> ' + JSON.stringify(decoded)));
    say('  This is why the page passes QrPayloads.binaryString(payload) to qr-code-styling.');
  }

  say('');
  say(failures ? `${failures} expectation(s) not met` : 'All expectations met.');
  await browser.close();
  server.close();
  if (failures) throw new Error(failures + ' expectation(s) not met: ' + log.filter((l) => l.startsWith('FAIL')).join(' | '));
});
