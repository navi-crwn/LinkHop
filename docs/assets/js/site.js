// HEL.ink website: language, theme, copy buttons, nav shadow, reveal on scroll.
// The language and theme are picked before paint by the inline script in <head>.
(function () {
  var root = document.documentElement;

  function save(k, v) { try { localStorage.setItem(k, v); } catch (e) {} }

  // language
  var en = document.getElementById('lang-en'), id = document.getElementById('lang-id');
  function setLang(l, keep) {
    root.lang = l;
    en.setAttribute('aria-pressed', l === 'en');
    id.setAttribute('aria-pressed', l === 'id');
    document.title = l === 'id' ? 'HEL.ink: pemendek link & link in bio open source' : 'HEL.ink: open-source link shortener & link in bio';
    if (keep) save('lang', l);
  }
  en.addEventListener('click', function () { setLang('en', true); });
  id.addEventListener('click', function () { setLang('id', true); });
  setLang(root.lang === 'id' ? 'id' : 'en', false);

  // theme: follows the system until the toggle is used
  document.getElementById('theme').addEventListener('click', function () {
    var cur = root.getAttribute('data-theme') ||
      (matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark');
    var next = cur === 'light' ? 'dark' : 'light';
    root.setAttribute('data-theme', next);
    save('theme', next);
  });

  // copy buttons
  document.querySelectorAll('.copy').forEach(function (b) {
    b.addEventListener('click', function () {
      var src = b.dataset.copy ? document.getElementById(b.dataset.copy) : b.parentElement.querySelector('code');
      var text = src.innerText.trim();
      var done = function () { var t = b.textContent; b.textContent = root.lang === 'id' ? 'Tersalin' : 'Copied'; setTimeout(function () { b.textContent = t; }, 1400); };
      if (navigator.clipboard) navigator.clipboard.writeText(text).then(done, function () {}); else done();
    });
  });

  // nav gets a shadow once the page scrolls
  var nav = document.querySelector('.nav');
  function onScroll() { nav.classList.toggle('solid', scrollY > 24); }
  addEventListener('scroll', onScroll, { passive: true }); onScroll();

  // reveal on scroll
  var items = document.querySelectorAll('.rv');
  if ('IntersectionObserver' in window && !root.classList.contains('shot')) {
    var io = new IntersectionObserver(function (es) {
      es.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } });
    }, { rootMargin: '0px 0px -8% 0px' });
    items.forEach(function (el) { io.observe(el); });
  } else items.forEach(function (el) { el.classList.add('in'); });

  // bento spotlight follows the pointer
  document.querySelectorAll('.feats>div').forEach(function (c) {
    c.addEventListener('pointermove', function (e) {
      var r = c.getBoundingClientRect();
      c.style.setProperty('--mx', (e.clientX - r.left) + 'px');
      c.style.setProperty('--my', (e.clientY - r.top) + 'px');
    });
  });

  // latest release / version from GitHub, if reachable (the page works without it)
  fetch('https://api.github.com/repos/navi-crwn/hel.ink/releases/latest').then(function (r) { return r.ok ? r.json() : null; }).then(function (j) {
    if (!j || !j.tag_name) return;
    document.querySelectorAll('[data-gh=version]').forEach(function (el) { el.textContent = j.tag_name; });
  }).catch(function () {});
})();
