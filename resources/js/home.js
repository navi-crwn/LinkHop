// The LinkHop home: scenes with motion, in the manner of the Amnesia site (GSAP ScrollTrigger + Lenis).
// GSAP and Lenis ship inside our own bundle (no CDN). Every scene is complete as plain HTML and CSS;
// this file only adds motion, and does nothing but wire the demo slug when "reduce motion" is on.
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import Lenis from 'lenis';

const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;
const $ = (sel, root = document) => root.querySelector(sel);
const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));
const once = (trigger, start = 'top 80%') => ({ trigger, start, once: true });
const lang = () => document.documentElement.lang || 'en';

/** the demo slug: typing in it changes the short link right below (works with or without motion) */
function slugDemo() {
    const input = $('[data-lk-slug-input]');
    if (!input) return;
    const out = $$('[data-lk-slug]');
    const apply = () => {
        const clean = input.value.replace(/[^A-Za-z0-9-]/g, '').slice(0, 24);
        out.forEach((el) => { el.textContent = clean || 'launch'; });
    };
    input.addEventListener('input', apply);
    apply();
}

function start() {
    gsap.registerPlugin(ScrollTrigger);
    document.documentElement.classList.add('lk-motion');

    // smooth scroll, kept in step with ScrollTrigger
    const lenis = new Lenis({ duration: 1.05, smoothWheel: true });
    lenis.on('scroll', ScrollTrigger.update);
    gsap.ticker.add((t) => lenis.raf(t * 1000));
    gsap.ticker.lagSmoothing(0);
    $$('a[href^="#"]').forEach((a) => a.addEventListener('click', (e) => {
        const id = a.getAttribute('href');
        const target = id.length > 1 && $(id);
        if (!target) return;
        e.preventDefault();
        lenis.scrollTo(target, { offset: -72 });
    }));

    shorten(); share(); qr(); stats(); lists(); terminals(); family();
    addEventListener('load', () => ScrollTrigger.refresh());
    document.fonts?.ready.then(() => ScrollTrigger.refresh());
}

/** 01: the long address loses its characters as you scroll, the short link and what it can do arrive */
function shorten() {
    const sec = $('[data-lk-shorten]');
    if (!sec) return;
    const long = $('[data-lk-long]', sec), result = $('[data-lk-result]', sec), chips = $$('.lk-chips li', sec), pin = $('[data-lk-pin]', sec);
    const text = long.textContent;
    long.textContent = '';
    long.style.webkitLineClamp = 'unset';
    long.style.display = 'block';
    long.setAttribute('aria-label', text);
    const chars = Array.from(text).map((ch) => { const s = document.createElement('span'); s.className = 'c'; s.textContent = ch; s.setAttribute('aria-hidden', 'true'); long.appendChild(s); return s; });
    const vanish = chars.slice('https://example.com/'.length).reverse();
    gsap.set(result, { autoAlpha: 0, y: 18, scale: .97 });
    gsap.set(chips, { autoAlpha: 0, y: 10 });
    const mm = gsap.matchMedia();
    const build = (tl) => {
        tl.to(vanish, { opacity: 0, width: 0, overflow: 'hidden', duration: .02, stagger: { each: .0035 } }, 0)
            .to(result, { autoAlpha: 1, y: 0, scale: 1, duration: .45, ease: 'back.out(1.4)' }, '>-.1')
            .to(chips, { autoAlpha: 1, y: 0, duration: .3, stagger: .12 }, '>-.1');
        return tl;
    };
    mm.add('(min-width: 981px)', () => {
        build(gsap.timeline({ scrollTrigger: { trigger: pin, start: 'center 54%', end: '+=115%', pin: true, scrub: .6 } }));
    });
    mm.add('(max-width: 980px)', () => {
        build(gsap.timeline({ scrollTrigger: once(pin, 'top 72%') }));
    });
}

/** 03: the preview card and its fields rise in */
function share() {
    const sec = $('#share');
    if (!sec) return;
    gsap.fromTo($$('.lk-in, .lk-tones, .lk-card', sec), { y: 22, opacity: 0 }, { y: 0, opacity: 1, duration: .7, stagger: .1, ease: 'power3.out', scrollTrigger: once(sec, 'top 70%') });
}

/** 04: the QR code tries three colour pairs by itself, until a visitor picks one */
function qr() {
    const win = $('#qr .h-stage-visual');
    if (!win) return;
    const buttons = $$('.lk-qr-tones button', win);
    if (buttons.length < 4) return;
    let touched = false;
    buttons.forEach((b) => b.addEventListener('click', () => { if (b.dataset.auto !== '1') touched = true; }));
    ScrollTrigger.create({
        ...once(win, 'top 65%'),
        onEnter: () => {
            [1, 2, 3, 0].forEach((i, n) => gsap.delayedCall(1.1 + n * 1.3, () => {
                if (touched) return;
                buttons[i].dataset.auto = '1';
                buttons[i].click();
                delete buttons[i].dataset.auto;
            }));
        },
    });
}

/** 06: the chart draws itself, the bars fill, the busy hours light up, the numbers count */
function stats() {
    const sec = $('[data-lk-stats]');
    if (!sec) return;
    const line = $('.lk-line', sec), area = $('.lk-area', sec), bars = $$('.lk-bars i', sec), heat = $$('.lk-heat i', sec), counts = $$('[data-lk-count]', sec);
    const len = line.getTotalLength();
    gsap.set(line, { strokeDasharray: len, strokeDashoffset: len });
    gsap.set(area, { opacity: 0 });
    gsap.set(bars, { scaleX: 0 });
    gsap.set(heat, { opacity: 0 });
    counts.forEach((el) => { el.dataset.final = el.textContent; el.textContent = '0' + (el.dataset.suffix || ''); });
    const tl = gsap.timeline({ scrollTrigger: once($('.lk-stats', sec), 'top 72%') });
    tl.to(line, { strokeDashoffset: 0, duration: 1.6, ease: 'power2.inOut' }, 0)
        .to(area, { opacity: 1, duration: 1 }, .8)
        .to(bars, { scaleX: 1, duration: .9, stagger: .12, ease: 'power3.out' }, .6)
        .to(heat, { opacity: 1, duration: .5, stagger: { each: .004, from: 'random' } }, .7);
    counts.forEach((el) => {
        const to = Number(el.dataset.lkCount), suffix = el.dataset.suffix || '', o = { v: 0 };
        tl.to(o, { v: to, duration: 1.5, ease: 'power2.out', onUpdate: () => { el.textContent = Math.round(o.v).toLocaleString(lang()) + suffix; } }, 0);
    });
}

/** organise rows and FAQ cards come in one after another (the other sections are revealed by hop-home.js) */
function lists() {
    [['#organise .link-demo-ranks > div', '#organise'], ['.h-faq-grid details', '#faq']].forEach(([sel, trig]) => {
        const items = $$(sel);
        if (!items.length) return;
        gsap.fromTo(items, { y: 26, opacity: 0 }, { y: 0, opacity: 1, duration: .65, stagger: .09, ease: 'power3.out', scrollTrigger: once($(trig), 'top 78%') });
    });
}

/** 09 and 10: the commands type themselves; the copy button always gets the whole line */
function terminals() {
    ['#api-curl', '#install-sh'].forEach((sel) => {
        const el = $(sel);
        if (!el) return;
        const full = el.textContent;
        el.setAttribute('data-full', full);
        el.setAttribute('aria-label', full);
        const o = { n: 0 };
        el.textContent = '';
        ScrollTrigger.create({
            ...once(el, 'top 85%'),
            onEnter: () => {
                el.classList.add('lk-typing');
                gsap.to(o, { n: full.length, duration: Math.min(2.4, full.length * .02), ease: 'none', onUpdate: () => { el.textContent = full.slice(0, Math.round(o.n)); }, onComplete: () => { el.textContent = full; el.classList.remove('lk-typing'); } });
            },
        });
    });
    $$('[data-copy-from]').forEach((btn) => btn.addEventListener('click', () => {
        const src = $(btn.getAttribute('data-copy-from'));
        if (src && src.getAttribute('data-full')) src.textContent = src.getAttribute('data-full');
    }, true));
}

/** 13: the four app marks rise in */
function family() {
    const row = $('[data-lk-family]');
    if (!row) return;
    gsap.fromTo($$('.lk-fam', row), { y: 24, opacity: 0, scale: .96 }, { y: 0, opacity: 1, scale: 1, duration: .6, stagger: .08, ease: 'back.out(1.4)', scrollTrigger: once(row, 'top 82%') });
}

slugDemo();
if (!reduced) {
    if (document.readyState === 'loading') addEventListener('DOMContentLoaded', start);
    else start();
}
