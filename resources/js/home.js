// The LinkHop home: one scroll scene per feature, in the manner of the Amnesia site (GSAP ScrollTrigger + Lenis).
// GSAP and Lenis ship inside our own bundle (no CDN). Every scene is complete as plain HTML and CSS;
// this file only adds motion, and with "reduce motion" on it only wires the small demos (preview fields, QR colours).
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import Lenis from 'lenis';

const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;
const $ = (sel, root = document) => root.querySelector(sel);
const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));
const once = (trigger, start = 'top 78%') => ({ trigger, start, once: true });
const lang = () => document.documentElement.lang || 'en';

/** the preview demo: the fields write into both cards, a colour changes the picture (works with or without motion) */
function shareDemo() {
    const title = $('[data-lx-title]'), desc = $('[data-lx-desc]'), tones = $$('[data-lx-tones] button');
    if (!title || !desc) return;
    const apply = () => {
        $$('[data-lx-card-title]').forEach((el) => { el.textContent = title.value || ' '; });
        $$('[data-lx-card-desc]').forEach((el) => { el.textContent = desc.value || ' '; });
    };
    title.addEventListener('input', apply);
    desc.addEventListener('input', apply);
    tones.forEach((b) => b.addEventListener('click', () => {
        tones.forEach((o) => o.setAttribute('aria-pressed', String(o === b)));
        $$('[data-lx-card-img]').forEach((el) => { el.style.background = b.dataset.tone; });
    }));
}

/** the QR colours: the cubes and the real generator image follow the pair that is picked */
function qrDemo() {
    const vox = $('[data-lx-vox]'), buttons = $$('[data-lx-qr-tones] button');
    if (!vox || !buttons.length) return null;
    const pick = (i) => {
        buttons.forEach((b, n) => b.setAttribute('aria-pressed', String(n === i)));
        vox.style.setProperty('--fg', buttons[i].dataset.fg);
        vox.style.setProperty('--bg', buttons[i].dataset.bg);
        window.dispatchEvent(new CustomEvent('lx-qr-tone', { detail: i }));
    };
    let touched = false;
    buttons.forEach((b, i) => b.addEventListener('click', (e) => { if (!e.detail && b.dataset.auto === '1') return; touched = true; pick(i); }));
    return { pick, touched: () => touched };
}

function start() {
    gsap.registerPlugin(ScrollTrigger);
    document.documentElement.classList.add('lx-motion');

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

    heads(); marquee(); particles(); story(); share(); qr(); analytics(); control(); routing(); gates(); build(); faq(); family();
    addEventListener('load', () => ScrollTrigger.refresh());
    document.fonts?.ready.then(() => ScrollTrigger.refresh());
}

/** a heading block: label, title, lead rise one after another */
function heads() {
    $$('.lx-head, .lx-final .lx-wrap > *').forEach((head) => {
        const parts = head.classList.contains('lx-head') ? Array.from(head.children) : [head];
        gsap.from(parts, { y: 26, opacity: 0, duration: .8, stagger: .09, ease: 'power3.out', scrollTrigger: once(head, 'top 86%') });
    });
}

function marquee() {
    const track = $('[data-lx-marq] > div');
    if (track) {
        track.innerHTML += track.innerHTML;
        Array.from(track.children).slice(track.children.length / 2).forEach((c) => c.setAttribute('aria-hidden', 'true'));
        track.classList.add('run');
    }
    const nums = $('[data-lx-nums]');
    if (!nums) return;
    gsap.from($$('div', nums), { y: 24, opacity: 0, duration: .7, stagger: .08, ease: 'power3.out', scrollTrigger: once(nums, 'top 88%') });
    $$('[data-count]', nums).forEach((el) => {
        const end = Number(el.dataset.count), suffix = el.dataset.suffix || '', o = { v: 0 };
        el.textContent = '0' + suffix;
        gsap.to(o, { v: end, duration: 1.3, ease: 'power2.out', onUpdate: () => { el.textContent = Math.round(o.v).toLocaleString(lang()) + suffix; }, scrollTrigger: once(nums, 'top 85%') });
    });
}

/** hero: the accent words break into particles that drift away as you scroll past the hero */
function particles() {
    const hero = $('.h-hero'), word = $('.h-hero h1 em');
    if (!hero || !word) return;
    const canvas = document.createElement('canvas');
    canvas.id = 'lx-particles'; canvas.setAttribute('aria-hidden', 'true');
    hero.appendChild(canvas);
    const ctx = canvas.getContext('2d'), dpr = Math.min(devicePixelRatio || 1, 2), pad = 140, max = innerWidth > 900 ? 1400 : 500;
    let pts = [], box = null, last = -1;
    const mix = (a, b, t) => 'rgb(' + a.map((v, i) => Math.round(v + (b[i] - v) * t)).join(',') + ')';
    function build() {
        const r = word.getBoundingClientRect(), hr = hero.getBoundingClientRect();
        box = { x: r.left - hr.left - pad, y: r.top - hr.top - pad, w: r.width + pad * 2, h: r.height + pad * 2 };
        Object.assign(canvas.style, { left: box.x + 'px', top: box.y + 'px', width: box.w + 'px', height: box.h + 'px' });
        canvas.width = Math.round(box.w * dpr); canvas.height = Math.round(box.h * dpr);
        const cs = getComputedStyle(word), off = document.createElement('canvas');
        off.width = Math.ceil(r.width + 24); off.height = Math.ceil(r.height);
        const o = off.getContext('2d');
        o.font = `${cs.fontStyle} ${cs.fontWeight} ${cs.fontSize} ${cs.fontFamily}`; o.textBaseline = 'middle'; o.fillStyle = '#fff';
        o.fillText(word.textContent, 0, r.height / 2);
        const data = o.getImageData(0, 0, off.width, off.height).data, step = Math.max(2, Math.ceil(Math.sqrt(off.width * off.height / (max * 3.5))));
        pts = [];
        for (let y = 0; y < off.height; y += step) for (let x = 0; x < off.width; x += step) if (data[(y * off.width + x) * 4 + 3] > 128) {
            const t = x / off.width;
            pts.push({ x: x + pad, y: y + pad, dx: (Math.random() - .3) * 260, dy: -Math.random() * 220 - 40, s: Math.random() * 1.2 + .8,
                c: t < .5 ? mix([34, 211, 238], [99, 102, 241], t * 2) : mix([99, 102, 241], [236, 72, 153], (t - .5) * 2) });
        }
        last = -1;
    }
    function draw(p) {
        if (!box || p === last) return;
        last = p;
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0); ctx.clearRect(0, 0, box.w, box.h);
        word.style.opacity = p > .02 ? 0 : 1;
        if (p <= .02) return;
        const e = p * p * (3 - 2 * p);
        ctx.globalAlpha = Math.max(0, 1 - p * 1.15);
        for (const q of pts) { ctx.fillStyle = q.c; ctx.fillRect(q.x + q.dx * e, q.y + q.dy * e, q.s, q.s); }
    }
    build();
    const st = ScrollTrigger.create({ trigger: hero, start: 'top top', end: '70% top', scrub: true, onUpdate: (self) => draw(+self.progress.toFixed(3)), onRefresh: (self) => { build(); draw(self.progress); } });
    document.fonts?.ready.then(() => { build(); draw(st.progress); });
}

/** 01: pinned. The long address is pasted, the slug is typed, the address shrinks, the link is shared, the numbers come in. */
function story() {
    const sec = $('[data-lx-story]');
    if (!sec) return;
    const long = $('[data-lx-long]', sec), ticket = $('[data-lx-ticket]', sec), typed = $('[data-story-type]', sec), ok = $('[data-story-ok]', sec);
    const chips = $$('.lx-chip', sec), stats = $('[data-story-stats]', sec), views = $('[data-story-views]', sec), steps = $$('[data-lx-steps] li', sec), body = $('.lx-story-body', sec);
    const text = long.textContent, slug = typed.textContent, o = { n: 0, v: 0 };
    long.textContent = '';
    long.setAttribute('aria-label', text);
    const chars = Array.from(text).map((ch) => { const s = document.createElement('span'); s.className = 'c'; s.textContent = ch; s.setAttribute('aria-hidden', 'true'); long.appendChild(s); return s; });
    const vanish = chars.slice('https://example.com/'.length).reverse();
    typed.textContent = '';

    const tl = gsap.timeline({ defaults: { ease: 'power2.out' } });
    tl.set(ok, { autoAlpha: 0, scale: .8 }).set(ticket, { autoAlpha: 0, y: 30, scale: .94 }).set(chips, { autoAlpha: 0, scale: .4 }).set(stats, { autoAlpha: 0, y: 24 })
        // 1 paste
        .from(chars, { opacity: 0, duration: .02, stagger: .004 }, 0)
        // 2 slug
        .to(o, { n: slug.length, duration: .8, ease: 'none', onUpdate: () => { typed.textContent = slug.slice(0, Math.round(o.n)); } }, 1.1)
        .to(ok, { autoAlpha: 1, scale: 1, duration: .15, ease: 'back.out(2)' }, 1.95)
        // 3 short link and QR
        .to(vanish, { opacity: 0, width: 0, overflow: 'hidden', duration: .02, stagger: .0035 }, 2.2)
        .to(ticket, { autoAlpha: 1, y: 0, scale: 1, duration: .5, ease: 'back.out(1.4)' }, 2.9)
        // 4 share
        .to(chips, { autoAlpha: 1, scale: 1, duration: .35, stagger: .1, ease: 'back.out(1.8)' }, 3.6)
        // 5 numbers
        .to(stats, { autoAlpha: 1, y: 0, duration: .4 }, 4.4)
        .from($$('.lx-bars i', sec), { scaleY: 0, transformOrigin: '50% 100%', duration: .4, stagger: .06 }, 4.5)
        .to(o, { v: 1284, duration: .7, ease: 'power1.out', onUpdate: () => { views.textContent = Math.round(o.v).toLocaleString(lang()); } }, 4.5)
        .to({}, { duration: .35 }); // a short hold at the end

    const mark = (progress) => {
        const at = Math.min(steps.length - 1, Math.floor(progress * steps.length));
        steps.forEach((li, i) => { li.classList.toggle('on', i === at); li.classList.toggle('done', i < at); });
    };
    mark(0);
    const mm = gsap.matchMedia();
    mm.add('(min-width: 981px)', () => {
        ScrollTrigger.create({ animation: tl, trigger: sec, start: 'top 64px', end: '+=3400', pin: true, scrub: .7, onUpdate: (self) => mark(self.progress) });
    });
    mm.add('(max-width: 980px)', () => {
        ScrollTrigger.create({ animation: tl, trigger: $('.lx-story-vis', sec), start: 'top 70%', end: 'bottom 30%', scrub: .7, onUpdate: (self) => mark(self.progress) });
    });
    // the short link tilts towards the pointer
    if (matchMedia('(hover: hover)').matches) {
        body.addEventListener('pointermove', (e) => { const r = ticket.getBoundingClientRect(); ticket.style.setProperty('--ry', (((e.clientX - r.left) / r.width - .5) * 16) + 'deg'); ticket.style.setProperty('--rx', ((.5 - (e.clientY - r.top) / r.height) * 12) + 'deg'); });
        body.addEventListener('pointerleave', () => { ticket.style.setProperty('--ry', '0deg'); ticket.style.setProperty('--rx', '0deg'); });
    }
}

/** 03: the two chats swing in from the sides as the scene arrives */
function share() {
    const sec = $('[data-lx-share]');
    if (!sec) return;
    const a = $('.lx-chat-a', sec), b = $('.lx-chat-b', sec);
    gsap.matchMedia().add('(min-width: 641px)', () => {
        gsap.fromTo(a, { rotationY: 42, x: -80, opacity: 0 }, { rotationY: 10, x: 0, opacity: 1, ease: 'none', scrollTrigger: { trigger: sec, start: 'top 80%', end: 'top 35%', scrub: .6 } });
        gsap.fromTo(b, { rotationY: -44, x: 90, opacity: 0 }, { rotationY: -12, x: 0, opacity: 1, ease: 'none', scrollTrigger: { trigger: sec, start: 'top 72%', end: 'top 25%', scrub: .6 } });
    });
    gsap.from($$('.lx-in, .lx-tones, .lx-lab', sec), { y: 18, opacity: 0, duration: .6, stagger: .06, ease: 'power3.out', scrollTrigger: once(sec, 'top 72%') });
}

/** 04: the cubes of the QR code rise with the scroll; the colours try themselves until a visitor picks one */
function qr() {
    const sec = $('[data-lx-qr]');
    if (!sec) return;
    const vox = $('[data-lx-vox]', sec);
    vox.style.setProperty('--p', 0.04);
    gsap.to(vox, { '--p': 1, ease: 'none', scrollTrigger: { trigger: vox, start: 'top 88%', end: 'top 38%', scrub: .5 } });
    gsap.from($$('.lx-qr-real', sec), { y: 18, opacity: 0, duration: .6, stagger: .1, ease: 'power3.out', scrollTrigger: once(sec, 'top 72%') });
    const demo = qrDemo.current;
    if (!demo) return;
    ScrollTrigger.create({
        ...once(vox, 'top 65%'),
        onEnter: () => {
            [1, 2, 3, 0].forEach((i, n) => gsap.delayedCall(1.2 + n * 1.4, () => { if (demo.touched()) return; demo.pick(i); }));
        },
    });
}

/** 05: the bars grow, the line draws, the figures count */
function analytics() {
    const sec = $('[data-lx-analytics]');
    if (!sec) return;
    const card = $('.lx-win', sec), draw = $('.draw', sec), area = $('.area', sec);
    const len = draw.getTotalLength();
    gsap.set(draw, { strokeDasharray: len, strokeDashoffset: len });
    gsap.set(area, { opacity: 0 });
    gsap.to(draw, { strokeDashoffset: 0, ease: 'none', scrollTrigger: { trigger: card, start: 'top 78%', end: 'bottom 52%', scrub: .5 } });
    gsap.to(area, { opacity: 1, ease: 'none', scrollTrigger: { trigger: card, start: 'top 70%', end: 'bottom 45%', scrub: .5 } });
    $$('[data-metric]', sec).forEach((el) => {
        const end = parseFloat(el.dataset.metric), suffix = el.dataset.suffix || '', o = { v: 0 };
        el.textContent = '0' + suffix;
        gsap.to(o, { v: end, duration: 1.4, ease: 'power2.out', onUpdate: () => { el.textContent = Math.round(o.v).toLocaleString(lang()) + suffix; }, scrollTrigger: once(card, 'top 80%') });
    });
    const iso = $('[data-lx-iso]', sec);
    if (iso) {
        iso.style.setProperty('--p', 0);
        gsap.to(iso, { '--p': 1, ease: 'none', scrollTrigger: { trigger: iso, start: 'top 85%', end: 'top 35%', scrub: .5 } });
    }
    gsap.from($$('.lx-top li', sec), { x: -18, opacity: 0, duration: .55, stagger: .1, ease: 'power2.out', scrollTrigger: once('.lx-top', 'top 88%') });
    gsap.from($$('.lx-top i', sec), { scaleX: 0, transformOrigin: 'left center', duration: .9, stagger: .1, ease: 'power3.out', scrollTrigger: once('.lx-top', 'top 88%') });
    gsap.from($$('.lx-heat i', sec), { opacity: 0, duration: .4, stagger: { each: .004, from: 'random' }, scrollTrigger: once('.lx-heat', 'top 90%') });
}

/** 06: a link is scheduled, goes live, counts up to its limit, ends and sends visitors on; it plays again while in view */
function control() {
    const sec = $('[data-lx-control]');
    if (!sec) return;
    const items = $$('[data-lx-track] li', sec), gauge = $('[data-lx-gauge]', sec), count = $('[data-lx-count]', sec), state = $('[data-lx-state]', sec), dest = $('[data-lx-dest]', sec);
    const label = (i) => $('b', items[i]).textContent, d = (i) => dest.dataset['d' + i];
    const o = { n: 0 };
    const apply = (i) => {
        items.forEach((li, n) => li.classList.toggle('on', n === i));
        state.textContent = label(i);
        state.classList.toggle('is-wait', i === 0);
        state.classList.toggle('is-ended', i >= 2);
        dest.textContent = d(i);
    };
    const show = () => { const v = Math.round(o.n); count.textContent = v; gauge.style.setProperty('--v', v); };
    const tl = gsap.timeline({ paused: true, repeat: -1, repeatDelay: 1 });
    tl.call(() => { o.n = 0; show(); apply(0); }).to({}, { duration: 1.6 })
        .call(() => apply(1))
        .to(o, { n: 100, duration: 3, ease: 'power1.inOut', onUpdate: show })
        .call(() => apply(2)).to({}, { duration: 1.2 })
        .call(() => apply(3)).to({}, { duration: 1.8 });
    ScrollTrigger.create({ trigger: sec, start: 'top 80%', end: 'bottom 10%', onToggle: (self) => (self.isActive ? tl.play() : tl.pause()) });
}

/** 07: visitors travel along the lines to their own destination, one after another */
function routing() {
    const sec = $('[data-lx-routing]');
    if (!sec) return;
    const paths = $$('.links path', sec), pkt = $('.pkt', sec), who = $$('.lx-who', sec), dests = $$('.lx-dest', sec), bars = $$('.lx-ab-bar i', sec);
    $$('.pkt', sec).slice(1).forEach((p) => p.remove());
    paths.forEach((p) => { const l = p.getTotalLength(); gsap.set(p, { strokeDasharray: l, strokeDashoffset: l }); });
    gsap.set(pkt, { opacity: 0 });
    const tl0 = gsap.timeline({ scrollTrigger: once('.lx-net', 'top 72%') });
    tl0.from('.lx-core', { scale: .6, opacity: 0, duration: .6, ease: 'back.out(1.8)' })
        .to(paths, { strokeDashoffset: 0, duration: .8, stagger: .06, ease: 'power2.inOut' }, '-=.2')
        .from([...who, ...dests], { opacity: 0, scale: .8, duration: .4, stagger: .05 }, '-=.8')
        .from(bars, { scaleX: 0, duration: .9, stagger: .15, ease: 'power3.out' }, '-=.2');
    const move = (path, o) => {
        const len = path.getTotalLength(), pt = path.getPointAtLength(len * o.t);
        pkt.setAttribute('cx', pt.x); pkt.setAttribute('cy', pt.y);
    };
    const tl = gsap.timeline({ paused: true, repeat: -1 });
    [0, 1, 2].forEach((i) => {
        const a = { t: 0 }, b = { t: 0 };
        tl.call(() => { who.forEach((w, n) => w.classList.toggle('on', n === i)); dests.forEach((x) => x.classList.remove('on')); gsap.set(pkt, { opacity: 1 }); })
            .to(a, { t: 1, duration: .9, ease: 'power1.inOut', onUpdate: () => move(paths[i], a) })
            .call(() => dests[i].classList.add('on'))
            .to(b, { t: 1, duration: .9, ease: 'power1.inOut', onUpdate: () => move(paths[3 + i], b) })
            .to({}, { duration: .6 });
    });
    ScrollTrigger.create({ trigger: '.lx-net', start: 'top 80%', end: 'bottom 10%', onToggle: (self) => (self.isActive ? tl.play() : tl.pause()) });
}

/** 08: the gate asks, the answer is given, the page opens; the checker scans a link; it plays again while in view */
function gates() {
    $$('.lx-gate').forEach((gate) => {
        const tl = gsap.timeline({ paused: true, repeat: -1, repeatDelay: 1.2, defaults: { ease: 'power2.out' } });
        if (gate.dataset.gate === 'scan') {
            const box = $('.lx-scan-box', gate), line = $('.lx-scanline', gate), res = $('.lx-scan-res', gate);
            tl.set(res, { autoAlpha: 0, y: 10 }).set(line, { autoAlpha: 0, y: 0 })
                .to(line, { autoAlpha: 1, duration: .15 })
                .to(line, { y: () => box.clientHeight - 2, duration: 1.1, ease: 'none' })
                .to(line, { autoAlpha: 0, duration: .15 })
                .to(res, { autoAlpha: 1, y: 0, duration: .5 }).to({}, { duration: 1.6 });
        } else {
            const page = $('.lx-gate-page', gate), box = $('.lx-gate-box', gate), go = $('.lx-go', gate), dots = $('[data-gate-dots]', gate), shackle = $('.shackle', gate);
            const full = dots ? dots.textContent : '', o = { n: 0 };
            tl.set(page, { filter: 'blur(7px)' }).set(box, { autoAlpha: 1, scale: 1 }).set(go, { scale: 1 })
                .call(() => { if (dots) dots.textContent = ''; })
                .from(box, { y: 16, autoAlpha: 0, duration: .5 });
            if (dots) tl.to(o, { n: full.length, duration: 1, ease: 'none', onUpdate: () => { dots.textContent = full.slice(0, Math.round(o.n)); } });
            else tl.to({}, { duration: .8 });
            tl.to(go, { scale: .93, duration: .12, yoyo: true, repeat: 1 }, '+=.2');
            if (shackle) tl.to(shackle, { y: -7, duration: .25, ease: 'back.out(3)' }, '<');
            tl.to(box, { autoAlpha: 0, scale: .92, duration: .4 }, '+=.15').to(page, { filter: 'blur(0px)', duration: .5 }, '<').to({}, { duration: 1.4 });
        }
        ScrollTrigger.create({ trigger: gate, start: 'top 85%', end: 'bottom 10%', onToggle: (self) => (self.isActive ? tl.play() : tl.pause()) });
    });
}

/** 09 and 10: the commands type themselves, then the answers print line by line; the copy button always gets the whole line */
function build() {
    ['#api-curl', '#install-sh'].forEach((sel) => {
        const el = $(sel);
        if (!el) return;
        const full = el.textContent;
        el.setAttribute('data-full', full);
        el.setAttribute('aria-label', full);
        const o = { n: 0 }, out = $$('span', $('pre[data-out]', el.closest('.h-shell')) || document.createElement('i'));
        el.textContent = '';
        gsap.set(out, { opacity: 0 });
        ScrollTrigger.create({
            ...once(el, 'top 85%'),
            onEnter: () => {
                el.classList.add('lx-typing');
                gsap.timeline()
                    .to(o, { n: full.length, duration: Math.min(2.6, full.length * .02), ease: 'none', onUpdate: () => { el.textContent = full.slice(0, Math.round(o.n)); }, onComplete: () => { el.textContent = full; el.classList.remove('lx-typing'); } })
                    .to(out, { opacity: 1, duration: .05, stagger: .45, ease: 'none' }, '+=.2');
            },
        });
    });
    $$('.lx-hook pre span').forEach((s) => gsap.set(s, { opacity: 0 }));
    ScrollTrigger.create({ ...once('.lx-hook', 'top 80%'), onEnter: () => gsap.to($$('.lx-hook pre span'), { opacity: 1, duration: .05, stagger: .5, ease: 'none' }) });
    $$('[data-copy-from]').forEach((btn) => btn.addEventListener('click', () => {
        const src = $(btn.getAttribute('data-copy-from'));
        if (src && src.getAttribute('data-full')) src.textContent = src.getAttribute('data-full');
    }, true));
}

function faq() {
    $$('[data-lx-faq] .lx-cell').forEach((c, i) => gsap.from(c, { y: 34, opacity: 0, duration: .7, ease: 'power3.out', delay: (i % 2) * .08, scrollTrigger: once(c, 'top 92%') }));
}

function family() {
}

if (document.querySelector('.hop-home')) {
    const boot = () => {
        shareDemo();
        qrDemo.current = qrDemo();
        if (!reduced) start();
    };
    if (document.readyState === 'loading') addEventListener('DOMContentLoaded', boot);
    else boot();
}
