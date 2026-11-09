// Small helpers for the dashboard's editors: optimistic copy, slug check, destination preview.

// A polite live region, so screen readers hear "Copied" without the focus moving.
let liveRegion;
const announce = (message) => {
    if (!message) {
        return;
    }
    if (!liveRegion) {
        liveRegion = document.createElement('div');
        liveRegion.setAttribute('role', 'status');
        liveRegion.setAttribute('aria-live', 'polite');
        liveRegion.className = 'sr-only';
        document.body.appendChild(liveRegion);
    }
    liveRegion.textContent = '';
    setTimeout(() => { liveRegion.textContent = message; }, 30);
};

const legacyCopy = (text) => {
    const area = document.createElement('textarea');
    area.value = text;
    area.setAttribute('readonly', '');
    area.style.cssText = 'position:fixed;top:-1000px;opacity:0';
    document.body.appendChild(area);
    area.select();
    let ok = false;
    try { ok = document.execCommand('copy'); } catch { ok = false; }
    area.remove();
    return ok;
};

// Copy and show "copied" at once; the clipboard answer only matters when it fails, and then the button says so.
// The button may carry data-copied / data-failed (translated words). Icon buttons swap their icon for a check
// (or a cross) and show the word in a small label above, text buttons change their text. No animation, so it is
// the same with reduced motion.
const copyTimers = new WeakMap();
const COPY_ICONS = {
    copied: '<svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>',
    failed: '<svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>',
};
const COPY_TIP_CLASS = 'pointer-events-none absolute -top-8 left-1/2 z-10 -translate-x-1/2 whitespace-nowrap rounded-md bg-slate-900 px-2 py-1 text-[11px] font-medium text-white shadow-lg dark:bg-white dark:text-slate-900';
window.hopCopy = (text, el) => {
    const isText = el.textContent.trim() !== '';
    const original = el._copyOriginal ??= { html: el.innerHTML, title: el.getAttribute('title'), position: el.style.position };
    const show = (state, label) => {
        clearTimeout(copyTimers.get(el));
        el.dataset.copyState = state;
        el.classList.toggle('text-emerald-500!', state === 'copied' && !isText);
        el.classList.toggle('text-rose-500!', state === 'failed' && !isText);
        if (isText && label) {
            el.textContent = label;
        } else if (label) {
            el.setAttribute('title', label);
            if (getComputedStyle(el).position === 'static') {
                el.style.position = 'relative';
            }
            el.innerHTML = COPY_ICONS[state] + `<span class="${COPY_TIP_CLASS}" aria-hidden="true">${label.replace(/[<>&]/g, '')}</span>`;
        }
        announce(label);
        copyTimers.set(el, setTimeout(() => {
            delete el.dataset.copyState;
            el.classList.remove('text-emerald-500!', 'text-rose-500!');
            el.innerHTML = original.html;
            el.style.position = original.position;
            original.title === null ? el.removeAttribute('title') : el.setAttribute('title', original.title);
        }, state === 'failed' ? 2500 : 1400));
    };
    show('copied', el.dataset.copied || 'Copied');
    const write = navigator.clipboard?.writeText ? navigator.clipboard.writeText(text) : Promise.reject(new Error('no clipboard'));
    write.catch(() => {
        if (!legacyCopy(text)) {
            show('failed', el.dataset.failed || 'Copy failed');
        }
    });
};

// Slug availability as you type. State: idle | checking | ok | bad. The server's message is already translated.
// Late answers to older questions are dropped.
window.hopSlugChecker = (endpoint, linkId = null, original = '') => ({
    state: 'idle',
    message: '',
    seq: 0,
    ctrl: null,
    async run(slug) {
        slug = (slug || '').trim();
        const seq = ++this.seq;
        this.ctrl?.abort();
        if (slug === '' || slug.toLowerCase() === original.toLowerCase()) {
            this.state = 'idle';
            this.message = '';
            return;
        }
        this.state = 'checking';
        this.ctrl = new AbortController();
        try {
            const query = new URLSearchParams({ slug });
            if (linkId) {
                query.set('link', linkId);
            }
            const res = await fetch(`${endpoint}?${query}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                signal: this.ctrl.signal,
            });
            if (seq !== this.seq) {
                return;
            }
            if (!res.ok) {
                this.state = 'idle';
                this.message = '';
                return;
            }
            const data = await res.json();
            this.state = data.available ? 'ok' : 'bad';
            this.message = data.message || '';
        } catch {
            if (seq === this.seq) {
                this.state = 'idle';
                this.message = '';
            }
        }
    },
});

// Edit page: slug field with the check, and a small preview of the destination when it changes.
export const registerEditorComponents = (Alpine) => {
    Alpine.data('slugField', (endpoint, linkId, original) => ({
        check: window.hopSlugChecker(endpoint, linkId, original),
    }));
    Alpine.data('urlPreview', (endpoint, original) => ({
        loading: false,
        info: null,
        timer: null,
        seq: 0,
        schedule(url, immediate = false) {
            clearTimeout(this.timer);
            url = (url || '').trim();
            if (url.length < 10 || url === original) {
                this.info = null;
                return;
            }
            this.timer = setTimeout(() => this.load(url), immediate ? 0 : 900);
        },
        async load(url) {
            const seq = ++this.seq;
            this.loading = true;
            try {
                const res = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ url: /^https?:\/\//i.test(url) ? url : `https://${url}` }),
                });
                const data = res.ok ? await res.json() : null;
                if (seq === this.seq) {
                    this.info = data?.success && data.data && (data.data.title || data.data.description) ? data.data : null;
                }
            } catch {
                if (seq === this.seq) {
                    this.info = null;
                }
            } finally {
                if (seq === this.seq) {
                    this.loading = false;
                }
            }
        },
    }));
};
