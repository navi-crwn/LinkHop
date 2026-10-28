import './bootstrap';
import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import Sortable from 'sortablejs';
import modal from './modal';
import installInlineActions from './inline-actions';
import installHopFloat from './hop-float';
Alpine.plugin(collapse);
Alpine.plugin(modal);
window.Alpine = Alpine;
window.Sortable = Sortable;
installInlineActions();
installHopFloat();
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
    challenge: false, // Cloudflare wants the visitor to click the checkbox
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
            size: this.$refs.captcha.dataset.size || 'normal',
            'before-interactive-callback': () => {
                this.challenge = true;
            },
            'after-interactive-callback': () => {
                this.challenge = false;
            },
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

// A page left open past SESSION_LIFETIME still holds the old CSRF token, so its next form
// (e.g. Logout) would fail with 419. When the tab is shown again after a while, fetch a fresh
// token and put it in the page. Values baked into inline scripts are not updated.
let csrfFetchedAt = Date.now();
const refreshCsrfToken = async () => {
    if (Date.now() - csrfFetchedAt < 5 * 60 * 1000) {
        return;
    }
    try {
        const response = await fetch('/csrf-token', { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        if (!response.ok) {
            return;
        }
        const { token } = await response.json();
        csrfFetchedAt = Date.now();
        document.querySelector('meta[name="csrf-token"]')?.setAttribute('content', token);
        document.querySelectorAll('input[name="_token"]').forEach((input) => {
            input.value = token;
        });
        if (window.axios) {
            window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
        }
    } catch {
        // Offline or blocked: the server-side handling of an expired token still applies.
    }
};
document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
        refreshCsrfToken();
    }
});
