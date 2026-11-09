// Command palette on Ctrl/Cmd+K: pages and actions from the page itself, the user's own links from /palette/links.
// Keyboard: arrows move, Enter opens, Escape closes, Tab stays inside. No animation, so reduced motion needs nothing special.
export const registerCommandPalette = (Alpine) => {
    Alpine.data('commandPalette', (config) => ({
        open: false,
        query: '',
        active: 0,
        links: [],
        loading: false,
        opener: null,
        timer: null,
        ctrl: null,
        seq: 0,
        isMac: /Mac|iPhone|iPad/.test(navigator.platform || ''),
        get commands() {
            return config.commands;
        },
        get shortcut() {
            return this.isMac ? '⌘K' : 'Ctrl K';
        },
        get pages() {
            const q = this.query.trim().toLowerCase();
            return this.commands.filter((c) => !q || `${c.label} ${c.keywords || ''}`.toLowerCase().includes(q));
        },
        // One flat list for the arrow keys: commands first, then links.
        get items() {
            return [
                ...this.pages.map((c) => ({ ...c, kind: 'page', key: `p-${c.id}` })),
                ...this.links.map((l) => ({
                    kind: 'link', key: `l-${l.id}`, label: `/l/${l.slug}`, hint: l.title || l.target_url, href: l.url, edit: l.edit_url,
                })),
            ];
        },
        toggle() {
            this.open ? this.close() : this.show();
        },
        show() {
            if (this.open) {
                return;
            }
            this.opener = document.activeElement;
            this.open = true;
            this.query = '';
            this.active = 0;
            document.documentElement.classList.add('overflow-hidden');
            this.fetchLinks();
            // Once now, and once more after the opener (a closing drawer, a fading menu) has let go of the focus.
            this.$nextTick(() => this.$refs.input.focus());
            setTimeout(() => { if (this.open && document.activeElement !== this.$refs.input) this.$refs.input.focus(); }, 120);
        },
        close() {
            this.open = false;
            this.ctrl?.abort();
            document.documentElement.classList.remove('overflow-hidden');
            this.$nextTick(() => this.opener?.focus?.());
        },
        onGlobalKey(e) {
            if ((e.ctrlKey || e.metaKey) && !e.altKey && !e.shiftKey && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                this.toggle();
            }
        },
        onInput() {
            this.active = 0;
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.fetchLinks(), 160);
        },
        async fetchLinks() {
            const seq = ++this.seq;
            this.ctrl?.abort();
            this.ctrl = new AbortController();
            this.loading = true;
            try {
                const res = await fetch(`${config.linksUrl}?q=${encodeURIComponent(this.query.trim())}`, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                    signal: this.ctrl.signal,
                });
                if (seq === this.seq) {
                    this.links = res.ok ? (await res.json()).links || [] : [];
                }
            } catch {
                if (seq === this.seq) {
                    this.links = [];
                }
            } finally {
                if (seq === this.seq) {
                    this.loading = false;
                }
            }
        },
        move(step) {
            const n = this.items.length;
            if (n) {
                this.active = (this.active + step + n) % n;
                this.$nextTick(() => document.getElementById(`palette-opt-${this.active}`)?.scrollIntoView({ block: 'nearest' }));
            }
        },
        run(item, edit = false) {
            item = item || this.items[this.active];
            if (!item) {
                return;
            }
            this.close();
            if (item.kind === 'link') {
                window.location.href = edit ? item.edit : item.href;
            } else if (item.action === 'new-link') {
                typeof window.openLinkPanel === 'function' ? window.openLinkPanel() : (window.location.href = item.href);
            } else if (item.action === 'theme') {
                window.toggleTheme?.();
            } else if (item.href) {
                window.location.href = item.href;
            }
        },
        // Tab cycles between the search field and the close button.
        trap(e) {
            const focusable = [...this.$refs.dialog.querySelectorAll('input, button')];
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        },
    }));
};
