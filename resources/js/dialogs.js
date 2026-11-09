// Questions and messages for the dashboard, in the HOP family's own dialogs (hop-assets hop-ui.js, window.HopUI)
// when that script is on the page, and in the browser's dialogs when it is not: the layout only loads it once the
// hub's manifest lists it, so every caller has to work either way.
//
//   await hopDialog.confirm({ title, message, danger, subject, anchor })  → true / false
//   await hopDialog.alert({ title, message })
//   await hopDialog.prompt({ title, message, label, value, type })        → the text, or null when cancelled
//
// `subject` names what a delete removes (the red button reads: Delete “subject”); `anchor` is the control that
// asked, so the dialog opens from it.

const shared = () => (window.HopUI && typeof window.HopUI.confirm === 'function' ? window.HopUI : null);

// The browser's dialogs take one string: the title, what is being removed, then the explanation.
const plain = (options) => [[options.title, options.subject].filter(Boolean).join(' '), options.message].filter(Boolean).join('\n\n');

const normalise = (options) => (typeof options === 'string' ? { title: options } : { ...(options || {}) });

export const hopConfirm = (options) => {
    const o = normalise(options);

    return shared() ? shared().confirm(o) : Promise.resolve(window.confirm(plain(o)));
};

export const hopAlert = (options) => {
    const o = normalise(options);

    return shared() ? shared().alert(o) : Promise.resolve(window.alert(plain(o)));
};

export const hopPrompt = (options) => {
    const o = normalise(options);

    return shared() ? shared().prompt(o) : Promise.resolve(window.prompt(plain(o) || o.label || '', o.value || ''));
};

window.hopDialog = { confirm: hopConfirm, alert: hopAlert, prompt: hopPrompt };
