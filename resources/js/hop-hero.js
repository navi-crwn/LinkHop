// Home page hero: LinkHop bunnies wait in cities around a dotted world map. One at a time a
// bunny hops to hel.ink, the short link lights up, and an arrow carries it on to the long
// destination. Every hop shows another example link. Plain SVG + DOM, no libraries.
// Under prefers-reduced-motion it shows one example without moving; it pauses off screen.

const LINKS = [
    ['school', 'school.example.com/students/2026/semester-2/assignments/final-project-guidelines.pdf'],
    ['shop', 'shop.example.com/collections/summer-sale/products/linen-shirt?color=sand&size=m'],
    ['event', 'events.example.com/2026/jakarta-tech-meetup/registration?ref=newsletter'],
    ['menu', 'cafe.example.com/menu/today/lunch-specials-and-cold-drinks'],
    ['wedding', 'photos.example.com/albums/2026/rina-and-dimas-wedding/highlights'],
    ['podcast', 'podcast.example.com/episodes/142-how-small-teams-ship-fast'],
    ['cv', 'drive.example.com/file/d/1a2b3c4d5e6f7g8h9i0j/view?usp=sharing'],
    ['promo', 'store.example.com/checkout?promo=HOP25&utm_source=instagram&utm_medium=story'],
    ['course', 'learn.example.com/courses/web-basics/lesson-07-forms-and-validation'],
    ['rsvp', 'forms.example.com/r/birthday-party-rsvp-saturday-7pm-rooftop'],
    ['docs', 'docs.example.com/api/v2/reference/links#create-a-short-link'],
    ['playlist', 'music.example.com/playlist/37i9dQZF1DX0XUsuxWHRQd?si=friday-mix'],
    ['map', 'maps.example.com/place/warung-makan-bu-sri/@-6.2088,106.8456,17z'],
    ['jobs', 'careers.example.com/jobs/frontend-engineer-remote-asia-2026?source=linkedin'],
    ['portfolio', 'design.example.com/u/ayu-pratama/projects/brand-refresh-case-study'],
    ['report', 'reports.example.com/q3-2026/annual-summary-final-v3-approved.pdf'],
];

// [country code, latitude, longitude]
const PLACES = [
    ['ID', -6.2, 106.8], ['JP', 35.7, 139.7], ['IN', 19.1, 72.9], ['US', 40.7, -74.0], ['BR', -23.5, -46.6],
    ['DE', 52.5, 13.4], ['NG', 6.5, 3.4], ['AU', -33.9, 151.2], ['MX', 19.4, -99.1], ['ZA', -26.2, 28.0],
];

// same projection as public/images/world-dots.svg: 1000 x 380, lat 72..-56
const project = (lat, lon) => [((lon + 180) / 360) * 1000, ((72 - lat) / 128) * 380];
const HUB = [500, 352];
const NS = 'http://www.w3.org/2000/svg';
const el = (tag, attrs, parent) => {
    const e = document.createElementNS(NS, tag);
    Object.entries(attrs).forEach(([k, v]) => e.setAttribute(k, v));
    parent?.appendChild(e);
    return e;
};

function start(root) {
    const svg = root.querySelector('[data-hop-layer]');
    const mark = root.dataset.mark;
    const short = root.querySelector('[data-hop-short]');
    const dest = root.querySelector('[data-hop-dest]');
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const big = () => (root.clientWidth < 640 ? 1.9 : 1); // the map shrinks a lot on phones; the bunnies shouldn't

    // hel.ink in the middle: a glowing mark the bunnies land on
    const hub = el('g', { transform: `translate(${HUB[0]} ${HUB[1]}) scale(${big()})` }, svg);
    el('circle', { r: 30, class: 'hop-hub-glow' }, hub);
    el('circle', { r: 21, fill: '#fff', stroke: '#c7d2fe', 'stroke-width': 2 }, hub);
    el('image', { href: mark, x: -15, y: -15, width: 30, height: 30 }, hub);

    const trail = el('path', { class: 'hop-trail', fill: 'none' }, svg);
    const bunnies = PLACES.map(([code, lat, lon]) => {
        const [x, y] = project(lat, lon);
        const g = el('g', { class: 'hop-bunny' }, svg);
        el('circle', { r: 15, fill: '#fff', stroke: '#e0e7ff', 'stroke-width': 1.5 }, g);
        el('image', { href: mark, x: -11, y: -11, width: 22, height: 22 }, g);
        el('text', { y: 28, 'text-anchor': 'middle', class: 'hop-tag' }, g).textContent = code;
        g.setAttribute('transform', `translate(${x} ${y}) scale(${big()})`);
        return { g, x, y };
    });

    let linkIx = Math.floor(Math.random() * LINKS.length), who = -1;
    const show = () => {
        const [slug, url] = LINKS[linkIx];
        short.textContent = `hel.ink/${slug}`;
        dest.textContent = url;
        dest.title = url;
    };
    show();
    if (reduce) {
        root.classList.add('is-arrived');
        return;
    }

    const PERIOD = 3400;
    let cycleStart = 0, raf = 0, visible = false;
    const pick = (n, not) => {
        let i;
        do i = Math.floor(Math.random() * n); while (i === not);
        return i;
    };
    const nextCycle = (t) => {
        cycleStart = t;
        who = pick(bunnies.length, who);
        linkIx = pick(LINKS.length, linkIx);
        root.classList.remove('is-arrived');
        bunnies.forEach((b, i) => b.g.classList.toggle('is-active', i === who));
    };

    const frame = (t) => {
        if (!cycleStart || t - cycleStart > PERIOD) nextCycle(t);
        const p = (t - cycleStart) / PERIOD, b = bunnies[who];
        // the hop: from the bunny's city, up and over, down onto hel.ink
        const cx = (b.x + HUB[0]) / 2, cy = Math.min(b.y, HUB[1]) - 120;
        const f = Math.min(1, Math.max(0, (p - 0.1) / 0.45)), e = f < 0.5 ? 2 * f * f : 1 - (-2 * f + 2) ** 2 / 2;
        const at = (k) => [
            (1 - k) ** 2 * b.x + 2 * (1 - k) * k * cx + k * k * HUB[0],
            (1 - k) ** 2 * b.y + 2 * (1 - k) * k * cy + k * k * HUB[1],
        ];
        if (p > 0.92) {
            b.g.setAttribute('transform', `translate(${b.x} ${b.y}) scale(${big()})`); // back home for its next turn
            b.g.style.opacity = 1;
            trail.setAttribute('d', '');
            return;
        }
        const [x, y] = at(e);
        const squash = p < 0.1 ? 1 - Math.sin((p / 0.1) * Math.PI) * 0.22 : f < 1 ? 1 + Math.sin(f * Math.PI) * 0.12 : 1;
        const scale = big() * (1 + Math.sin(e * Math.PI) * 0.35);
        b.g.setAttribute('transform', `translate(${x} ${y}) scale(${scale / Math.sqrt(squash)} ${scale * squash})`);
        b.g.style.opacity = p > 0.82 ? Math.max(0, 1 - (p - 0.82) / 0.08) : 1;
        let d = `M${b.x} ${b.y}`;
        for (let k = 0.04; k <= e; k += 0.04) d += `L${at(k).join(' ')}`;
        trail.setAttribute('d', f > 0 && p < 0.82 ? d : '');
        if (p > 0.55 && !root.classList.contains('is-arrived')) {
            show();
            root.classList.add('is-arrived'); // the short link lights up, the arrow runs on to the destination
        }
    };

    const loop = (t) => {
        frame(t);
        raf = visible ? requestAnimationFrame(loop) : 0;
    };
    new IntersectionObserver(([entry]) => {
        visible = entry.isIntersecting;
        if (visible && !raf) raf = requestAnimationFrame(loop);
    }).observe(root);
}

const root = document.querySelector('[data-hop-scene]');
if (root) start(root);
