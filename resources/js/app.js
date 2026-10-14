import './bootstrap';
import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import Sortable from 'sortablejs';
Alpine.plugin(collapse);
window.Alpine = Alpine;
window.Sortable = Sortable;
// Use requestAnimationFrame to ensure inline scripts in blade templates have executed
// This handles the race condition with module script execution
requestAnimationFrame(() => {
    requestAnimationFrame(() => {
        Alpine.start();
    });
});
// Cloudflare Turnstile: the widget renders on page load (interaction-only, so it
// stays invisible unless Cloudflare needs the visitor) and is usually solved
// before the form is filled in. Submitting early waits for the token and then
// submits automatically; expired tokens are refreshed silently.
const ensureTurnstileScript = () => {
    window.turnstileReady ??= new Promise((resolve) => {
        let script = document.getElementById('turnstile-script');
        if (!script) {
            script = document.createElement('script');
            script.id = 'turnstile-script';
            script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
            script.async = true;
            script.defer = true;
            document.head.appendChild(script);
        }
        script.addEventListener('error', () => resolve(null), { once: true });
        // The preloaded tag may have finished before we got here, so poll instead of
        // relying on its load event.
        const started = Date.now();
        const poll = setInterval(() => {
            if (window.turnstile || Date.now() - started > 20000) {
                clearInterval(poll);
                resolve(window.turnstile || null);
            }
        }, 50);
    });

    return window.turnstileReady;
};
window.turnstileForm = (siteKey) => ({
    siteKey,
    token: '',
    widgetId: null,
    status: 'idle', // idle | loading | ready | error
    waiting: false, // user submitted before the token was ready
    init() {
        if (!this.siteKey) {
            return;
        }
        this.form = this.$el.tagName === 'FORM' ? this.$el : this.$el.querySelector('form');
        this.status = 'loading';
        ensureTurnstileScript().then((turnstile) => this.renderCaptcha(turnstile));
    },
    renderCaptcha(turnstile) {
        if (!turnstile || !this.$refs.captcha) {
            this.fail();
            return;
        }
        this.widgetId = turnstile.render(this.$refs.captcha, {
            sitekey: this.siteKey,
            appearance: 'interaction-only',
            callback: (token) => {
                this.token = token;
                this.status = 'ready';
                if (this.waiting) {
                    this.waiting = false;
                    // Let Alpine write the token into the hidden input first.
                    this.$nextTick(() => this.form?.requestSubmit());
                }
            },
            'expired-callback': () => this.refresh(),
            'timeout-callback': () => this.refresh(),
            'error-callback': () => {
                this.fail();
                return true; // handled: show our message instead of Cloudflare's
            },
        });
    },
    refresh() {
        this.token = '';
        this.status = 'loading';
        if (window.turnstile && this.widgetId !== null) {
            window.turnstile.reset(this.widgetId);
        } else {
            ensureTurnstileScript().then((turnstile) => this.renderCaptcha(turnstile));
        }
    },
    fail() {
        this.token = '';
        this.status = 'error';
        this.waiting = false;
    },
    retry() {
        this.refresh();
    },
    handleSubmit(event) {
        if (!this.siteKey || this.token) {
            return;
        }
        event.preventDefault();
        this.waiting = true;
        if (this.status === 'error') {
            this.refresh();
        }
    },
});
