/* Local-only product demos and keyboard-accessible feature tabs. */
(function () {
  'use strict';
  var root = document.documentElement, scheme = matchMedia('(prefers-color-scheme: dark)');
  function syncArtTheme() {
    var dark = root.dataset.theme ? root.dataset.theme === 'dark' : root.classList.contains('dark') || (root.dataset.theme === undefined && getComputedStyle(root).colorScheme === 'dark');
    document.querySelectorAll('.h-art picture source').forEach(function (source) { source.media = dark ? 'all' : 'not all'; });
  }
  new MutationObserver(syncArtTheme).observe(root, {attributes:true, attributeFilter:['class','data-theme']});
  scheme.addEventListener('change', syncArtTheme);
  syncArtTheme();
  document.querySelectorAll('[data-connect-demo]').forEach(function (root) {
    root.querySelectorAll('[data-connect-app]').forEach(function (button) {
      button.addEventListener('click', function () {
        root.querySelectorAll('[data-connect-app]').forEach(function (other) { other.setAttribute('aria-pressed', String(other === button)); });
        root.querySelector('output').textContent = 'HOP ID → ' + button.dataset.connectApp + ' ✓';
      });
    });
  });
  // Scroll story: a tall section whose inner part sticks; the scroll position
  // picks the chapter, the buttons jump to a chapter, the keyboard works too.
  // Below 801 px or with reduced motion the chapters simply stack.
  var pinnedMedia = matchMedia('(min-width: 801px)'), reduced = matchMedia('(prefers-reduced-motion: reduce)');
  document.querySelectorAll('[data-scroll-story]').forEach(function (story) {
    var pin = story.querySelector('.h-story-pin'), tabs = Array.from(story.querySelectorAll('[data-story-step]')), panels = Array.from(story.querySelectorAll('[data-story-panel]'));
    var bar = story.querySelector('[data-story-progress]'), active = -1, frame = 0, n = panels.length;
    if (!pin || !n) return;
    var nav = story.querySelector('.h-story-nav'); if (nav) nav.setAttribute('role', 'tablist');
    function pinned() { return pinnedMedia.matches && !reduced.matches; }
    function select(i, byUser) {
      i = Math.max(0, Math.min(n - 1, i));
      tabs.forEach(function (t, k) { t.setAttribute('aria-selected', String(k === i)); t.tabIndex = k === i ? 0 : -1; });
      panels.forEach(function (p, k) { p.hidden = pinned() ? k !== i : false; p.classList.toggle('on', k === i); });
      if (bar) bar.style.width = ((i + 1) / n * 100) + '%';
      story.dataset.activeStep = String(i);
      if (active !== i) { active = i; story.dispatchEvent(new CustomEvent('hop-story', {detail: {index: i, byUser: !!byUser}})); }
    }
    function jump(i) {
      i = (i + n) % n;
      if (pinned()) {
        var top = story.getBoundingClientRect().top + scrollY, distance = story.offsetHeight - pin.offsetHeight;
        scrollTo({top: top - 72 + distance * (i + .5) / n, behavior: 'instant'});
      } else panels[i].scrollIntoView({block: 'start', behavior: 'smooth'});
      select(i, true);
    }
    tabs.forEach(function (t, i) {
      t.setAttribute('role', 'tab'); t.id = t.id || story.id + '-tab-' + i; panels[i].id = panels[i].id || story.id + '-panel-' + i;
      t.setAttribute('aria-controls', panels[i].id); panels[i].setAttribute('role', 'tabpanel'); panels[i].setAttribute('aria-labelledby', t.id);
      t.addEventListener('click', function () { jump(i); });
      t.addEventListener('keydown', function (e) {
        var next = i, rtl = document.documentElement.dir === 'rtl';
        if (e.key === 'ArrowRight') next += rtl ? -1 : 1; else if (e.key === 'ArrowLeft') next += rtl ? 1 : -1;
        else if (e.key === 'Home') next = 0; else if (e.key === 'End') next = n - 1; else return;
        e.preventDefault(); jump(next); tabs[active].focus();
      });
    });
    var prev = story.querySelector('[data-story-prev]'), next = story.querySelector('[data-story-next]');
    if (prev) prev.addEventListener('click', function () { jump(active - 1); });
    if (next) next.addEventListener('click', function () { jump(active + 1); });
    function update() {
      frame = 0;
      story.classList.toggle('is-pinned', pinned());
      if (!pinned()) { if (active < 0) select(0); return; }
      var distance = Math.max(1, story.offsetHeight - pin.offsetHeight);
      var k = Math.max(0, Math.min(.999, (72 - story.getBoundingClientRect().top) / distance));
      select(Math.floor(k * n));
    }
    function queue() { if (!frame) frame = requestAnimationFrame(update); }
    story.classList.add('is-enhanced');
    update();
    addEventListener('scroll', queue, {passive: true});
    addEventListener('resize', queue, {passive: true});
    reduced.addEventListener('change', queue); pinnedMedia.addEventListener('change', queue);
  });
  // Copy buttons for install lines and snippets.
  document.querySelectorAll('[data-copy-text]').forEach(function (b) {
    b.addEventListener('click', function () {
      var text = b.dataset.copyText || (document.querySelector(b.dataset.copyFrom) || {}).textContent || '';
      navigator.clipboard.writeText(text).then(function () { b.dataset.copied = 'true'; setTimeout(function () { delete b.dataset.copied; }, 1600); });
    });
  });
  document.querySelectorAll('[data-home-tabs]').forEach(function (root) {
    var tabs = Array.from(root.querySelectorAll('[role=tab]'));
    function select(tab) {
      tabs.forEach(function (t) {
        var active = t === tab;
        t.setAttribute('aria-selected', String(active)); t.tabIndex = active ? 0 : -1;
        document.getElementById(t.getAttribute('aria-controls')).hidden = !active;
      });
    }
    tabs.forEach(function (tab, i) {
      tab.addEventListener('click', function () { select(tab); });
      tab.addEventListener('keydown', function (e) {
        var next = i, rtl = document.documentElement.dir === 'rtl';
        if (e.key === 'ArrowRight') next += rtl ? -1 : 1;
        else if (e.key === 'ArrowLeft') next += rtl ? 1 : -1;
        else if (e.key === 'Home') next = 0;
        else if (e.key === 'End') next = tabs.length - 1;
        else return;
        e.preventDefault(); var t = tabs[(next + tabs.length) % tabs.length]; select(t); t.focus();
      });
    });
    if (tabs.length) select(tabs[0]);
  });
  document.querySelectorAll('[data-home-demo]').forEach(function (root) {
    var kind = root.dataset.homeDemo, output = root.querySelector('output');
    if (kind === 'linkhop') {
      var input = root.querySelector('input');
      function shorten() {
        try {
           var url = new URL(input.value); if (!/^https?:$/.test(url.protocol)) throw new Error('scheme');
          var slug = (url.hostname.replace(/^www\./, '').split('.')[0] + url.pathname).replace(/[^a-z0-9]+/gi, '-').slice(0,32).replace(/-$/, '');
          output.textContent = root.dataset.prefix + (slug || 'demo');
        } catch (_) { output.textContent = root.dataset.prefix + '…'; }
      }
      input.addEventListener('input', shorten); shorten();
    } else if (kind === 'biohop') {
      root.querySelectorAll('[data-theme-choice]').forEach(function (button) {
        button.addEventListener('click', function () {
          root.querySelector('.h-mini-bio').dataset.theme = button.dataset.themeChoice;
          root.querySelectorAll('[data-theme-choice]').forEach(function (b) { b.setAttribute('aria-pressed', String(b === button)); });
        });
      });
    } else if (kind === 'notehop') {
      var text = root.querySelector('textarea'), revision = 0;
      async function encrypt() {
        var current = ++revision;
        if (!crypto.subtle) { output.textContent = '—'; return; }
         try {
           var key = await crypto.subtle.generateKey({name:'AES-GCM',length:256},false,['encrypt']);
           var iv = crypto.getRandomValues(new Uint8Array(12));
           var encrypted = await crypto.subtle.encrypt({name:'AES-GCM',iv:iv},key,new TextEncoder().encode(text.value));
           if (current === revision) output.textContent = btoa(String.fromCharCode.apply(null,new Uint8Array(encrypted)));
         } catch (_) { if (current === revision) output.textContent = '—'; }
      }
      text.addEventListener('input', encrypt); encrypt();
    } else if (kind === 'pixelhop') {
      var image = root.querySelector('img'), drop = root.querySelector('[data-drop]'), button = root.querySelector('button');
       async function compress() {
         try {
           await image.decode(); var canvas = document.createElement('canvas');canvas.width=240;canvas.height=160;
           canvas.getContext('2d').drawImage(image,0,0,240,160);
           canvas.toBlob(function (blob) { output.textContent = blob ? root.dataset.prefix + 'demo · ' + (blob.type === 'image/webp' ? 'WebP' : 'PNG') + ' · ' + Math.ceil(blob.size/1024) + ' KB' : '—'; },'image/webp',.75);
         } catch (_) { output.textContent = '—'; }
      }
      image.addEventListener('dragstart',function(e){e.dataTransfer.setData('text/plain','sample');});
      drop.addEventListener('dragover',function(e){e.preventDefault();});
      drop.addEventListener('drop',function(e){e.preventDefault();compress();});
      button.addEventListener('click',compress);
    }
  });
})();
