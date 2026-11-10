// Scroll motion for the home page, in the spirit of GSAP's ScrollTrigger but
// small and local (the CSP allows only this site's scripts):
//   [data-rv]          fades and rises in when it scrolls into view
//   [data-rv-group]    its children come in one after another
//   [data-split]       a heading whose words rise out of a mask
//   NHMotion.once(el, fn, at)  runs fn once when el's top passes `at` (share of the screen)
//   NHMotion.scrub(el, fn)     calls fn(0..1) while el scrolls through the screen
// v7 adds, for .hop-bento (see hop-bento.css) and any [data-rv-group]:
//   boxes of a .hop-bento fade and rise in 40 ms apart, once (a [data-rv-group] keeps its 80 ms);
//   [data-rv-load] on the group: its children come in on page load instead of waiting for the scroll (dashboards)
//   [data-spot]        a pointer spotlight: pointermove writes --mx / --my in px (fine pointers only)
//   [data-scene]       gets the class is-live only while it is on screen, so its own CSS/SVG scene runs only then
//   NHMotion.scan(root)  wires [data-scene] elements added later
// Nothing is hidden before this runs, and with "reduce motion" nothing moves:
// everything is simply there.
(function () {
  'use strict';
  var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
  var root = document.documentElement, onces = [], scrubs = [], ticking = false;

  // Words become masked spans; an <em> (the gradient phrase) moves as one piece.
  function split(h) {
    var i = 0;
    Array.prototype.slice.call(h.childNodes).forEach(function (n) {
      if (n.nodeType === 3) {
        var frag = document.createDocumentFragment();
        n.textContent.split(/(\s+)/).forEach(function (w) {
          if (!w) return;
          if (/^\s+$/.test(w)) return frag.appendChild(document.createTextNode(w));
          frag.appendChild(word(document.createTextNode(w), i++));
        });
        h.replaceChild(frag, n);
      } else if (n.nodeType === 1) {
        var box = word(null, i++);
        h.replaceChild(box, n);
        box.firstChild.appendChild(n);
      }
    });
  }
  function word(content, i) {
    var mask = document.createElement('span'), inner = document.createElement('span');
    mask.className = 'mw';
    inner.className = 'mi';
    inner.style.transitionDelay = (i * 55) + 'ms';
    if (content) inner.appendChild(content);
    mask.appendChild(inner);
    return mask;
  }

  function inView(el, at) { var r = el.getBoundingClientRect(); return r.top < innerHeight * (at || .88) && r.bottom > 0; }

  function tick() {
    ticking = false;
    var ready = onces.map(function (o) { return inView(o.el, o.at); });
    for (var i = onces.length - 1; i >= 0; i--) {
      if (ready[i]) { var o = onces.splice(i, 1)[0]; o.fn(); }
    }
    scrubs.forEach(function (s) {
      var r = s.el.getBoundingClientRect(), total = r.height - innerHeight;
      var p = total > 0 ? -r.top / total : (innerHeight - r.top) / (innerHeight + r.height);
      p = Math.min(1, Math.max(0, p));
      if (p !== s.p) { s.p = p; s.fn(p); }
    });
  }
  function schedule() { if (!ticking) { ticking = true; requestAnimationFrame(tick); } }

  var api = {
    reduce: reduce,
    once: function (el, fn, at) { if (!el) return; if (reduce) return fn(); onces.push({ el: el, fn: fn, at: at }); schedule(); },
    scrub: function (el, fn) { if (!el) return; var s = { el: el, fn: fn, p: -1 }; scrubs.push(s); if (reduce) return fn(1); schedule(); },
    // types `text` into el, one letter at a time; done() after
    type: function (el, text, ms, done) {
      if (reduce) { el.textContent = text; return done && done(); }
      var n = 0;
      var t = setInterval(function () { el.textContent = text.slice(0, ++n); if (n >= text.length) { clearInterval(t); if (done) done(); } }, ms || 35);
      return t;
    },
    // counts el up to n
    count: function (el, n, fmt) {
      fmt = fmt || function (v) { return v.toLocaleString(); };
      if (reduce) { el.textContent = fmt(n); return; }
      var t0 = performance.now();
      (function step(now) {
        var k = Math.min(1, Math.max(0, (now - t0) / 1100)); // a frame time can precede t0: never count below zero
        el.textContent = fmt(Math.round(n * (1 - Math.pow(1 - k, 3))));
        if (k < 1) requestAnimationFrame(step);
      })(t0);
    }
  };
  // [data-scene] is live only while on screen (the resting style of a scene is its final frame).
  var sceneObserver = null;
  function scan(scope) {
    if (reduce || typeof IntersectionObserver === 'undefined') return;
    if (!sceneObserver) sceneObserver = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) { e.target.classList.toggle('is-live', e.isIntersecting); });
    }, { threshold: .15 });
    Array.prototype.forEach.call((scope || document).querySelectorAll('[data-scene]'), function (el) {
      if (el.hasAttribute('data-scene-wired')) return;
      el.setAttribute('data-scene-wired', '');
      sceneObserver.observe(el);
    });
  }
  api.scan = scan;
  window.HopMotion = window.NHMotion = api;
  if (reduce) return;

  // Pointer spotlight on [data-spot] boxes: one delegated listener, one write per frame.
  if (matchMedia('(hover: hover) and (pointer: fine)').matches) {
    var spotEl = null, spotX = 0, spotY = 0, spotQueued = false;
    document.addEventListener('pointermove', function (e) {
      var el = e.target && e.target.closest ? e.target.closest('[data-spot]') : null;
      if (!el) return;
      spotEl = el; spotX = e.clientX; spotY = e.clientY;
      if (spotQueued) return;
      spotQueued = true;
      requestAnimationFrame(function () {
        spotQueued = false;
        var r = spotEl.getBoundingClientRect();
        spotEl.style.setProperty('--mx', Math.round(spotX - r.left) + 'px');
        spotEl.style.setProperty('--my', Math.round(spotY - r.top) + 'px');
      });
    }, { passive: true });
  }
  scan(document);

  document.querySelectorAll('[data-split]').forEach(split);
  var onLoad = [];
  document.querySelectorAll('[data-rv-group], .hop-bento').forEach(function (g) {
    var step = g.classList.contains('hop-bento') ? 40 : 80, load = g.hasAttribute('data-rv-load');
    Array.prototype.forEach.call(g.children, function (c, i) {
      c.setAttribute('data-rv', ''); c.style.transitionDelay = (i * step) + 'ms';
      if (load) onLoad.push(c);
    });
  });
  var targets = document.querySelectorAll('[data-rv], [data-split]');
  // What is already on screen shows at once, without a jump; the rest waits. Boxes of a [data-rv-load] group
  // come in on load, staggered, wherever they are.
  Array.from(targets).filter(function (el) { return onLoad.indexOf(el) === -1 && inView(el); }).forEach(function (el) { el.classList.add('in'); });
  root.classList.add('mo');
  if (onLoad.length) requestAnimationFrame(function () { onLoad.forEach(function (el) { el.classList.add('in'); }); });
  targets.forEach(function (el) { if (!el.classList.contains('in') && onLoad.indexOf(el) === -1) api.once(el, function () { el.classList.add('in'); }); });
  addEventListener('scroll', schedule, { passive: true });
  addEventListener('resize', schedule);
})();
