// Regenerates every brand file in resources/brand (and public/favicon.ico) from one design:
//   npm i --no-save opentype.js@1.3.4 @fontsource/poppins@5.1.0 && node scripts/linkhop-logo.cjs
// SVGs first, then the PNGs and the .ico are rendered from them with the repo's Playwright.
// Builds the LinkHop logo files as plain SVG (text converted to outlines, no font needed).
// Wordmark: "LinkH" + an "o" that is a bunny head + "p", with an Amazon-style ribbon from under
// the L to an arrow pointing at the p. Mark: the bunny head on its own.
const fs = require('fs'), path = require('path'), opentype = require('opentype.js');
const out = process.argv[2] || path.join(__dirname, '..', 'resources', 'brand');
const font = opentype.parse(fs.readFileSync(require.resolve('@fontsource/poppins/files/poppins-latin-800-italic.woff')).buffer);

const SIZE = 96, BASE = 118, X0 = 30, T = Math.tan(10 * Math.PI / 180);
const XH = SIZE * .555, R = XH * .54, W = SIZE * .155, CY = BASE - XH / 2 + 1;
const f = n => +n.toFixed(2);
const GRAD = (id, x1, x2) => `<linearGradient id="${id}" gradientUnits="userSpaceOnUse" x1="${f(x1)}" y1="0" x2="${f(x2)}" y2="0"><stop offset="0" stop-color="#0ea5e9"/><stop offset=".5" stop-color="#6366f1"/><stop offset="1" stop-color="#d946ef"/></linearGradient>`;

// The bunny head: two ears and a ring the weight of the font's strokes, slanted like the italic.
// Drawn as absolute outlines (no transform), so a shared gradient runs straight across it.
function bunny(cx, cy, r, w, fill) {
  const sk = (x, y) => [cx + x - y * T, cy + y];                       // local -> slanted absolute
  const ellipse = (ex, ey, rx, ry, rot = 0, rev = false) => {
    const c = Math.cos(rot * Math.PI / 180), s = Math.sin(rot * Math.PI / 180), pts = [];
    for (let i = 0; i < 96; i++) { const a = (rev ? -1 : 1) * i / 96 * Math.PI * 2, x = rx * Math.cos(a), y = ry * Math.sin(a);
      pts.push(sk(ex + x * c - y * s, ey + x * s + y * c)); }
    return 'M' + pts.map(p => `${f(p[0])} ${f(p[1])}`).join('L') + 'Z';
  };
  const ears = ellipse(-r * .38, -r * 1.62, r * .3, r * .82, -12) + ellipse(r * .38, -r * 1.62, r * .3, r * .82, 12);
  const ring = ellipse(0, 0, r, r * .94) + ellipse(0, 0, r - w, r * .94 - w, 0, true);
  return `<path fill="${fill}" d="${ears}"/><path fill="${fill}" fill-rule="evenodd" d="${ring}"/>`;
}

// A ribbon of varying width along a list of points, as one closed path.
function ribbonPath(pts, width) {
  const L = [], Rr = [], N = pts.length - 1;
  pts.forEach((p, i) => {
    const a = pts[Math.max(0, i - 1)], b = pts[Math.min(N, i + 1)];
    let nx = -(b[1] - a[1]), ny = b[0] - a[0]; const m = Math.hypot(nx, ny) || 1; nx /= m; ny /= m;
    const h = width(i / N) / 2; L.push(`${f(p[0] + nx * h)} ${f(p[1] + ny * h)}`); Rr.unshift(`${f(p[0] - nx * h)} ${f(p[1] - ny * h)}`);
  });
  return 'M' + L.join('L') + 'L' + Rr.join('L') + 'Z';
}

function wordmark(fill, gradId) {
  // Lay out the real word "LinkHop" with the font's own spacing and kerning, then swap the o for the bunny
  // head, so the letters sit exactly as tight as the typeface intends.
  const glyphs = font.getPaths('LinkHop', X0, BASE, SIZE, { kerning: true });
  const ob = glyphs[5].getBoundingBox(), p = glyphs[6], pb = p.getBoundingBox();
  const head = { toPathData: n => glyphs.slice(0, 5).map(g => g.toPathData(n)).join('') };
  const ocx = (ob.x1 + ob.x2) / 2, r = (ob.y2 - ob.y1) / 2 / .94, ocy = (ob.y1 + ob.y2) / 2;
  // ribbon: free of the letters, starts under the L, dips, rises into an arrow aimed at the p's tail
  const sx = X0 + 6, y0 = BASE + 26, ex = pb.x1 - SIZE * .62, ey = BASE + 44, span = ex - sx, pts = [];
  for (let i = 0; i <= 240; i++) { const u = i / 240; pts.push([sx + u * span, y0 + 22 * Math.sin(Math.PI * Math.min(1, u * 1.05)) - 18 * u ** 3]); }
  const ribbon = ribbonPath(pts, t => SIZE * (.03 + .085 * Math.pow(Math.sin(Math.PI * Math.min(1, t * 1.06)), .6) + .04 * t ** 3));
  const e = pts[pts.length - 1], q = pts[pts.length - 4], a = Math.atan2(e[1] - q[1], e[0] - q[0]), Lh = SIZE * .24;
  const P = (dx, dy) => `${f(e[0] + dx * Math.cos(a) - dy * Math.sin(a))} ${f(e[1] + dx * Math.sin(a) + dy * Math.cos(a))}`;
  const arrow = `M${P(Lh * .7, 0)}L${P(-Lh * .35, -Lh * .62)}L${P(-Lh * .1, 0)}L${P(-Lh * .35, Lh * .62)}Z`;
  const x1 = X0 - 8, x2 = pb.x2 + 6, top = CY - R * 2.5, bottom = Math.max(pb.y2, y0 + 30) + 6;
  return { fill, gradId, ocx, ocy, r, d: head.toPathData(2) + p.toPathData(2), ribbon, arrow, x1, x2, top, bottom };
}

function wordmarkSvg(fill) {
  const m = wordmark();
  const ink = fill || 'url(#lh)';
  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="${f(m.x1)} ${f(m.top)} ${f(m.x2 - m.x1)} ${f(m.bottom - m.top)}">
<defs>${GRAD('lh', m.x1, m.x2)}</defs>
<g fill="${ink}"><path d="${m.d}"/><path d="${m.ribbon}"/><path d="${m.arrow}" stroke="${ink}" stroke-width="4" stroke-linejoin="round"/></g>
${bunny(m.ocx, m.ocy, m.r, W, ink)}
</svg>
`;
}

// Mark: the bunny head alone, in a gradient tile (favicon / app icon) or on its own.
function markSvg(kind) {
  const tile = kind === 'tile';
  const ink = tile ? '#fff' : kind === 'white' ? '#fff' : 'url(#lh)';
  const g = `<defs><linearGradient id="lh" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#0ea5e9"/><stop offset=".55" stop-color="#6366f1"/><stop offset="1" stop-color="#d946ef"/></linearGradient>
<linearGradient id="gl" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fff" stop-opacity=".28"/><stop offset=".5" stop-color="#fff" stop-opacity="0"/></linearGradient></defs>`;
  const bg = tile ? '<rect width="512" height="512" rx="116" fill="url(#lh)"/><rect width="512" height="512" rx="116" fill="url(#gl)"/>' : '';
  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">${g}${bg}${bunny(262, 344, 112, 48, ink)}</svg>
`;
}

fs.writeFileSync(path.join(out, 'linkhop-wordmark.svg'), wordmarkSvg());
fs.writeFileSync(path.join(out, 'linkhop-wordmark-white.svg'), wordmarkSvg('#fff'));
fs.writeFileSync(path.join(out, 'linkhop-mark.svg'), markSvg('tile'));
fs.writeFileSync(path.join(out, 'linkhop-mark-gradient.svg'), markSvg('gradient'));
fs.writeFileSync(path.join(out, 'linkhop-mark-white.svg'), markSvg('white'));

// PNGs: favicon/app icon (tile), logo-dark (tile, for light backgrounds), logo (white glyph, for dark ones);
// public/favicon.ico (16/32/48 px PNGs inside one .ico, for browsers that ask for /favicon.ico).
(async () => {
  const { chromium } = require('@playwright/test');
  const browser = await chromium.launch();
  const render = async (svg, size) => {
    const page = await browser.newPage({ viewport: { width: size, height: size } });
    await page.setContent(`<style>html,body{margin:0;background:transparent}</style><img src="data:image/svg+xml;base64,${Buffer.from(fs.readFileSync(path.join(out, svg))).toString('base64')}" width="${size}" height="${size}">`);
    const png = await page.screenshot({ omitBackground: true });
    await page.close();
    return png;
  };
  fs.writeFileSync(path.join(out, 'favicon.png'), await render('linkhop-mark.svg', 256));
  fs.writeFileSync(path.join(out, 'logo-dark.png'), await render('linkhop-mark.svg', 512));
  fs.writeFileSync(path.join(out, 'logo.png'), await render('linkhop-mark-white.svg', 512));
  const sizes = [16, 32, 48], pngs = await Promise.all(sizes.map(n => render('linkhop-mark.svg', n)));
  const head = Buffer.alloc(6 + 16 * sizes.length); head.writeUInt16LE(1, 2); head.writeUInt16LE(sizes.length, 4);
  let offset = head.length;
  sizes.forEach((n, i) => { const e = 6 + 16 * i; head[e] = n; head[e + 1] = n; head.writeUInt16LE(1, e + 4); head.writeUInt16LE(32, e + 6); head.writeUInt32LE(pngs[i].length, e + 8); head.writeUInt32LE(offset, e + 12); offset += pngs[i].length; });
  fs.writeFileSync(path.join(__dirname, '..', 'public', 'favicon.ico'), Buffer.concat([head, ...pngs]));
  await browser.close();
  console.log('ok: resources/brand + public/favicon.ico');
})();
