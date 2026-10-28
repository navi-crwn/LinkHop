// Floating HOP app tiles on the auth pages: each tile drifts across the viewport and
// bounces off the edges (the DVD-logo idea), picking a new colour theme on every
// bounce. Transforms only, one animation frame loop, paused when the tab is hidden.
// Under prefers-reduced-motion the tiles just sit where they started.
const THEMES = 6;

export default function installHopFloat() {
    const layer = document.querySelector('[data-hop-float]');
    if (!layer) return;
    const sprites = Array.from(layer.querySelectorAll('.hop-sprite'));
    if (!sprites.length) return;

    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)');
    let W = 0, H = 0;
    const bodies = sprites.map((el, i) => {
        // Spread the start positions and send each tile off at its own angle.
        const angle = (i / sprites.length) * Math.PI * 2 + 0.6;
        const speed = 28 + (i % 3) * 9; // px per second
        return {
            el,
            theme: Number((el.className.match(/hop-theme-(\d)/) || [])[1] ?? i % THEMES),
            x: 0, y: 0, size: 72,
            fx: 0.08 + ((i * 0.37) % 0.8), // fraction of the viewport to start at
            fy: 0.12 + ((i * 0.53) % 0.7),
            vx: Math.cos(angle) * speed,
            vy: Math.sin(angle) * speed,
            placed: false,
        };
    });

    const measure = () => {
        W = layer.clientWidth;
        H = layer.clientHeight;
        for (const b of bodies) {
            b.size = b.el.offsetWidth || 72;
            if (!b.placed) {
                b.x = b.fx * (W - b.size);
                b.y = b.fy * (H - b.size);
                b.placed = true;
            }
            b.x = Math.min(Math.max(b.x, 0), Math.max(W - b.size, 0));
            b.y = Math.min(Math.max(b.y, 0), Math.max(H - b.size, 0));
            paint(b);
        }
    };

    const paint = (b) => {
        b.el.style.transform = `translate3d(${b.x.toFixed(1)}px, ${b.y.toFixed(1)}px, 0)`;
    };

    const recolor = (b) => {
        const next = (b.theme + 1 + Math.floor(Math.random() * (THEMES - 1))) % THEMES;
        b.el.classList.remove(`hop-theme-${b.theme}`);
        b.el.classList.add(`hop-theme-${next}`);
        b.theme = next;
        b.el.classList.remove('is-bounce');
        void b.el.offsetWidth; // restart the squash animation
        b.el.classList.add('is-bounce');
    };

    let last = 0;
    let raf = 0;
    const step = (t) => {
        raf = 0;
        const dt = Math.min((t - last) / 1000, 0.05) || 0;
        last = t;
        for (const b of bodies) {
            b.x += b.vx * dt;
            b.y += b.vy * dt;
            const maxX = Math.max(W - b.size, 0);
            const maxY = Math.max(H - b.size, 0);
            let hit = false;
            if (b.x <= 0) { b.x = 0; b.vx = Math.abs(b.vx); hit = true; }
            else if (b.x >= maxX) { b.x = maxX; b.vx = -Math.abs(b.vx); hit = true; }
            if (b.y <= 0) { b.y = 0; b.vy = Math.abs(b.vy); hit = true; }
            else if (b.y >= maxY) { b.y = maxY; b.vy = -Math.abs(b.vy); hit = true; }
            if (hit) recolor(b);
            paint(b);
        }
        if (running) raf = requestAnimationFrame(step);
    };

    let running = false;
    const start = () => {
        if (running || reduce.matches) return;
        running = true;
        last = performance.now();
        if (!raf) raf = requestAnimationFrame(step);
    };
    const stop = () => {
        running = false;
        if (raf) cancelAnimationFrame(raf);
        raf = 0;
    };

    measure();
    layer.classList.add('is-ready');
    window.addEventListener('resize', measure, { passive: true });
    document.addEventListener('visibilitychange', () => (document.hidden ? stop() : start()));
    reduce.addEventListener?.('change', () => (reduce.matches ? stop() : start()));
    start();
}
