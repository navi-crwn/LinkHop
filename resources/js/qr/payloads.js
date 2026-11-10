/*
 * qr-payloads.js: builders for the text a QR code carries. Pure functions: no DOM,
 * no network, no storage, so the same file runs in the browser, in Node tests and
 * (copied) in BioHop. An ES module: `import * as QrPayloads from "./payloads.js"`.
 *
 * Every builder returns { payload, warnings, errors }. `errors` is a list of codes
 * that make the payload unusable (payload is then ''), `warnings` a list of codes the
 * page shows as advice. Codes are keys, not sentences: the page translates them.
 */
  var LIMITS = {
    ssidBytes: 32,        // IEEE 802.11 SSID
    wpaMin: 8,            // WPA/WPA2/WPA3 passphrase, characters
    wpaMax: 63,           // or exactly 64 hex digits (a raw key)
    softPayloadBytes: 1000, // above this the code gets dense: warn
    hardPayloadBytes: 2000  // above this refuse (QR capacity is lower still at high error correction)
  };

  // C0 controls except tab, LF and CR, plus DEL. LF/CR are handled per field.
  var CTRL = /[\u0000-\u0008\u000B\u000C\u000E-\u001F\u007F]/g;
  var NEWLINES = /\r\n|[\n\r\u0085\u2028\u2029]/g;

  function str(v) { return v == null ? '' : String(v); }

  function utf8Length(s) {
    var n = 0;
    for (var ch of str(s)) {
      var c = ch.codePointAt(0);
      n += c < 0x80 ? 1 : c < 0x800 ? 2 : c < 0x10000 ? 3 : 4;
    }
    return n;
  }

  /** The UTF-8 bytes of a string as a "binary string" (one char per byte). */
  function binaryString(s) {
    var out = '';
    for (var ch of str(s)) {
      var c = ch.codePointAt(0);
      if (c < 0x80) out += String.fromCharCode(c);
      else if (c < 0x800) out += String.fromCharCode(0xC0 | (c >> 6), 0x80 | (c & 63));
      else if (c < 0x10000) out += String.fromCharCode(0xE0 | (c >> 12), 0x80 | ((c >> 6) & 63), 0x80 | (c & 63));
      else out += String.fromCharCode(0xF0 | (c >> 18), 0x80 | ((c >> 12) & 63), 0x80 | ((c >> 6) & 63), 0x80 | (c & 63));
    }
    return out;
  }

  /* ------------------------------------------------------------------ Wi-Fi */

  /** Wi-Fi field escaping (ZXing / Android / iOS form): \ ; , : and " get a backslash. */
  function wifiEscape(s) {
    return str(s).replace(NEWLINES, ' ').replace(CTRL, '').replace(/[\\;,:"]/g, '\\$&');
  }

  var WIFI_TYPES = ['WPA', 'WEP', 'nopass', 'SAE'];

  /**
   * { ssid, password, security: 'WPA' | 'WEP' | 'nopass' | 'SAE', hidden: bool }
   * WPA covers WPA, WPA2 and WPA2/WPA3 mixed networks. SAE (WPA3 only) is Android's own
   * spelling and is not read by every phone: the page labels it as untested.
   */
  function buildWifi(o) {
    o = o || {};
    var warnings = [], errors = [];
    var sec = WIFI_TYPES.indexOf(o.security) >= 0 ? o.security : 'WPA';
    var rawSsid = str(o.ssid), rawPass = str(o.password);
    var ssid = rawSsid.replace(NEWLINES, ' ').replace(CTRL, '');
    var pass = rawPass.replace(NEWLINES, ' ').replace(CTRL, '');
    if (ssid !== rawSsid || pass !== rawPass) warnings.push('ctl_removed');

    if (ssid.trim() === '') errors.push('ssid_required');
    else {
      if (utf8Length(ssid) > LIMITS.ssidBytes) warnings.push('ssid_too_long');
      if (ssid !== ssid.trim()) warnings.push('edge_space');
    }
    if (sec !== 'nopass') {
      if (pass === '') errors.push('pass_required');
      else {
        if (sec === 'WEP') {
          if ([5, 13].indexOf(pass.length) < 0 && !/^[0-9a-fA-F]{10}$|^[0-9a-fA-F]{26}$/.test(pass)) warnings.push('wep_length');
        } else if (pass.length < LIMITS.wpaMin) warnings.push('pass_short');
        else if (pass.length > LIMITS.wpaMax && !/^[0-9a-fA-F]{64}$/.test(pass)) warnings.push('pass_long');
        if (pass !== pass.trim()) warnings.push('edge_space');
      }
    }
    if (errors.length) return { payload: '', warnings: warnings, errors: errors };

    var p = 'WIFI:T:' + sec + ';S:' + wifiEscape(ssid) + ';';
    if (sec !== 'nopass') p += 'P:' + wifiEscape(pass) + ';';
    if (o.hidden) p += 'H:true;';
    p += ';';
    return finish(p, warnings, errors);
  }

  /* ------------------------------------------------------------------ vCard */

  /**
   * vCard 3.0 text value (RFC 2426 section 5, RFC 6350 section 3.4): \ ; , get a backslash and
   * every line break becomes the two characters \n. Because no raw line break survives,
   * a value can never start a new vCard line (no injected NOTE:, PHOTO: or END:VCARD).
   */
  function vcardEscape(s) {
    return str(s).replace(NEWLINES, '\n').replace(CTRL, '')
      .replace(/[\\;,]/g, '\\$&').replace(/\n/g, '\\n');
  }

  /** Fold one content line at 75 octets without splitting a UTF-8 sequence (RFC 2426 section 2.6). */
  function foldLine(line, max) {
    max = max || 75;
    var out = [], cur = '', bytes = 0;
    for (var ch of line) {
      var c = ch.codePointAt(0);
      var b = c < 0x80 ? 1 : c < 0x800 ? 2 : c < 0x10000 ? 3 : 4;
      if (bytes + b > max) { out.push(cur); cur = ' '; bytes = 1; }
      cur += ch; bytes += b;
    }
    out.push(cur);
    return out;
  }

  var EMAIL_RE = /^[^\s@;,:]+@[^\s@;,:]+\.[^\s@;,:]+$/;

  /**
   * { firstName, lastName, org, title, mobile, work, email, url,
   *   street, city, region, postal, country, note }
   * opts: { eol: '\r\n', fold: false, final: false }. The QR code uses no folding; the .vcf file does.
   */
  function buildVcard(o, opts) {
    o = o || {};
    opts = opts || {};
    var eol = opts.eol || '\r\n';
    var warnings = [], errors = [];
    var g = function (k) { return str(o[k]).trim(); };
    var first = g('firstName'), last = g('lastName'), org = g('org');
    var fn = [first, last].filter(Boolean).join(' ') || org;
    if (!fn) errors.push('name_required');
    if (errors.length) return { payload: '', warnings: warnings, errors: errors };

    var lines = ['BEGIN:VCARD', 'VERSION:3.0'];
    lines.push('N:' + [last, first, '', '', ''].map(vcardEscape).join(';'));
    lines.push('FN:' + vcardEscape(fn));
    if (org) lines.push('ORG:' + vcardEscape(org));
    if (g('title')) lines.push('TITLE:' + vcardEscape(g('title')));
    if (g('mobile')) lines.push('TEL;TYPE=CELL:' + vcardEscape(g('mobile')));
    if (g('work')) lines.push('TEL;TYPE=WORK,VOICE:' + vcardEscape(g('work')));
    if (g('email')) {
      if (!EMAIL_RE.test(g('email'))) warnings.push('email_invalid');
      lines.push('EMAIL;TYPE=INTERNET:' + vcardEscape(g('email')));
    }
    if (g('url')) {
      var u = g('url');
      if (!/^[a-z][a-z0-9+.-]*:/i.test(u)) u = 'https://' + u;
      if (/\s/.test(u)) warnings.push('url_invalid');
      else if (!/^https?:\/\//i.test(u)) warnings.push('url_scheme');
      else lines.push('URL:' + u); // a URI value is not text: no escaping, and no whitespace got through
    }
    if (g('street') || g('city') || g('region') || g('postal') || g('country')) {
      lines.push('ADR;TYPE=WORK:;;' + [g('street'), g('city'), g('region'), g('postal'), g('country')].map(vcardEscape).join(';'));
    }
    if (g('note')) lines.push('NOTE:' + vcardEscape(str(o.note).trim()));
    lines.push('END:VCARD');

    if (opts.fold) lines = lines.reduce(function (acc, l) { return acc.concat(foldLine(l)); }, []);
    var p = lines.join(eol) + (opts.final ? eol : '');
    return finish(p, warnings, errors);
  }

  /** A safe file name for the .vcf download: no path characters, no controls, 80 characters at most. */
  function vcardFileName(name) {
    var n = str(name).replace(NEWLINES, ' ').replace(CTRL, '').replace(/[\\\/:*?"<>|]+/g, '').replace(/\s+/g, '_').replace(/^[._]+|[._]+$/g, '');
    n = Array.from(n).slice(0, 80).join('');
    return (n || 'contact') + '.vcf';
  }

  /* --------------------------------------------------------- text and links */

  function buildText(o) {
    o = o || {};
    var warnings = [], errors = [];
    var t = str(o.text).replace(NEWLINES, '\n').replace(CTRL, '');
    if (t.trim() === '') errors.push('text_required');
    if (errors.length) return { payload: '', warnings: warnings, errors: errors };
    var oneLine = t.trim();
    if (/^[a-z][a-z0-9+.-]*:\/\//i.test(oneLine) && !/\s/.test(oneLine)) {
      if (!/^https?:\/\//i.test(oneLine)) warnings.push('url_scheme_other');
    } else if (/^(www\.)?[a-z0-9-]+(\.[a-z0-9-]+)+(\/\S*)?$/i.test(oneLine)) {
      warnings.push('url_no_scheme'); // scanners treat it as plain text and may not offer to open it
    }
    return finish(t, warnings, errors);
  }

  function finish(payload, warnings, errors) {
    var n = utf8Length(payload);
    if (n > LIMITS.hardPayloadBytes) { errors.push('payload_too_long'); return { payload: '', warnings: warnings, errors: errors, bytes: n }; }
    if (n > LIMITS.softPayloadBytes) warnings.push('payload_long');
    return { payload: payload, warnings: warnings, errors: errors, bytes: n };
  }

  /* ---------------------------------------------------- scan-ability checks */

  function parseHex(h) {
    h = str(h).replace('#', '').trim();
    if (h.length === 3) h = h.replace(/./g, '$&$&');
    if (!/^[0-9a-fA-F]{6}$/.test(h)) return null;
    return [0, 2, 4].map(function (i) { return parseInt(h.substr(i, 2), 16); });
  }

  function luminance(rgb) {
    var c = rgb.map(function (v) { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); });
    return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
  }

  /** WCAG contrast ratio, 1 to 21, or null for a bad colour. */
  function contrastRatio(a, b) {
    var x = parseHex(a), y = parseHex(b);
    if (!x || !y) return null;
    var l1 = luminance(x), l2 = luminance(y);
    return (Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05);
  }

  /**
   * Advice for a pair of colours. level: ok | warn | bad. These thresholds are a rule of thumb
   * (see RENCANA, "belum diuji di ponsel"): dark code on a light ground, ratio of 4.5 or more.
   */
  function scanAdvice(fg, bg) {
    var ratio = contrastRatio(fg, bg);
    if (ratio === null) return { ratio: null, inverted: false, level: 'bad', codes: ['bad_colour'] };
    var inverted = luminance(parseHex(fg)) > luminance(parseHex(bg));
    var codes = [], level = 'ok';
    if (ratio < 3) { level = 'bad'; codes.push('contrast_bad'); }
    else if (ratio < 4.5) { level = 'warn'; codes.push('contrast_low'); }
    if (inverted) { if (level === 'ok') level = 'warn'; codes.push('inverted'); }
    return { ratio: ratio, inverted: inverted, level: level, codes: codes };
  }

  /** Width of one dark square in millimetres when the printed code (quiet zone included) is sizeMm wide. */
  function moduleMm(sizeMm, modules, quietModules) {
    if (!modules) return null;
    return sizeMm / (modules + 2 * (quietModules == null ? 4 : quietModules));
  }

export {
  LIMITS,
  utf8Length,
  binaryString,
  wifiEscape,
  buildWifi,
  vcardEscape,
  foldLine,
  buildVcard,
  vcardFileName,
  buildText,
  contrastRatio,
  scanAdvice,
  moduleMm
};
