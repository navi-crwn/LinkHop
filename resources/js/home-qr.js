// The home page QR scene ("From the screen to the real world"): four real codes in turn, a link, a Wi-Fi table card, a
// contact card and a code whose destination changes. The texts inside the codes come from the same pure builders the
// /qr page uses (./qr/payloads.js), and the codes are drawn by the vendored qr-code-styling that /qr loads (no new
// library, no network). Motion: cards change while the scene is on screen (the engine's .is-live), pause on hover or
// focus and stop for good when a visitor picks a card. Reduced motion: one finished card (the last), no timers.
import * as P from './qr/payloads.js';
import { qrStyleOptions } from './qr/options.js';

/** what each code says; pure, so a test can check every payload is valid */
export function deckPayloads(d) {
    const wifi = P.buildWifi({ ssid: d.wifi.ssid, password: d.wifi.password, security: 'WPA', hidden: false });
    const vcard = P.buildVcard(d.vcard);
    const plain = (payload) => ({ payload, errors: [], warnings: [] });
    return [plain(d.link), wifi, vcard, plain(d.dynamic)];
}

export const DECK_STYLES = [
    { fg: '#0b1020', bg: '#ffffff', dots: 'square', corners: 'square', logo: '', logoSize: 22 },
    { fg: '#0f766e', bg: '#ffffff', dots: 'rounded', corners: 'extra-rounded', logo: '', logoSize: 22 },
    { fg: '#4338ca', bg: '#ffffff', dots: 'dots', corners: 'extra-rounded', logo: 'LOGO', logoSize: 24 },
    { fg: '#0b1020', bg: '#ffffff', dots: 'classy-rounded', corners: 'extra-rounded', logo: '', logoSize: 22 },
];

const STAY = [4600, 4600, 4600, 6600];

export function initQrDeck({ reduced }) {
    const root = document.querySelector('[data-lx-qrdeck]');
    if (!root) return;
    const cards = Array.from(root.querySelectorAll('[data-qd-card]'));
    const tabs = Array.from(root.querySelectorAll('[data-qd-tab]'));
    if (cards.length !== 4 || tabs.length !== 4) return;
    let data;
    try { data = JSON.parse(root.dataset.states); } catch (e) { return; }

    let current = 0, timer = 0, destTimer = 0, touched = false, paused = false, built = false;
    const live = () => root.classList.contains('is-live');

    function schedule() {
        clearTimeout(timer);
        if (reduced || touched || paused || !built || !live() || document.hidden) return;
        timer = setTimeout(() => show((current + 1) % 4), STAY[current]);
    }

    function show(i) {
        current = i;
        cards.forEach((c, n) => {
            const on = n === i;
            c.classList.toggle('is-on', on);
            if (on) c.removeAttribute('inert'); else c.setAttribute('inert', '');
            c.setAttribute('aria-hidden', String(!on));
        });
        tabs.forEach((t, n) => t.setAttribute('aria-pressed', String(n === i)));
        // the last card: the destination changes behind the same printed code
        clearTimeout(destTimer);
        const dyn = cards[3];
        dyn.classList.remove('is-new');
        if (i === 3) {
            if (reduced) dyn.classList.add('is-new');
            else destTimer = setTimeout(() => dyn.classList.add('is-new'), 1700);
        }
        schedule();
    }

    tabs.forEach((t, n) => {
        t.addEventListener('click', () => { touched = true; show(n); });
        t.addEventListener('keydown', (e) => {
            if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') return;
            const m = (n + (e.key === 'ArrowRight' ? 1 : 3)) % 4;
            touched = true; show(m); tabs[m].focus(); e.preventDefault();
        });
    });
    root.addEventListener('pointerenter', () => { paused = true; clearTimeout(timer); });
    root.addEventListener('pointerleave', () => { paused = false; schedule(); });
    root.addEventListener('focusin', () => { paused = true; clearTimeout(timer); });
    root.addEventListener('focusout', () => { paused = false; schedule(); });
    document.addEventListener('visibilitychange', schedule);
    new MutationObserver(schedule).observe(root, { attributes: true, attributeFilter: ['class'] });

    function loadLib() {
        if (window.QRCodeStyling) return Promise.resolve();
        return new Promise((resolve, reject) => {
            const s = document.createElement('script');
            s.src = root.dataset.lib;
            if (root.dataset.nonce) s.setAttribute('nonce', root.dataset.nonce);
            s.onload = resolve; s.onerror = reject;
            document.head.appendChild(s);
        });
    }

    function build() {
        const items = deckPayloads(data);
        cards.forEach((card, n) => {
            if (items[n].errors.length) return;
            const style = Object.assign({}, DECK_STYLES[n]);
            style.logo = style.logo ? data.logo : '';
            const o = qrStyleOptions(style, 'canvas', 416, items[n].payload, 0);
            const slot = card.querySelector('[data-qd-code]');
            // canvas, not svg: four svg codes on one page share element ids (their clip paths), and the last one wins
            const qr = new window.QRCodeStyling(o);
            const fresh = document.createElement('div');
            fresh.className = 'lx-qd-real';
            slot.appendChild(fresh);
            qr.append(fresh);
            slot.classList.add('is-real');
        });
        built = true;
        // reduced motion: the finished state, still; the tabs let a visitor look at the others
        show(reduced ? 3 : 0);
    }

    // draw once the scene is near the screen: the library is only fetched when it is about to be seen
    const start = () => loadLib().then(build).catch(() => { /* the server-drawn link code stays */ });
    if ('IntersectionObserver' in window) {
        const io = new IntersectionObserver((es) => { if (es.some((e) => e.isIntersecting)) { io.disconnect(); start(); } }, { rootMargin: '240px' });
        io.observe(root);
    } else start();
}
