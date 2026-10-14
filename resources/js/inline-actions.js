// Delegated replacements for inline on* attributes, so pages work under a CSP
// without 'unsafe-inline' for event handlers (script-src-attr 'none').
//
//   data-confirm="Question?"         on a form (submit) or a button/link (click)
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

function handleImageError(img) {
    if (!(img instanceof HTMLImageElement) || img.dataset.fallbackDone) return;
    if (img.dataset.fallbackSrc) {
        img.dataset.fallbackDone = '1';
        img.src = img.dataset.fallbackSrc;
    } else if (img.dataset.fallback === 'hide') {
        img.style.display = 'none';
    }
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

    root.addEventListener('submit', (e) => {
        const form = e.target;
        if (form instanceof HTMLFormElement && form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
            e.preventDefault();
            e.stopImmediatePropagation();
        }
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
        if (confirmEl && !window.confirm(confirmEl.dataset.confirm)) {
            e.preventDefault();
            e.stopImmediatePropagation();
            return;
        }

        const el = e.target.closest('[data-call], [data-show], [data-hide], [data-toggle-class], [data-copy-from], [data-toggle-dark], [data-prompt-password], [data-reload]');
        if (!el) return;

        if (el.hasAttribute('data-reload')) window.location.reload();
        if (el.dataset.promptPassword && el.form) {
            const password = window.prompt(el.dataset.promptPassword);
            const field = el.form.querySelector('input[name="password"]');
            if (password && field) {
                field.value = password;
                // requestSubmit() fires submit, so the form's data-confirm still asks first.
                el.form.requestSubmit();
            }
        }

        if (el.dataset.show) document.querySelector(el.dataset.show)?.classList.remove('hidden');
        if (el.dataset.hide) document.querySelector(el.dataset.hide)?.classList.add('hidden');
        if (el.dataset.toggleClass) el.classList.toggle(el.dataset.toggleClass);
        if (el.dataset.copyFrom) copyText(el, document.querySelector(el.dataset.copyFrom)?.value ?? '');
        if (el.hasAttribute('data-toggle-dark')) {
            const dark = document.documentElement.classList.toggle('dark');
            try { localStorage.setItem('helink-theme', dark ? 'dark' : 'light'); } catch (err) { /* storage blocked */ }
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
