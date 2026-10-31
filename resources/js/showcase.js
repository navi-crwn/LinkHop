// Scroll tells the same story as the buttons; no wheel/touch interception.
const reduced = matchMedia('(prefers-reduced-motion: reduce)');
const desktop = matchMedia('(min-width: 801px) and (min-height: 761px)');

document.querySelectorAll('[data-story]').forEach(root => {
    const buttons = [...root.querySelectorAll('[data-story-step]')];
    const panels = [...root.querySelectorAll('[data-story-panel]')];
    let active = 0, frame = 0;
    const pinned = () => desktop.matches && !reduced.matches;
    const select = index => {
        active = Math.max(0, Math.min(buttons.length - 1, index));
        buttons.forEach((button, i) => button.setAttribute('aria-pressed', String(i === active)));
        panels.forEach((panel, i) => { panel.hidden = i !== active; });
        root.querySelector('[data-story-progress]').style.width = `${(active + 1) / buttons.length * 100}%`;
        root.dataset.activeStep = String(active);
    };
    const move = index => {
        index = (index + buttons.length) % buttons.length;
        // Button navigation lands in the middle of its scroll chapter, avoiding boundary jitter.
        if (pinned()) {
            const top = root.getBoundingClientRect().top + scrollY - 84;
            const distance = root.offsetHeight - root.querySelector('.showcase-story-pin').offsetHeight;
            window.scrollTo({ top: top + distance * (index + .35) / buttons.length, behavior: 'instant' });
        }
        select(index);
    };
    buttons.forEach((button, index) => button.addEventListener('click', () => move(index)));
    root.querySelector('[data-story-prev]').addEventListener('click', () => move(active - 1));
    root.querySelector('[data-story-next]').addEventListener('click', () => move(active + 1));
    const update = () => {
        frame = 0;
        if (!pinned() || panels.some(panel => panel.contains(document.activeElement))) return;
        const distance = root.offsetHeight - root.querySelector('.showcase-story-pin').offsetHeight;
        const progress = Math.max(0, Math.min(.999, (84 - root.getBoundingClientRect().top) / Math.max(1, distance)));
        select(Math.floor(progress * buttons.length));
    };
    const queue = () => { if (!frame) frame = requestAnimationFrame(update); };
    root.classList.add('is-enhanced');
    select(0);
    addEventListener('scroll', queue, { passive: true });
    addEventListener('resize', queue, { passive: true });
    reduced.addEventListener('change', queue);
});

document.querySelectorAll('[data-tilt-scene]').forEach(root => {
    const object = root.querySelector('[data-tilt-object]');
    let x = 0, y = 0, paused = reduced.matches;
    const paint = () => {
        object.style.setProperty('--tilt-x', `${reduced.matches ? 0 : x}deg`);
        object.style.setProperty('--tilt-y', `${reduced.matches ? 0 : y}deg`);
    };
    root.addEventListener('pointermove', event => {
        if (paused || reduced.matches || event.target.closest('button, a, input, summary')) return;
        const box = root.getBoundingClientRect();
        x = ((event.clientX - box.left) / box.width - .5) * 22;
        y = -((event.clientY - box.top) / box.height - .5) * 12;
        paint();
    }, { passive: true });
    root.addEventListener('pointerleave', () => { x = 0; y = 0; paint(); });
    root.querySelector('[data-tilt-left]').addEventListener('click', () => { x = Math.max(-30, x - 10); paint(); });
    root.querySelector('[data-tilt-right]').addEventListener('click', () => { x = Math.min(30, x + 10); paint(); });
    root.querySelector('[data-tilt-reset]').addEventListener('click', () => { x = 0; y = 0; paint(); });
    const toggle = root.querySelector('[data-motion-toggle]');
    const sync = () => { root.classList.toggle('is-paused', paused || reduced.matches); toggle.setAttribute('aria-pressed', String(paused || reduced.matches)); paint(); };
    toggle.addEventListener('click', () => { paused = !paused; sync(); });
    reduced.addEventListener('change', sync);
    document.addEventListener('visibilitychange', () => root.classList.toggle('is-offscreen', document.hidden));
    new IntersectionObserver(([entry]) => root.classList.toggle('is-offscreen', !entry.isIntersecting)).observe(root);
    sync();
});
