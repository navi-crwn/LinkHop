// Delegated replacements for inline on* attributes, so pages work under a CSP
// without 'unsafe-inline' for event handlers (script-src-attr 'none').
//
//   data-confirm="Question?"         on a form (submit) or a button/link (click): asked in the family dialog
//     data-confirm-message="…"       the sentence under the question
//     data-confirm-danger            a delete: red button, focus starts on Cancel
//     data-confirm-subject="name"    what the delete removes (the button reads: Delete “name”)
//   data-autosubmit                  on a form control: submit its form on change
//   data-nav="?period={value}"       on a select: go to the URL with its value
//   data-call="fnName"               on a clickable: call window.fnName(...data-args)
//   data-args='["a", 1]'             JSON array of arguments for data-call
//   data-show / data-hide="#id"      on a clickable: show or hide an element
//   data-hide-self-on-backdrop       on an overlay: hide it when its backdrop is clicked
//   data-toggle-class="name"         on a clickable: toggle a class on itself
//   data-copy-from="#id"             on a button: copy that input's value
//   data-toggle-dark                 on a button: toggle dark mode and remember it
//   data-prompt-password="Question"  on a button in a form: ask for the password, then submit
//   data-reload                      on a button: reload the page
//   data-fallback="hide"             on an img: hide it when it fails to load
//   data-fallback-src="url"          on an img: swap to this image when it fails to load
//   data-back                        on a link: go back in history when there is a page to go back to, else follow href

import { hopConfirm, hopPrompt } from './dialogs';

function handleImageError(img) {
    if (!(img instanceof HTMLImageElement) || img.dataset.fallbackDone) return;
    if (img.dataset.fallbackSrc) {
        img.dataset.fallbackDone = '1';
        img.src = img.dataset.fallbackSrc;
    } else if (img.dataset.fallback === 'hide') {
        img.style.display = 'none';
    }
}

// The question of a [data-confirm] element, asked through hopDialog (the family dialog, or the browser's).
function ask(el, anchor) {
    return hopConfirm({
        title: el.dataset.confirm,
        message: el.dataset.confirmMessage || '',
        danger: el.hasAttribute('data-confirm-danger'),
        subject: el.dataset.confirmSubject || '',
        anchor: anchor || el,
    });
}

function copyText(button, text) {
    const original = button.textContent;
    navigator.clipboard?.writeText(text).then(() => {
        button.textContent = button.dataset.copiedText || 'Copied!';
        setTimeout(() => (button.textContent = original), 2000);
    });
}

export default function installInlineActions(root = document) {
    // Image errors do not bubble, but they can be caught on the way down.
    root.addEventListener('error', (e) => handleImageError(e.target), true);
    // Images that failed before this script loaded.
    root.querySelectorAll('img[data-fallback], img[data-fallback-src]').forEach((img) => {
        if (img.complete && img.naturalWidth === 0 && img.getAttribute('src')) handleImageError(img);
    });

    // The dialog answers later, so the first submit is held back and the form is submitted again on "yes".
    const confirmed = new WeakSet();
    root.addEventListener('submit', (e) => {
        const form = e.target;
        if (!(form instanceof HTMLFormElement) || !form.dataset.confirm) return;
        if (confirmed.has(form)) {
            confirmed.delete(form);
            return;
        }
        e.preventDefault();
        e.stopImmediatePropagation();
        const submitter = e.submitter || null;
        ask(form, submitter || form.querySelector('button[type="submit"], button:not([type])')).then((yes) => {
            if (!yes) return;
            confirmed.add(form);
            // The browser's own question answers at once, still inside this submit event, and a form ignores a
            // submit that is asked for while it is firing one: so go after the event.
            setTimeout(() => {
                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit(submitter && submitter.form === form ? submitter : undefined);
                } else {
                    form.submit();
                }
            }, 0);
        });
    }, true);

    root.addEventListener('change', (e) => {
        const el = e.target;
        if (!(el instanceof HTMLElement)) return;
        if (el.hasAttribute('data-autosubmit') && el.form) {
            el.form.submit();
        } else if (el.dataset.nav) {
            window.location.href = el.dataset.nav.replace('{value}', encodeURIComponent(el.value));
        }
    });

    root.addEventListener('click', (e) => {
        if (!(e.target instanceof Element)) return;

        const backdrop = e.target.closest('[data-hide-self-on-backdrop]');
        if (backdrop && e.target === backdrop) {
            backdrop.classList.add('hidden');
            return;
        }

        const confirmEl = e.target.closest('[data-confirm]:not(form)');
        if (confirmEl) {
            if (confirmed.has(confirmEl)) {
                confirmed.delete(confirmEl);
            } else {
                e.preventDefault();
                e.stopImmediatePropagation();
                ask(confirmEl).then((yes) => {
                    if (yes) {
                        confirmed.add(confirmEl);
                        // after this click event: an element ignores click() while it is being clicked
                        setTimeout(() => confirmEl.click(), 0);
                    }
                });
                return;
            }
        }

        const back = e.target.closest('a[data-back]');
        if (back && window.history.length > 1 && document.referrer && document.referrer !== window.location.href) {
            e.preventDefault();
            window.history.back();
            return;
        }

        const el = e.target.closest('[data-call], [data-show], [data-hide], [data-toggle-class], [data-copy-from], [data-toggle-dark], [data-prompt-password], [data-reload]');
        if (!el) return;

        if (el.hasAttribute('data-reload')) window.location.reload();
        if (el.dataset.promptPassword && el.form) {
            const form = el.form;
            hopPrompt({ title: el.dataset.promptPassword, label: el.dataset.promptLabel || '', type: 'password', anchor: el }).then((password) => {
                const field = form.querySelector('input[name="password"]');
                if (password && field) {
                    field.value = password;
                    // requestSubmit() fires submit, so the form's data-confirm still asks first.
                    form.requestSubmit();
                }
            });
        }

        if (el.dataset.show) document.querySelector(el.dataset.show)?.classList.remove('hidden');
        if (el.dataset.hide) document.querySelector(el.dataset.hide)?.classList.add('hidden');
        if (el.dataset.toggleClass) el.classList.toggle(el.dataset.toggleClass);
        if (el.dataset.copyFrom) copyText(el, document.querySelector(el.dataset.copyFrom)?.value ?? '');
        if (el.hasAttribute('data-toggle-dark')) {
            const dark = document.documentElement.classList.toggle('dark');
            // hop_theme (shared by the HOP apps) and the old helink-theme key: window.hopSetTheme from partials/theme-boot.
            if (typeof window.hopSetTheme === 'function') window.hopSetTheme(dark ? 'dark' : 'light');
            else try { localStorage.setItem('helink-theme', dark ? 'dark' : 'light'); } catch (err) { /* storage blocked */ }
        }
        if (el.dataset.call) {
            const fn = window[el.dataset.call];
            if (typeof fn === 'function') {
                let args = [];
                try { args = el.dataset.args ? JSON.parse(el.dataset.args) : []; } catch (err) { args = []; }
                fn.apply(el, args);
            } else if (el.dataset.fallbackHref) {
                window.location.href = el.dataset.fallbackHref;
            }
        }
    });
}
