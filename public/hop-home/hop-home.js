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
