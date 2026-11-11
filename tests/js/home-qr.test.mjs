// The home QR scene: every code says something the /qr page would also build, with no errors.
import test from 'node:test';
import assert from 'node:assert/strict';
import { deckPayloads, DECK_STYLES } from '../../resources/js/home-qr.js';

const data = {
    link: 'https://hel.ink/l/launch', dynamic: 'https://hel.ink/l/menu',
    wifi: { ssid: 'HopCafe', password: 'welcome-2026' },
    vcard: { firstName: 'Ada', lastName: 'Lovelace', org: 'HOP Studio', mobile: '+14155550123', email: 'ada@example.com' },
};

test('the four codes of the home scene are valid payloads', () => {
    const items = deckPayloads(data);
    assert.equal(items.length, 4);
    assert.equal(DECK_STYLES.length, 4);
    for (const it of items) { assert.deepEqual(it.errors, []); assert.ok(it.payload.length > 10); }
    assert.match(items[1].payload, /^WIFI:T:WPA;S:HopCafe;P:welcome-2026;;/);
    assert.match(items[2].payload, /^BEGIN:VCARD/);
    assert.match(items[2].payload, /N:Lovelace;Ada/);
});
