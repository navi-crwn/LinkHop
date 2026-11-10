/* The guest QR page. Everything runs in the browser: after the page loads no request is made.
 * The text that goes into the code comes from payloads.js (pure functions). The page texts come from the server
 * (already in the visitor's language) as JSON in #qr-i18n. */
import * as P from './payloads.js';
import { CARD, LANG_ORDER } from './card-text.js';
import { qrStyleOptions } from './options.js';

function main() {
  'use strict';
  var T = JSON.parse(document.getElementById('qr-i18n').textContent);
  var LANG = document.documentElement.lang || 'en';
  var NF = {};
  var $ = function (id) { return document.getElementById(id); };
  var q = new URLSearchParams(location.search);

  var SECLABEL = { WPA: 'WPA/WPA2/WPA3', WEP: 'WEP', SAE: 'WPA3 (SAE)' };
  var DEST = [
    { k: 'd1', url: 'https://example.com/menu/lunch' },
    { k: 'd2', url: 'https://example.com/menu/dinner' },
    { k: 'd3', url: 'https://example.com/closed-for-the-holiday' }
  ];
  var PRESETS = [['#000000', '#ffffff'], ['#1e1b4b', '#ffffff'], ['#064e3b', '#ecfdf5'], ['#7f1d1d', '#fef2f2'], ['#111827', '#fef3c7']];
  var DEFAULT_STYLE = { fg: '#000000', bg: '#ffffff', dots: 'square', corners: 'square', logo: '', logoSize: 22 };

  var state = {
    tab: ['wifi', 'vcard', 'text'].indexOf(q.get('tab')) >= 0 ? q.get('tab') : 'wifi',
    style: Object.assign({}, DEFAULT_STYLE),
    paper: q.get('paper') === 'A5' ? 'A5' : 'A6',
    cardLang: CARD[q.get('card')] ? q.get('card') : 'en',
    titleTouched: false
  };

  function t(key, vars) {
    var s = T[key] != null ? T[key] : key;
    return vars ? s.replace(/\{(\w+)\}/g, function (_, k) { return vars[k] != null ? vars[k] : ''; }) : s;
  }
  function num(n, d) {
    d = d == null ? 1 : d;
    try { return (NF[d] = NF[d] || new Intl.NumberFormat(LANG, { minimumFractionDigits: d, maximumFractionDigits: d })).format(n); }
    catch (e) { return Number(n).toFixed(d); }
  }

  function applyI18n() {
    buildDest();
    if (!state.titleTouched) $('c-title').value = CARD[state.cardLang].title;
  }

  /* ------------------------------------------------------------------ tabs */
  function setTab(tab) {
    state.tab = tab;
    ['wifi', 'vcard', 'text'].forEach(function (k) {
      var on = k === tab;
      $('tab-' + k).setAttribute('aria-selected', String(on)); $('tab-' + k).tabIndex = on ? 0 : -1;
      $('panel-' + k).hidden = !on;
    });
    $('card-sec').hidden = tab !== 'wifi';
    render();
  }
  document.querySelectorAll('.tab').forEach(function (b) {
    b.addEventListener('click', function () { setTab(b.dataset.tab); });
    b.addEventListener('keydown', function (e) {
      var order = ['wifi', 'vcard', 'text'], i = order.indexOf(b.dataset.tab);
      if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
        var n = order[(i + (e.key === 'ArrowRight' ? 1 : 2)) % 3]; setTab(n); $('tab-' + n).focus(); e.preventDefault();
      }
    });
  });

  /* --------------------------------------------------------------- reading */
  function val(id) { return $(id).value; }
  function build() {
    if (state.tab === 'wifi') return P.buildWifi({ ssid: val('w-ssid'), password: val('w-pass'), security: val('w-sec'), hidden: $('w-hidden').checked });
    if (state.tab === 'vcard') return P.buildVcard({
      firstName: val('v-first'), lastName: val('v-last'), org: val('v-org'), title: val('v-title'), mobile: val('v-mobile'), work: val('v-work'),
      email: val('v-email'), url: val('v-url'), street: val('v-street'), city: val('v-city'), region: val('v-region'), postal: val('v-postal'),
      country: val('v-country'), note: val('v-note')
    });
    return P.buildText({ text: val('t-text') });
  }

  /* ------------------------------------------------------------- rendering */
  var mainQr = null, timer = 0, lastModules = 0;
  var SIZE = 576;

  function qrOptions(type, size, payload, margin) { return qrStyleOptions(state.style, type, size, payload, margin); }
  /** Draws with the quiet zone of four squares: needs the module count, which the first pass gives. */
  function drawQr(inst, type, size, payload) {
    var guess = Math.round(size * 0.09);
    var o = qrOptions(type, size, payload, guess);
    if (!inst) inst = new window.QRCodeStyling(o); else inst.update(o);
    var n = inst._qr && inst._qr.getModuleCount ? inst._qr.getModuleCount() : 0;
    if (n) {
      var m = Math.round(4 * size / (n + 8));
      if (Math.abs(m - guess) > 1) inst.update({ margin: m });
    }
    return { inst: inst, modules: n };
  }

  function advice(items) {
    var ul = $('advice'); ul.textContent = '';
    items.forEach(function (it) {
      var li = document.createElement('li'); li.className = it.level;
      var dot = document.createElement('span'); dot.className = 'dot'; dot.setAttribute('aria-hidden', 'true');
      var tx = document.createElement('span'); tx.className = 'num'; tx.textContent = it.text;
      li.appendChild(dot); li.appendChild(tx); ul.appendChild(li);
    });
  }

  function render() {
    clearTimeout(timer);
    timer = setTimeout(renderNow, 40);
  }
  function renderNow() {
    var b = build();
    var items = [];
    var s = state.style;
    if ($('w-sec')) $('w-pass-field').hidden = val('w-sec') === 'nopass';
    if (state.tab === 'text') $('t-count').textContent = String(P.utf8Length(val('t-text')));
    var contrast = P.scanAdvice(s.fg, s.bg);
    $('s-fg-hex').setAttribute('aria-invalid', String(!P.contrastRatio(s.fg, '#ffffff')));
    $('payload').textContent = b.payload;
    b.errors.forEach(function (c) { if (c !== 'payload_too_long') items.push({ level: 'warn', text: t('m_' + c) }); else items.push({ level: 'bad', text: t('m_payload_too_long') }); });

    if (!b.payload) {
      $('qr-out').hidden = true; $('qr-empty').hidden = false;
      $('qr-empty').textContent = b.errors.length && b.errors[0] !== 'payload_too_long' ? t('m_' + b.errors[0]) : t('empty');
      $('qr-meta').textContent = ''; $('dl-png').disabled = $('dl-svg').disabled = true;
      advice(items.length ? items : [{ level: 'warn', text: t('empty') }]);
      renderCard(null);
      return;
    }
    $('dl-png').disabled = $('dl-svg').disabled = false;
    var res;
    try { res = drawQr(mainQr, 'canvas', SIZE, b.payload); mainQr = res.inst; }
    catch (e) {
      $('qr-out').hidden = true; $('qr-empty').hidden = false; $('qr-empty').textContent = t('m_oversize');
      advice([{ level: 'bad', text: t('m_oversize') }]); return;
    }
    if (!$('qr-out').firstChild) mainQr.append($('qr-out'));
    $('qr-out').hidden = false; $('qr-empty').hidden = true;
    lastModules = res.modules;
    var ver = res.modules ? (res.modules - 17) / 4 : 0;
    $('qr-meta').textContent = res.modules ? 'v' + ver + ' · ' + res.modules + '×' + res.modules + ' · ' + b.bytes + ' B' : '';

    // advice
    var cr = contrast.ratio == null ? '' : num(contrast.ratio, 1);
    if (contrast.level === 'ok') items.push({ level: 'ok', text: t('a_contrast_ok', { r: cr }) });
    contrast.codes.forEach(function (c) { items.push({ level: c === 'inverted' || c === 'contrast_low' ? 'warn' : 'bad', text: t('m_' + c, { r: cr }) }); });
    b.warnings.forEach(function (c) { items.push({ level: 'warn', text: t('m_' + c) }); });
    if (state.tab === 'wifi' && val('w-sec') === 'SAE') items.push({ level: 'warn', text: t('m_sae') });
    if (['dots', 'classy', 'classy-rounded'].indexOf(s.dots) >= 0) items.push({ level: 'warn', text: t('m_dots_style') });
    if (s.logo) {
      items.push({ level: 'ok', text: t('a_logo_ecc') });
      if (s.logoSize > 25) items.push({ level: 'warn', text: t('m_logo_big') });
      if (b.bytes > 600) items.push({ level: 'warn', text: t('m_logo_dense') });
    }
    if (res.modules) items.push({ level: 'ok', text: t('a_print', { mm: Math.ceil(0.4 * (res.modules + 8)) }) });
    advice(items);
    renderCard(b);
  }

  /* ------------------------------------------------------------ table card */
  var WIFI_ICON = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 9a14 14 0 0 1 19 0M5.5 12.5a9.5 9.5 0 0 1 13 0M8.6 16a5 5 0 0 1 6.8 0"/><circle cx="12" cy="19.3" r="1" fill="currentColor"/></svg>';
  function div(cls, text) { var d = document.createElement('div'); if (cls) d.className = cls; if (text != null) d.textContent = text; return d; }

  function renderCard(b) {
    var paper = $('paper'); paper.textContent = ''; paper.dataset.paper = state.paper;
    var inner = div('p-in'); paper.appendChild(inner);
    var note = $('paper-note'); note.textContent = ''; note.className = 'paper-note muted';
    if (state.tab !== 'wifi') return;
    var wifiB = b || P.buildWifi({ ssid: val('w-ssid'), password: val('w-pass'), security: val('w-sec'), hidden: $('w-hidden').checked });
    var L = CARD[state.cardLang], sec = val('w-sec');
    if (!wifiB.payload) { note.textContent = t('m_card_nowifi'); return; }
    var head = div('p-head'); head.innerHTML = WIFI_ICON; head.appendChild(div('p-title', val('c-title') || L.title));
    inner.appendChild(head);
    inner.appendChild(div('p-join', L.join));
    var qrBox = div('p-qr'); inner.appendChild(qrBox);
    var dl = document.createElement('dl');
    function row(k, v) { var w = document.createElement('div'); var dt = document.createElement('dt'); dt.textContent = k; var dd = document.createElement('dd'); dd.textContent = v; w.appendChild(dt); w.appendChild(dd); dl.appendChild(w); }
    row(L.net, val('w-ssid'));
    if (sec === 'nopass') row(L.pw, L.none); else if ($('c-showpw').checked) row(L.pw, val('w-pass'));
    row(L.sec, sec === 'nopass' ? L.none : SECLABEL[sec]);
    inner.appendChild(dl);
    var foot = div('p-foot'); foot.appendChild(div('', L.hint));
    if ($('c-footer').checked) foot.appendChild(div('url', 'link.hel.ink/qr'));
    inner.appendChild(foot);
    // the code on the card is vector, so it stays sharp at any paper size
    var r = drawQr(null, 'svg', 600, wifiB.payload);
    r.inst.append(qrBox);
    var svg = qrBox.querySelector('svg'); if (svg) { if (!svg.getAttribute('viewBox')) svg.setAttribute('viewBox', '0 0 600 600'); svg.removeAttribute('width'); svg.removeAttribute('height'); }
    // size check: the code is 54% of the paper width
    var widthMm = state.paper === 'A5' ? 148 : 105, codeMm = widthMm * 0.54;
    var mm = P.moduleMm(codeMm, r.modules); // the drawing includes a four-square quiet zone
    if (mm) {
      note.textContent = t(mm < 0.4 ? 'm_card_small' : 'm_card_squares', { mm: num(mm, 2) });
      note.className = 'paper-note ' + (mm < 0.4 ? 'warn' : 'muted');
    }
  }

  function setPageRule() {
    var style = $('page-rule');
    if (!style) { style = document.createElement('style'); style.id = 'page-rule'; document.head.appendChild(style); }
    var sheet = style.sheet; if (!sheet) return;
    while (sheet.cssRules.length) sheet.deleteRule(0);
    // Chrome does not know the name A6, so the size is given in millimetres
    sheet.insertRule('@page { size: ' + (state.paper === 'A5' ? '148mm 210mm' : '105mm 148mm') + '; margin: 0; }', 0);
  }
  /** A cloned SVG would share clip-path ids with the original, which is hidden while printing: the code then prints as a black square. */
  function uniqueIds(root, suffix) {
    var map = {};
    root.querySelectorAll('[id]').forEach(function (el) { map[el.id] = el.id + suffix; el.id = el.id + suffix; });
    root.querySelectorAll('*').forEach(function (el) {
      Array.prototype.forEach.call(el.attributes, function (a) {
        var v = a.value.replace(/url\(\s*(['"]?)#([^)'"]+)\1\s*\)/g, function (m, qt, id) { return map[id] ? 'url(' + qt + '#' + map[id] + qt + ')' : m; });
        if ((a.name === 'href' || a.name === 'xlink:href') && v.charAt(0) === '#' && map[v.slice(1)]) v = '#' + map[v.slice(1)];
        if (v !== a.value) el.setAttribute(a.name, v);
      });
    });
  }
  function printRoot() {
    var root = document.getElementById('print-root');
    if (!root) { root = document.createElement('div'); root.id = 'print-root'; root.className = 'qrs print-root'; root.setAttribute('aria-hidden', 'true'); document.body.appendChild(root); }
    return root;
  }
  window.addEventListener('beforeprint', function () {
    setPageRule();
    var root = printRoot(); root.textContent = '';
    var clone = $('paper').cloneNode(true); clone.removeAttribute('id'); uniqueIds(clone, '-print'); root.appendChild(clone);
  });
  window.addEventListener('afterprint', function () { printRoot().textContent = ''; });
  $('print-btn').addEventListener('click', function () { window.print(); });

  /* ---------------------------------------------------------------- inputs */
  document.querySelectorAll('#panel-wifi input, #panel-wifi select, #panel-vcard input, #panel-vcard textarea, #panel-text textarea, #c-showpw, #c-footer').forEach(function (el) {
    el.addEventListener('input', render); el.addEventListener('change', render);
  });
  $('c-title').addEventListener('input', function () { state.titleTouched = true; render(); });

  // card language and paper
  LANG_ORDER.forEach(function (code) { var o = document.createElement('option'); o.value = code; o.textContent = CARD[code].name; $('c-lang').appendChild(o); });
  $('c-lang').value = state.cardLang;
  $('c-lang').addEventListener('change', function () { state.cardLang = this.value; if (!state.titleTouched) $('c-title').value = CARD[state.cardLang].title; render(); });
  document.querySelectorAll('[data-paper]').forEach(function (b) {
    if (b.tagName !== 'BUTTON') return;
    b.addEventListener('click', function () {
      state.paper = b.dataset.paper;
      document.querySelectorAll('.seg button').forEach(function (x) { x.setAttribute('aria-pressed', String(x === b)); });
      setPageRule(); render();
    });
  });

  // style
  function setStyle(patch) { Object.assign(state.style, patch); syncStyleInputs(); render(); }
  function syncStyleInputs() {
    var s = state.style;
    if (P.contrastRatio(s.fg, '#ffffff') !== null) $('s-fg').value = s.fg;
    if (P.contrastRatio(s.bg, '#ffffff') !== null) $('s-bg').value = s.bg;
    $('s-fg-hex').value = s.fg; $('s-bg-hex').value = s.bg;
    $('s-dots').value = s.dots; $('s-corners').value = s.corners;
    $('logo-thumb').hidden = $('logo-del').hidden = $('logo-size-wrap').hidden = !s.logo;
    if (s.logo) $('logo-thumb').src = s.logo;
    $('logo-size').value = s.logoSize; $('logo-size-v').textContent = s.logoSize + '%';
    document.querySelectorAll('.swatch').forEach(function (b) { b.setAttribute('aria-pressed', String(b.dataset.fg === s.fg && b.dataset.bg === s.bg)); });
  }
  function hexOk(v) { return P.contrastRatio(v, '#ffffff') !== null; }
  function norm(v) { v = v.trim(); if (v && v[0] !== '#') v = '#' + v; return v.toLowerCase(); }
  $('s-fg').addEventListener('input', function () { setStyle({ fg: this.value }); });
  $('s-bg').addEventListener('input', function () { setStyle({ bg: this.value }); });
  $('s-fg-hex').addEventListener('input', function () { var v = norm(this.value); state.style.fg = v; if (hexOk(v)) { $('s-fg').value = v; } render(); });
  $('s-bg-hex').addEventListener('input', function () { var v = norm(this.value); state.style.bg = v; if (hexOk(v)) { $('s-bg').value = v; } render(); });
  $('s-dots').addEventListener('change', function () { setStyle({ dots: this.value }); });
  $('s-corners').addEventListener('change', function () { setStyle({ corners: this.value }); });
  $('logo-size').addEventListener('input', function () { setStyle({ logoSize: Number(this.value) }); });
  $('logo-add').addEventListener('click', function () { $('logo-file').click(); });
  $('logo-del').addEventListener('click', function () { $('logo-file').value = ''; setStyle({ logo: '' }); });
  $('logo-file').addEventListener('change', function () {
    var f = this.files && this.files[0]; if (!f) return;
    if (f.size > 512 * 1024) { advice([{ level: 'bad', text: t('m_logo_file') }]); this.value = ''; return; }
    var fr = new FileReader(); fr.onload = function () { setStyle({ logo: String(fr.result) }); }; fr.readAsDataURL(f);
  });
  $('style-reset').addEventListener('click', function () { $('logo-file').value = ''; setStyle(Object.assign({}, DEFAULT_STYLE)); });
  PRESETS.forEach(function (p) {
    var b = document.createElement('button'); b.type = 'button'; b.className = 'swatch'; b.dataset.fg = p[0]; b.dataset.bg = p[1];
    b.style.setProperty('--c', 'linear-gradient(135deg, ' + p[0] + ' 50%, ' + p[1] + ' 50%)');
    b.setAttribute('aria-label', p[0] + ' / ' + p[1]); b.setAttribute('aria-pressed', 'false');
    b.addEventListener('click', function () { setStyle({ fg: p[0], bg: p[1] }); });
    $('presets').appendChild(b);
  });

  // tricky examples (the same values the decode proof uses)
  $('w-example').addEventListener('click', function () {
    $('w-ssid').value = 'Kopi;Hop,"Lt:2"\\'; $('w-pass').value = 'p;a,s:s"w\\o é日本語😀'; $('w-sec').value = 'WPA'; $('w-hidden').checked = true; render();
  });
  $('v-example').addEventListener('click', function () {
    var f = { 'v-first': 'Ann;Marie', 'v-last': 'Doe, Jr.', 'v-org': 'Kopi, Hop; Lt.2', 'v-title': 'Café ☕ 日本語', 'v-mobile': '+62 812-0000-0000', 'v-work': '(021) 555 0100',
      'v-email': 'ann.doe@example.com', 'v-url': 'example.com/menu?a=1&b=2', 'v-street': 'Jl. Merdeka No. 1, Blok A; Lt. 2', 'v-city': 'Jakarta', 'v-region': 'DKI', 'v-postal': '10110',
      'v-country': 'Indonesia', 'v-note': 'Baris 1\nBaris 2 日本語 Кириллица\nBaris 3: titik dua, koma; titik koma \\ garis miring' };
    Object.keys(f).forEach(function (k) { $(k).value = f[k]; });
    document.querySelector('#panel-vcard details').open = true; render();
  });

  /* ------------------------------------------------------------- downloads */
  async function download(ext) {
    var b = build(); if (!b.payload) return;
    var size = 1024;
    var n = lastModules || 0, margin = n ? Math.round(4 * size / (n + 8)) : Math.round(size * 0.09);
    var inst = new window.QRCodeStyling(qrOptions(ext === 'svg' ? 'svg' : 'canvas', size, b.payload, margin));
    var blob = await inst.getRawData(ext);
    var a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = 'qr-' + (state.tab === 'text' ? 'link' : state.tab) + '.' + ext;
    document.body.appendChild(a); a.click(); a.remove(); setTimeout(function () { URL.revokeObjectURL(a.href); }, 2000);
  }
  $('dl-png').addEventListener('click', function () { download('png'); });
  $('dl-svg').addEventListener('click', function () { download('svg'); });

  /* ---------------------------------------------------- dynamic QR (demo) */
  var dynIndex = 0;
  function buildDest() {
    var box = $('dest'); box.textContent = '';
    DEST.forEach(function (d, i) {
      var b = document.createElement('button'); b.type = 'button'; b.textContent = t(d.k); b.setAttribute('aria-pressed', String(i === dynIndex));
      b.addEventListener('click', function () { dynIndex = i; buildDest(); });
      box.appendChild(b);
    });
    $('dest-url').textContent = DEST[dynIndex].url;
  }
  function drawDemo() {
    var inst = new window.QRCodeStyling({ width: 264, height: 264, type: 'canvas', data: $('dyn-qr').dataset.url, margin: 18,
      qrOptions: { errorCorrectionLevel: 'M' }, dotsOptions: { color: '#111827', type: 'rounded' }, backgroundOptions: { color: '#ffffff' },
      cornersSquareOptions: { color: '#111827', type: 'extra-rounded' }, cornersDotOptions: { color: '#111827', type: 'dot' } });
    inst.append($('dyn-qr'));
  }

  /* ------------------------------------------------------------------ start */
  function start() {
    applyI18n();
    $('c-lang').value = state.cardLang;
    syncStyleInputs();
    ['wifi', 'vcard', 'text'].forEach(function (k) { $('tab-' + k).setAttribute('aria-selected', String(k === state.tab)); $('tab-' + k).tabIndex = k === state.tab ? 0 : -1; $('panel-' + k).hidden = k !== state.tab; });
    $('card-sec').hidden = state.tab !== 'wifi';
    document.querySelectorAll('.seg button').forEach(function (x) { x.setAttribute('aria-pressed', String(x.dataset.paper === state.paper)); });
    setPageRule();
    if (q.get('example') === '1') { $('w-example').click(); $('v-example').click(); }
    drawDemo();
    renderNow();
  }
  start();
}
// Deferred scripts run in order and this module comes first: wait for DOMContentLoaded, when the vendored qr-code-styling (a classic deferred script) has run.
if (document.readyState === 'complete') main(); else document.addEventListener('DOMContentLoaded', main);
