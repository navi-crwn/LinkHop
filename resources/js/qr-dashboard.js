/* The dashboard's QR codes page. The codes are drawn in the browser with the vendored qr-code-styling (loaded by the
 * layout); what goes into a code comes from payloads.js (the same pure functions the public /qr page uses). Nothing is
 * sent to the server until "Save": then one JSON request carries the content, the look and a label. The saved codes
 * arrive in #qc-data (the page is no-store and shown only to its owner). */
import * as P from './payloads.js';
import { qrStyleOptions } from './options.js';

function main() {
  var dataEl = document.getElementById('qc-data');
  if (!dataEl) return;
  var D = JSON.parse(dataEl.textContent);
  var T = D.t;
  var $ = function (id) { return document.getElementById(id); };
  var DEFAULT = { fg: '#000000', bg: '#ffffff', dots: 'square', corners: 'square', logo: '', logoSize: 22 };
  var tab = 'wifi';
  var timer = 0;
  var current = null; // { payload, style } of what the form shows, or null while it is empty

  function t(key, vars) {
    var s = T[key] != null ? T[key] : key;
    return vars ? s.replace(/:(\w+)/g, function (m, k) { return vars[k] != null ? vars[k] : m; }) : s;
  }

  /** Draws a code with the quiet zone of four squares (the first pass gives the module count). */
  function draw(target, style, payload, size, type) {
    var s = Object.assign({}, DEFAULT, style);
    var guess = Math.round(size * 0.09);
    var inst = new window.QRCodeStyling(qrStyleOptions(s, type || 'canvas', size, payload, guess));
    var n = inst._qr && inst._qr.getModuleCount ? inst._qr.getModuleCount() : 0;
    if (n) {
      var m = Math.round(4 * size / (n + 8));
      if (Math.abs(m - guess) > 1) inst.update({ margin: m });
    }
    if (target) { target.textContent = ''; inst.append(target); fit(target); }
    return inst;
  }
  /** The library writes fixed-size canvases: let them fill their box. */
  function fit(target) {
    var el = target.querySelector('canvas, svg');
    if (el) { el.style.width = '100%'; el.style.height = 'auto'; el.style.display = 'block'; }
  }
  function byId(id) { return D.saved.filter(function (x) { return x.id === id; })[0]; }
  function fileName(item, ext) {
    var base = String(item.label || item.type).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 40) || 'qr';
    return 'qr-' + base + '.' + ext;
  }
  async function download(item, ext) {
    var inst = draw(null, item.style, item.payload, 1024, ext === 'svg' ? 'svg' : 'canvas');
    var blob = await inst.getRawData(ext);
    var a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = fileName(item, ext);
    document.body.appendChild(a); a.click(); a.remove(); setTimeout(function () { URL.revokeObjectURL(a.href); }, 2000);
  }

  /* ------------------------------------------------------- the saved codes */
  D.saved.forEach(function (item) {
    var box = document.querySelector('[data-qc-thumb="' + item.id + '"]');
    if (box) { try { draw(box, item.style, item.payload, 192); } catch (e) { box.textContent = ''; } }
  });
  document.querySelectorAll('[data-qc-dl]').forEach(function (b) {
    b.addEventListener('click', function () { var item = byId(b.dataset.qcId); if (item) download(item, b.dataset.qcDl); });
  });
  var view = $('qc-view'), viewing = null;
  document.querySelectorAll('[data-qc-view]').forEach(function (b) {
    b.addEventListener('click', function () {
      viewing = byId(b.dataset.qcView); if (!viewing) return;
      $('qc-view-title').textContent = viewing.label;
      draw($('qc-view-canvas'), viewing.style, viewing.payload, 640);
      if (view.showModal) view.showModal(); else view.setAttribute('open', '');
    });
  });
  function closeView() { if (view.close) view.close(); else view.removeAttribute('open'); }
  $('qc-view-close').addEventListener('click', closeView);
  view.addEventListener('click', function (e) { if (e.target === view) closeView(); });
  $('qc-view-png').addEventListener('click', function () { if (viewing) download(viewing, 'png'); });
  $('qc-view-svg').addEventListener('click', function () { if (viewing) download(viewing, 'svg'); });

  /* ------------------------------------------------------------ the creator */
  var creator = $('qc-creator'), openBtn = $('qc-open');
  function setOpen(on) {
    creator.hidden = !on;
    openBtn.setAttribute('aria-expanded', String(on));
    if (on) { render(); var first = creator.querySelector('input, textarea'); if (first) first.focus(); }
  }
  openBtn.addEventListener('click', function () { setOpen(creator.hidden); });
  $('qc-cancel').addEventListener('click', function () { setOpen(false); openBtn.focus(); });
  creator.addEventListener('keydown', function (e) { if (e.key === 'Escape') { setOpen(false); openBtn.focus(); e.stopPropagation(); } });

  function setTab(next) {
    tab = next;
    ['wifi', 'vcard', 'text'].forEach(function (k) {
      var on = k === tab;
      var b = $('qc-tab-' + k);
      b.setAttribute('aria-selected', String(on)); b.tabIndex = on ? 0 : -1;
      ['bg-slate-900/5', 'text-ink', 'dark:bg-white/10'].forEach(function (c) { b.classList.toggle(c, on); });
      ['text-muted', 'hover:text-ink'].forEach(function (c) { b.classList.toggle(c, !on); });
      $('qc-panel-' + k).hidden = !on;
    });
    render();
  }
  document.querySelectorAll('[data-qc-tab]').forEach(function (b) {
    b.addEventListener('click', function () { setTab(b.dataset.qcTab); });
  });

  function val(id) { return $(id).value; }
  function build() {
    if (tab === 'wifi') return P.buildWifi({ ssid: val('qc-w-ssid'), password: val('qc-w-pass'), security: val('qc-w-sec'), hidden: $('qc-w-hidden').checked });
    if (tab === 'vcard') return P.buildVcard({ firstName: val('qc-v-first'), lastName: val('qc-v-last'), org: val('qc-v-org'), title: val('qc-v-title'),
      mobile: val('qc-v-mobile'), work: '', email: val('qc-v-email'), url: val('qc-v-url'), street: '', city: '', region: '', postal: '', country: '', note: '' });
    return P.buildText({ text: val('qc-t-text') });
  }
  function style() { return { fg: val('qc-s-fg'), bg: val('qc-s-bg'), dots: val('qc-s-dots'), corners: val('qc-s-corners') }; }
  function suggestedLabel() {
    if (tab === 'wifi') return val('qc-w-ssid').trim();
    if (tab === 'vcard') return (val('qc-v-first') + ' ' + val('qc-v-last')).trim() || val('qc-v-org').trim();
    return val('qc-t-text').trim().slice(0, 40);
  }

  function render() { clearTimeout(timer); timer = setTimeout(renderNow, 40); }
  function renderNow() {
    if (creator.hidden) return;
    $('qc-w-pass-field').hidden = val('qc-w-sec') === 'nopass';
    var b = build(), box = $('qc-preview'), empty = $('qc-empty');
    if (!b.payload) {
      current = null; box.textContent = ''; box.hidden = true; empty.hidden = false;
      empty.textContent = b.errors.length && b.errors[0] !== 'payload_too_long' ? t('m_' + b.errors[0]) : t('empty');
      $('qc-save').disabled = true;
      return;
    }
    try { draw(box, style(), b.payload, 480); } catch (e) {
      current = null; box.textContent = ''; box.hidden = true; empty.hidden = false; empty.textContent = t('m_oversize'); $('qc-save').disabled = true; return;
    }
    box.hidden = false; empty.hidden = true;
    current = { payload: b.payload, style: style() };
    $('qc-save').disabled = false;
  }
  creator.querySelectorAll('input, select, textarea').forEach(function (el) {
    el.addEventListener('input', render); el.addEventListener('change', render);
  });

  $('qc-save').addEventListener('click', async function () {
    if (!current) return;
    var btn = this, err = $('qc-error');
    err.hidden = true; btn.disabled = true;
    var label = val('qc-label').trim() || suggestedLabel();
    try {
      var res = await fetch(D.storeUrl, {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') || {}).content || '' },
        body: JSON.stringify({ type: tab, label: label.slice(0, 80), payload: current.payload, style: current.style })
      });
      if (res.ok) { window.location.reload(); return; }
      var msg = '';
      try { var j = await res.json(); msg = j.message || ''; if (j.errors) { msg = Object.values(j.errors)[0][0] || msg; } } catch (e) {}
      err.textContent = msg || t('saving_failed', { status: res.status });
    } catch (e) {
      err.textContent = t('saving_failed', { status: 0 });
    }
    err.hidden = false; btn.disabled = false;
  });
}
// Deferred scripts run in order and this module comes first: wait for DOMContentLoaded, when the vendored qr-code-styling has run.
if (document.readyState === 'complete') main(); else document.addEventListener('DOMContentLoaded', main);
