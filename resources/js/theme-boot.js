/*
 * The family theme, read before first paint (the HOP preference contract): the cookie hop_theme, then this
 * app's old localStorage key, then the system setting. hop_theme is shared by every *.hel.ink app (Domain=.hel.ink),
 * host-only elsewhere (localhost, self-hosted). An old saved choice without the cookie is copied into it once.
 * window.hopSetTheme('light' | 'dark') is what every theme toggle calls: it writes the cookie and keeps the old key.
 *
 * Inlined by resources/views/partials/theme-boot.blade.php (a nonce'd inline script, no extra request); the old key
 * comes from the script tag's data-old-key, default helink-theme. Plain ES5: it runs before any bundle.
 */
(function (w, d) {
    var script = d.currentScript;
    var oldKey = (script && script.getAttribute('data-old-key')) || 'helink-theme';
    function write(value) {
        var domain = /(^|\.)hel\.ink$/.test(w.location.hostname) ? '; domain=.hel.ink; Secure' : '';
        d.cookie = 'hop_theme=' + value + '; Path=/; Max-Age=31536000; SameSite=Lax' + domain;
    }
    var match = /(?:^|;\s*)hop_theme=([^;]*)/.exec(d.cookie);
    var theme = match ? match[1] : null;
    if (theme === null) {
        var old = null;
        try { old = w.localStorage.getItem(oldKey); } catch (e) { /* storage blocked */ }
        if (old === 'light' || old === 'dark') {
            theme = old;
            write(old);
        }
    }
    w.hopSetTheme = function (value) {
        write(value);
        try { w.localStorage.setItem(oldKey, value); } catch (e) { /* storage blocked */ }
    };
    if (theme === 'dark' || (theme !== 'light' && w.matchMedia && w.matchMedia('(prefers-color-scheme: dark)').matches)) {
        d.documentElement.classList.add('dark');
    }
    // The family colour set (cookie hop_palette: classic | mist | sand), for the dashboard only: the layout turns it on
    // with a data-palette attribute on this script tag. Classic is no attribute at all; any other value, or no cookie, is classic. The cookie is
    // written like hop_theme, by script only; the server never reads it. It never touches the theme above.
    if (script && script.getAttribute('data-palette') === 'on') {
        var set = function (value) {
            if (value === 'mist' || value === 'sand') { d.documentElement.setAttribute('data-palette', value); }
            else { d.documentElement.removeAttribute('data-palette'); }
        };
        var saved = /(?:^|;\s*)hop_palette=([^;]*)/.exec(d.cookie);
        set(saved ? saved[1] : 'classic');
        w.hopSetPalette = function (value) {
            var choice = value === 'mist' || value === 'sand' ? value : 'classic';
            var domain = /(^|\.)hel\.ink$/.test(w.location.hostname) ? '; domain=.hel.ink; Secure' : '';
            d.cookie = 'hop_palette=' + choice + '; Path=/; Max-Age=31536000; SameSite=Lax' + domain;
            set(choice);
        };
    }
})(window, document);
