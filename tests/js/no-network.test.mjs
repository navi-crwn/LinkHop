// Static guard for the privacy claim of /qr: nothing typed there leaves the browser or is stored.
// The page scripts and the view must not contain a network, storage or form-post call.
import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.join(path.dirname(fileURLToPath(import.meta.url)), '..', '..');
const files = fs.readdirSync(path.join(ROOT, 'resources/js/qr')).map((f) => 'resources/js/qr/' + f).concat('resources/views/qr.blade.php');
const FORBIDDEN = [/\bfetch\s*\(/, /XMLHttpRequest/, /sendBeacon/, /\blocalStorage\b/, /\bsessionStorage\b/, /\bindexedDB\b/, /\bWebSocket\b/, /<form\b[^>]*\b(action|method)\s*=/i];

test('the /qr scripts and view hold no network call, storage or form post', () => {
  assert.ok(files.length >= 5);
  for (const f of files) {
    const src = fs.readFileSync(path.join(ROOT, f), 'utf8');
    for (const re of FORBIDDEN) assert.doesNotMatch(src, re, `${f} matches ${re}`);
  }
});

test('the page builds its code options with binaryString (UTF-8 bytes for the library)', () => {
  const opt = fs.readFileSync(path.join(ROOT, 'resources/js/qr/options.js'), 'utf8');
  assert.match(opt, /data:\s*binaryString\(payload\)/);
  const studio = fs.readFileSync(path.join(ROOT, 'resources/js/qr/studio.js'), 'utf8');
  assert.match(studio, /qrStyleOptions\(/);
  assert.doesNotMatch(studio, /data:\s*P\.(?!binaryString)/);
});
