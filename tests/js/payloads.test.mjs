// Unit tests for resources/js/qr/payloads.js. Run: node --test tests/js
// Expected strings are written by hand from the specs, not produced by the code under test.
import test from 'node:test';
import assert from 'node:assert/strict';
import * as Q from '../../resources/js/qr/payloads.js';

test('wifiEscape puts a backslash before \\ ; , : and "', () => {
  assert.equal(Q.wifiEscape('a;b,c:d"e\\f'), 'a\\;b\\,c\\:d\\"e\\\\f');
  assert.equal(Q.wifiEscape('plain text 123'), 'plain text 123');
});

test('wifiEscape keeps non-Latin text and drops control characters and line breaks', () => {
  assert.equal(Q.wifiEscape('Кафе 日本語 😀'), 'Кафе 日本語 😀');
  assert.equal(Q.wifiEscape('a\u0000b\u0007c\nd\r\ne'), 'abc d e');
});

test('Wi-Fi payload, WPA', () => {
  const r = Q.buildWifi({ ssid: 'Cafe Hop', password: 'hop-wifi-2026', security: 'WPA' });
  assert.equal(r.payload, 'WIFI:T:WPA;S:Cafe Hop;P:hop-wifi-2026;;');
  assert.deepEqual(r.errors, []);
  assert.deepEqual(r.warnings, []);
});

test('Wi-Fi payload, hard values', () => {
  const r = Q.buildWifi({ ssid: 'A;B,C:"D"\\', password: 'p;a,s:s"w\\o', security: 'WPA' });
  assert.equal(r.payload, 'WIFI:T:WPA;S:A\\;B\\,C\\:\\"D\\"\\\\;P:p\\;a\\,s\\:s\\"w\\\\o;;');
});

test('Wi-Fi payload, hidden, open and WEP', () => {
  assert.equal(Q.buildWifi({ ssid: 'x', password: '12345678', security: 'WPA', hidden: true }).payload, 'WIFI:T:WPA;S:x;P:12345678;H:true;;');
  assert.equal(Q.buildWifi({ ssid: 'Open', password: 'ignored', security: 'nopass' }).payload, 'WIFI:T:nopass;S:Open;;');
  assert.equal(Q.buildWifi({ ssid: 'old', password: '12345', security: 'WEP' }).payload, 'WIFI:T:WEP;S:old;P:12345;;');
  assert.equal(Q.buildWifi({ ssid: 'w3', password: 'longenough', security: 'SAE' }).payload, 'WIFI:T:SAE;S:w3;P:longenough;;');
});

test('Wi-Fi errors and warnings', () => {
  assert.deepEqual(Q.buildWifi({ ssid: '', password: 'x' }).errors, ['ssid_required']);
  assert.deepEqual(Q.buildWifi({ ssid: 'x', password: '' }).errors, ['pass_required']);
  assert.equal(Q.buildWifi({ ssid: 'x', password: '' }).payload, '');
  assert.ok(Q.buildWifi({ ssid: 'x', password: 'short' }).warnings.includes('pass_short'));
  assert.ok(Q.buildWifi({ ssid: 'x', password: 'g'.repeat(64) }).warnings.includes('pass_long'));
  assert.ok(!Q.buildWifi({ ssid: 'x', password: 'g'.repeat(63) }).warnings.includes('pass_long'));
  assert.ok(!Q.buildWifi({ ssid: 'x', password: 'ab12'.repeat(16) }).warnings.includes('pass_long')); // 64 hex digits = raw key
  assert.ok(Q.buildWifi({ ssid: 'é'.repeat(17), password: 'longenough' }).warnings.includes('ssid_too_long')); // 34 bytes
  assert.ok(!Q.buildWifi({ ssid: 'é'.repeat(16), password: 'longenough' }).warnings.includes('ssid_too_long')); // 32 bytes
  assert.ok(Q.buildWifi({ ssid: ' x', password: 'longenough' }).warnings.includes('edge_space'));
  assert.ok(Q.buildWifi({ ssid: 'x\u0001', password: 'longenough' }).warnings.includes('ctl_removed'));
  assert.ok(Q.buildWifi({ ssid: 'x', password: 'x'.repeat(2100), security: 'nopass' }).errors.length === 0); // open network ignores the password
});

test('vcardEscape: backslash, semicolon, comma, line break', () => {
  assert.equal(Q.vcardEscape('Doe, John; Jr\\'), 'Doe\\, John\\; Jr\\\\');
  assert.equal(Q.vcardEscape('a\nb\r\nc\rd'), 'a\\nb\\nc\\nd');
  assert.equal(Q.vcardEscape('back\\slash\nnext'), 'back\\\\slash\\nnext'); // the \ of a line break is not doubled
  assert.equal(Q.vcardEscape('colon: stays'), 'colon: stays');
  assert.equal(Q.vcardEscape('a\u0000b'), 'ab');
});

test('vCard 3.0, exact output', () => {
  const r = Q.buildVcard({ firstName: 'Dewi', lastName: 'Lestari', org: 'Kopi Hop', title: 'Owner', mobile: '+62 812 0000 0000', email: 'dewi@example.com', url: 'example.com/menu' });
  assert.equal(r.payload, [
    'BEGIN:VCARD', 'VERSION:3.0', 'N:Lestari;Dewi;;;', 'FN:Dewi Lestari', 'ORG:Kopi Hop', 'TITLE:Owner',
    'TEL;TYPE=CELL:+62 812 0000 0000', 'EMAIL;TYPE=INTERNET:dewi@example.com', 'URL:https://example.com/menu', 'END:VCARD'
  ].join('\r\n'));
  assert.deepEqual(r.warnings, []);
});

test('vCard escapes names with comma, semicolon and a line break', () => {
  const r = Q.buildVcard({ firstName: 'Ann;Marie', lastName: 'Doe, Jr.', org: 'A;B,C', note: 'line1\nline2' });
  const lines = r.payload.split('\r\n');
  assert.ok(lines.includes('N:Doe\\, Jr.;Ann\\;Marie;;;'));
  assert.ok(lines.includes('FN:Ann\\;Marie Doe\\, Jr.'));
  assert.ok(lines.includes('ORG:A\\;B\\,C'));
  assert.ok(lines.includes('NOTE:line1\\nline2'));
});

test('vCard cannot be injected through a value', () => {
  const evil = 'x\r\nEND:VCARD\r\nBEGIN:VCARD\r\nFN:evil\nPHOTO;ENCODING=b:AAAA';
  const r = Q.buildVcard({ firstName: evil, lastName: evil, org: evil, title: evil, mobile: evil, work: evil, email: evil, street: evil, note: evil });
  const lines = r.payload.split('\r\n');
  assert.equal(lines.filter((l) => l === 'BEGIN:VCARD').length, 1);
  assert.equal(lines.filter((l) => l === 'END:VCARD').length, 1);
  const allowed = /^(BEGIN:VCARD|VERSION:3\.0|N:|FN:|ORG:|TITLE:|TEL;TYPE=CELL:|TEL;TYPE=WORK,VOICE:|EMAIL;TYPE=INTERNET:|ADR;TYPE=WORK:|NOTE:|END:VCARD)/;
  for (const l of lines) assert.match(l, allowed, 'unexpected line: ' + l);
  assert.ok(!lines.some((l) => l.startsWith('PHOTO')));
});

test('vCard URL: scheme added, bad ones dropped', () => {
  assert.match(Q.buildVcard({ firstName: 'a', url: 'example.com' }).payload, /\r\nURL:https:\/\/example\.com\r\n/);
  const js = Q.buildVcard({ firstName: 'a', url: 'javascript:alert(1)' });
  assert.ok(!js.payload.includes('URL:'));
  assert.ok(js.warnings.includes('url_scheme'));
  const sp = Q.buildVcard({ firstName: 'a', url: 'https://x.com/a b' });
  assert.ok(!sp.payload.includes('URL:'));
  assert.ok(sp.warnings.includes('url_invalid'));
});

test('vCard needs a name or an organisation', () => {
  assert.deepEqual(Q.buildVcard({}).errors, ['name_required']);
  assert.equal(Q.buildVcard({ org: 'Only Org' }).payload.includes('FN:Only Org'), true);
});

test('vCard folding: 75 octets, UTF-8 never split, unfolds to the original', () => {
  const note = '日本語のメモ '.repeat(30) + 'и кириллица 😀'.repeat(10);
  const flat = Q.buildVcard({ firstName: 'a', note }, { fold: false }).payload.split('\r\n');
  const folded = Q.buildVcard({ firstName: 'a', note }, { fold: true }).payload.split('\r\n');
  const dec = new TextDecoder('utf-8', { fatal: true });
  for (const l of folded) {
    assert.ok(Buffer.byteLength(l, 'utf8') <= 75, 'line too long: ' + Buffer.byteLength(l, 'utf8'));
    dec.decode(Buffer.from(l, 'utf8')); // throws on a broken sequence
  }
  assert.ok(folded.length > flat.length);
  assert.deepEqual(folded.join('\r\n').replace(/\r\n /g, '').split('\r\n'), flat);
});

test('vCard file text ends with a line break only when asked', () => {
  assert.ok(Q.buildVcard({ firstName: 'a' }, { final: true }).payload.endsWith('END:VCARD\r\n'));
  assert.ok(Q.buildVcard({ firstName: 'a' }).payload.endsWith('END:VCARD'));
});

test('vcardFileName', () => {
  assert.equal(Q.vcardFileName('Dewi Lestari'), 'Dewi_Lestari.vcf');
  assert.equal(Q.vcardFileName('../../etc/passwd'), 'etcpasswd.vcf');
  assert.equal(Q.vcardFileName('a"b<c>d|e:f*g?h'), 'abcdefgh.vcf');
  assert.equal(Q.vcardFileName(''), 'contact.vcf');
  assert.equal(Q.vcardFileName('...'), 'contact.vcf');
  assert.equal(Q.vcardFileName('日本語 名前'), '日本語_名前.vcf');
  assert.equal(Array.from(Q.vcardFileName('x'.repeat(200))).length, 84);
});

test('text payload', () => {
  assert.deepEqual(Q.buildText({ text: '   ' }).errors, ['text_required']);
  assert.equal(Q.buildText({ text: 'https://hel.ink/l/abc' }).payload, 'https://hel.ink/l/abc');
  assert.deepEqual(Q.buildText({ text: 'https://hel.ink/l/abc' }).warnings, []);
  assert.ok(Q.buildText({ text: 'hel.ink/l/abc' }).warnings.includes('url_no_scheme'));
  assert.ok(Q.buildText({ text: 'ftp://host/file' }).warnings.includes('url_scheme_other'));
  assert.deepEqual(Q.buildText({ text: 'Hello world' }).warnings, []);
  assert.ok(Q.buildText({ text: 'x'.repeat(1001) }).warnings.includes('payload_long'));
  assert.ok(Q.buildText({ text: 'x'.repeat(2001) }).errors.includes('payload_too_long'));
});

test('contrast and scan advice', () => {
  assert.ok(Math.abs(Q.contrastRatio('#000000', '#ffffff') - 21) < 1e-9);
  assert.equal(Q.contrastRatio('#fff', '#fff'), 1);
  assert.equal(Q.contrastRatio('nope', '#fff'), null);
  assert.equal(Q.scanAdvice('#000000', '#ffffff').level, 'ok');
  const inv = Q.scanAdvice('#ffffff', '#000000');
  assert.equal(inv.level, 'warn');
  assert.ok(inv.inverted && inv.codes.includes('inverted'));
  assert.equal(Q.scanAdvice('#888888', '#999999').level, 'bad');
  assert.equal(Q.scanAdvice('#6b7280', '#ffffff').level, 'ok'); // 4.83
  assert.equal(Q.scanAdvice('#9ca3af', '#ffffff').level, 'bad'); // 2.54
  assert.equal(Q.scanAdvice('#757575', '#ffffff').level, 'ok'); // 4.6
});

test('moduleMm', () => {
  assert.equal(Q.moduleMm(28.5, 49), 0.5); // 49 modules + 2 x 4 quiet = 57 squares across 28.5 mm
  assert.equal(Q.moduleMm(50, null), null);
});

test('utf8Length and binaryString', () => {
  assert.equal(Q.utf8Length('aé日😀'), 1 + 2 + 3 + 4);
  assert.equal(Q.binaryString('é'), 'Ã©');
  assert.equal(Buffer.from(Q.binaryString('日😀'), 'latin1').toString('utf8'), '日😀');
});
