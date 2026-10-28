// Home page hero: a holographic sea of pixel columns. Islands rise out of it, bunnies hop
// through hel.ink to the place a link leads (a school, a mall, a hospital…), and the URL below
// scrambles from long to short and back. Scenarios rotate: a plain hop, sharing a link between
// two bunnies, and a password that works or doesn't. The sea ripples and lights up under the
// cursor; colours follow the site's light/dark theme. Paused off screen; one still frame under
// prefers-reduced-motion. Loaded on the home page only.
import * as THREE from 'three';

function start(root) {
    if (!root.offsetParent) return; // hidden (the miniature on phones): don't start a WebGL context for nothing
    const isDark = () => document.documentElement.classList.contains('dark');

    const el = root.querySelector('[data-hop-canvas]');
    let renderer;
    try { renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true }); } catch (e) { return; } // no WebGL: the static picture stays
    root.querySelector('[data-hop-fallback]')?.remove();
    renderer.setPixelRatio(Math.min(devicePixelRatio, 2)); renderer.setSize(el.clientWidth, el.clientHeight);
    renderer.shadowMap.enabled = true; renderer.shadowMap.type = THREE.PCFSoftShadowMap;
    el.appendChild(renderer.domElement);
    renderer.domElement.setAttribute('aria-hidden', 'true');
    const scene = new THREE.Scene();
    const cam = new THREE.PerspectiveCamera(30, el.clientWidth / el.clientHeight, .1, 200);
    cam.position.set(0, 17, 15.5); cam.lookAt(0, 0, 1.2);
    const hemi = new THREE.HemisphereLight(0xffffff, 0x6366f1, 1.4); scene.add(hemi);
    const sun = new THREE.DirectionalLight(0xffffff, 2); sun.position.set(-6, 12, 8); sun.castShadow = true;
    sun.shadow.mapSize.set(2048, 2048); Object.assign(sun.shadow.camera, { left: -16, right: 16, top: 11, bottom: -11 }); scene.add(sun);
    const cursorLight = new THREE.PointLight(0xd946ef, 20, 8, 1.5); scene.add(cursorLight);

    const SKY = new THREE.Color('#0ea5e9'), IND = new THREE.Color('#6366f1'), FUC = new THREE.Color('#d946ef');
    const grad = t => t < .5 ? SKY.clone().lerp(IND, t * 2) : IND.clone().lerp(FUC, (t - .5) * 2);
    const clamp = (v, a = 0, b = 1) => Math.min(b, Math.max(a, v)), ease = f => f < .5 ? 2 * f * f : 1 - (-2 * f + 2) ** 2 / 2;
    const hash = (a, b) => { const s = Math.sin(a * 127.1 + b * 311.7) * 43758.5453; return s - Math.floor(s); };

    // ------------------------------------------------ the sea: pixel columns that fade out at the edges (no board)
    // data-mini: the small copy next to the shortener (fewer columns, closer camera, plain hops only)
    const mini = 'mini' in root.dataset, small = innerWidth < 640;
    const CELL = mini ? .25 : small ? .3 : .2, NX = mini ? 72 : small ? 100 : 150, NZ = mini ? 60 : small ? 60 : 90, W = NX * CELL, D = NZ * CELL, K = Math.max(.7, W / 30);
    const sea = new THREE.InstancedMesh(new THREE.BoxGeometry(.12, 1, .12), new THREE.MeshStandardMaterial({ roughness: .4, metalness: .1, transparent: true, opacity: .85 }), NX * NZ);
    sea.receiveShadow = true; sea.renderOrder = 1; scene.add(sea);
    const cells = [];
    for (let i = 0; i < NX; i++) for (let k = 0; k < NZ; k++) {
      const x = -W / 2 + (i + .5) * CELL, z = -D / 2 + (k + .5) * CELL;
      // soft, irregular falloff instead of a rectangle edge
      const e = Math.hypot(x / (W * .46), z / (D * .5)) + (hash(i, k) - .5) * .12 + .06 * Math.sin(Math.atan2(z, x) * 5);
      const fade = clamp((1 - e) / .35);
      if (fade > 0) cells.push({ x, z, n: hash(i, k), fade, explored: 0 });
    }
    sea.count = cells.length;
    const M = new THREE.Matrix4(), C = new THREE.Color(), Q = new THREE.Quaternion(), V = new THREE.Vector3(), P = new THREE.Vector3();

    // ------------------------------------------------ islands ("countries") that rise out of the sea
    function region(cx, cz, R, seed) {
      const a = hash(seed, 1) * 6, b = hash(seed, 2) * 6;
      return { cx, cz, R, rAt: th => R * (1 + .24 * Math.sin(3 * th + a) + .14 * Math.sin(5 * th + b) + .07 * Math.sin(9 * th + a * 2)) };
    }
    const inRegion = (rg, x, z) => { const dx = x - rg.cx, dz = z - rg.cz, d = Math.hypot(dx, dz), r = rg.rAt(Math.atan2(dz, dx)); return 1 - d / r; };
    // terrain colours by height and distance from the coast: shallow water, sand, grass, forest, rock
    const SHALLOW = new THREE.Color('#67e8f9'), SAND = new THREE.Color('#fde68a'), GRASS = new THREE.Color('#86efac'), FOREST = new THREE.Color('#22c55e'), ROCK = new THREE.Color('#a8a29e'), PEAK = new THREE.Color('#f5f5f4');
    function terrain(w, n) {
      if (w < .08) return SHALLOW.clone().lerp(SAND, w / .08);
      if (w < .2) return SAND.clone();
      if (w < .55) return GRASS.clone().lerp(FOREST, (w - .2) / .35 + (n - .5) * .3);
      if (w < .8) return FOREST.clone().lerp(ROCK, (w - .55) / .25);
      return ROCK.clone().lerp(PEAK, (w - .8) / .2);
    }

    // ------------------------------------------------ the bunny: a sitting rabbit made of small cubes, with real features
    function bunnyModel() {
      const s = .055, v = [];
      const add = (x, y, z, c) => v.push([x, y, z, c]);
      const blob = (cx, cy, cz, rx, ry, rz, c) => { for (let x = cx - rx; x <= cx + rx; x += s) for (let y = cy - ry; y <= cy + ry; y += s) for (let z = cz - rz; z <= cz + rz; z += s) if (((x - cx) / rx) ** 2 + ((y - cy) / ry) ** 2 + ((z - cz) / rz) ** 2 <= 1) add(x, y, z, c); };
      blob(0, .26, 0, .26, .26, .22, 'fur');            // body
      blob(.04, .6, 0, .19, .17, .17, 'fur');           // head
      blob(-.01, .95, -.07, .05, .25, .035, 'fur');      // ears
      blob(.06, .95, .07, .05, .25, .035, 'fur');
      blob(-.0, .95, -.07 + .02, .025, .19, .02, 'pink'); // inner ears
      blob(.07, .95, .07 + .02, .025, .19, .02, 'pink');
      blob(-.27, .2, 0, .08, .08, .08, 'fur');          // tail
      blob(.16, .04, -.11, .11, .05, .06, 'fur');       // feet
      blob(.16, .04, .11, .11, .05, .06, 'fur');
      // face on the +x side: eyes, nose
      const front = .04 + .19;
      add(front - .02, .64, -.08, 'eye'); add(front - .02, .64, .08, 'eye');
      add(front + .01, .56, 0, 'pink');
      const COL = { fur: () => new THREE.Color('#f8fafc').lerp(new THREE.Color('#cbd5e1'), Math.random() * .25), pink: () => new THREE.Color('#f9a8d4'), eye: () => new THREE.Color('#0f172a') };
      const m = new THREE.InstancedMesh(new THREE.BoxGeometry(s, s, s), new THREE.MeshStandardMaterial({ roughness: .6 }), v.length);
      v.forEach((p, i) => m.setColorAt(i, COL[p[3]]()));
      m.castShadow = true; m.userData.v = v; scene.add(m); return m;
    }
    function placeBunny(m, at, rotY, scale, show, squash = 1) {
      const c = Math.cos(rotY), s = Math.sin(rotY);
      m.userData.v.forEach((p, i) => {
        const x = p[0] * c - p[2] * s, z = p[0] * s + p[2] * c;
        P.set(at.x + x * scale / Math.sqrt(squash), at.y + p[1] * scale * squash, at.z + z * scale / Math.sqrt(squash));
        m.setMatrixAt(i, M.compose(P, Q, V.setScalar(show ? scale : 0)));
      });
      m.instanceMatrix.needsUpdate = true;
    }

    // ------------------------------------------------ buildings: parts that rise and stack in order, like a puzzle
    const mat = (c, o = {}) => new THREE.MeshStandardMaterial({ color: c, roughness: .55, ...o });
    const boxG = (w, h, d) => new THREE.BoxGeometry(w, h, d);
    const roofG = (w, h, d) => { const g = new THREE.CylinderGeometry(0, 1, 1, 4, 1); g.rotateY(Math.PI / 4); g.scale(w / Math.SQRT2, h, d / Math.SQRT2); return g; };
    function windows(parts, w, h, d, y0, rows, cols, c = '#1e3a8a', front = true) {
      for (let r = 0; r < rows; r++) for (let k = 0; k < cols; k++) parts.push([boxG(w / cols * .5, h / rows * .45, .03), mat(c, { emissive: c, emissiveIntensity: .25 }), [-w / 2 + (k + .5) * w / cols, y0 + (r + .5) * h / rows, front ? d / 2 + .015 : 0]]);
    }
    const BUILD = {
      school() { const p = [[boxG(2, .8, 1.1), mat('#fef3c7'), [0, .4, 0]]]; windows(p, 2, .8, 1.1, 0, 2, 5); p.push([boxG(.3, .45, .05), mat('#92400e'), [0, .23, .57]], [roofG(2.2, .55, 1.3), mat('#dc2626'), [0, 1.08, 0]], [boxG(.04, 1.3, .04), mat('#e5e7eb'), [1.25, .65, .4]], [boxG(.4, .25, .02), mat('#ef4444'), [1.47, 1.18, .4]]); return p; },
      mall() { const glass = mat('#7dd3fc', { transparent: true, opacity: .85, metalness: .3, roughness: .15 }); const p = [[boxG(2.4, .7, 1.4), glass, [0, .35, 0]], [boxG(2, .6, 1.2), glass, [0, 1.0, 0]], [boxG(2.6, .08, 1.6), mat('#e2e8f0'), [0, .74, 0]], [boxG(1.1, .35, .06), mat('#db2777', { emissive: '#db2777', emissiveIntensity: .5 }), [0, 1.55, .5]], [boxG(.8, .06, .5), mat('#f472b6'), [0, .5, .9]]]; windows(p, 2.4, .7, 1.4, 0, 1, 6, '#0c4a6e'); return p; },
      government() { const p = [[boxG(2.4, .16, 1.4), mat('#e7e5e4'), [0, .08, 0]], [boxG(2.2, .16, 1.2), mat('#f5f5f4'), [0, .24, 0]], [boxG(2, .8, 1), mat('#fafaf9'), [0, .72, -.05]]]; for (let i = 0; i < 6; i++) p.push([new THREE.CylinderGeometry(.07, .07, .8, 12), mat('#ffffff'), [-.85 + i * .34, .72, .5]]); p.push([roofG(2.3, .3, 1.2), mat('#d6d3d1'), [0, 1.27, 0]], [new THREE.SphereGeometry(.42, 24, 12, 0, Math.PI * 2, 0, Math.PI / 2), mat('#facc15', { metalness: .4, roughness: .3 }), [0, 1.3, -.1]], [boxG(.04, .4, .04), mat('#e5e7eb'), [0, 1.9, -.1]], [boxG(.28, .18, .02), mat('#ef4444'), [.16, 2.0, -.1]]); return p; },
      stadium() { const t = new THREE.TorusGeometry(1.1, .32, 10, 40); t.rotateX(Math.PI / 2); t.scale(1.25, .7, 1); return [[new THREE.CylinderGeometry(1.25, 1.25, .1, 40).scale(1.25, 1, 1), mat('#4ade80'), [0, .05, 0]], [t, mat('#e5e7eb'), [0, .3, 0]], [boxG(.06, 1, .06), mat('#94a3b8'), [1.4, .8, .9]], [boxG(.25, .15, .25), mat('#fef9c3', { emissive: '#fef08a', emissiveIntensity: .8 }), [1.4, 1.35, .9]], [boxG(.06, 1, .06), mat('#94a3b8'), [-1.4, .8, -.9]], [boxG(.25, .15, .25), mat('#fef9c3', { emissive: '#fef08a', emissiveIntensity: .8 }), [-1.4, 1.35, -.9]]]; },
      tv() { const p = [[boxG(.9, .3, .9), mat('#cbd5e1'), [0, .15, 0]]]; for (let i = 0; i < 6; i++) p.push([new THREE.CylinderGeometry(.13 - i * .012, .15 - i * .012, .38, 10), mat(i % 2 ? '#ffffff' : '#ef4444'), [0, .5 + i * .38, 0]]); p.push([new THREE.SphereGeometry(.32, 20, 14), mat('#93c5fd', { metalness: .3, roughness: .2 }), [0, 2.0, 0]], [boxG(.03, .6, .03), mat('#94a3b8'), [0, 2.6, 0]]); return p; },
      concert() { const arch = new THREE.TorusGeometry(1.1, .07, 8, 30, Math.PI); return [[boxG(2.4, .3, 1.2), mat('#1f2937'), [0, .15, 0]], [boxG(2.2, .05, 1), mat('#a855f7', { emissive: '#a855f7', emissiveIntensity: .4 }), [0, .32, 0]], [arch, mat('#94a3b8'), [0, .32, -.45]], [boxG(.3, .6, .3), mat('#111827'), [-1.3, .6, .2]], [boxG(.3, .6, .3), mat('#111827'), [1.3, .6, .2]], [boxG(.14, .14, .14), mat('#f472b6', { emissive: '#f472b6', emissiveIntensity: 1 }), [-.5, 1.35, -.45]], [boxG(.14, .14, .14), mat('#38bdf8', { emissive: '#38bdf8', emissiveIntensity: 1 }), [.5, 1.35, -.45]], [boxG(.14, .14, .14), mat('#facc15', { emissive: '#facc15', emissiveIntensity: 1 }), [0, 1.42, -.45]]]; },
      hospital() { const p = [[boxG(1.8, 1.4, 1.1), mat('#ffffff'), [0, .7, 0]]]; windows(p, 1.8, 1.4, 1.1, 0, 3, 4, '#0369a1'); p.push([boxG(.5, .14, .05), mat('#ef4444', { emissive: '#ef4444', emissiveIntensity: .4 }), [0, 1.65, .3]], [boxG(.14, .5, .05), mat('#ef4444', { emissive: '#ef4444', emissiveIntensity: .4 }), [0, 1.65, .3]], [boxG(.5, .5, .08), mat('#e2e8f0'), [0, 1.65, .25]]); return p; },
    };
    function buildingModel(type) {
      const g = new THREE.Group();
      BUILD[type]().forEach(([geo, m, pos], i) => { const mesh = new THREE.Mesh(geo, m); mesh.castShadow = mesh.receiveShadow = true; mesh.userData = { pos, i }; g.add(mesh); });
      g.userData.n = g.children.length; scene.add(g); return g;
    }
    function placeBuilding(g, at, k) { // parts rise from below the ground one after another, then settle
      g.position.copy(at); g.visible = k > 0;
      g.children.forEach(c => { const { pos, i } = c.userData; const f = ease(clamp((k * (g.userData.n + 2) - i) / 2.2)); c.position.set(pos[0], pos[1] - (1 - f) * 2.5, pos[2]); c.scale.setScalar(f < .02 ? .001 : .6 + .4 * f); });
    }

    // ------------------------------------------------ scenarios
    const LINKS = [
      ['school', 'school', 'school.example.com/students/2026/semester-2/assignments/final-project-guidelines.pdf'],
      ['sale', 'mall', 'shop.example.com/collections/summer-sale/products/linen-shirt?color=sand&size=m'],
      ['permit', 'government', 'city.example.gov/services/business-permits/apply?step=1&type=new'],
      ['finals', 'stadium', 'tickets.example.com/football/2026/cup-final/seat-map?section=north'],
      ['live', 'tv', 'tv.example.com/live/evening-news-2026-10-28?quality=1080p'],
      ['tour', 'concert', 'concerts.example.com/world-tour-2026/london/presale?code=HOP25'],
      ['checkup', 'hospital', 'clinic.example.com/appointments/new?department=general&doctor=any'],
    ];
    const SCENARIOS = ['hop', 'share', 'pass-ok', 'pass-fail'];
    const rnd = seed => { let s = seed * 9301 + 49297; return () => (s = (s * 9301 + 49297) % 233280) / 233280; };
    const pick = (r, avoid = []) => { for (let n = 0; ; n++) { const p = new THREE.Vector3((r() - .5) * W * .56, 0, (r() - .55) * D * .5); if (avoid.every(a => a.distanceTo(p) > (8.5 - Math.min(3, n * .05)) * K)) return p; } };
    function makeLabel(text) {
      const c = document.createElement('canvas'); c.width = 256; c.height = 80; const g = c.getContext('2d');
      g.fillStyle = 'rgba(255,255,255,.95)'; g.beginPath(); g.roundRect(4, 4, 248, 72, 36); g.fill();
      g.fillStyle = '#4f46e5'; g.font = '700 40px ui-monospace, Menlo, monospace'; g.textAlign = 'center'; g.textBaseline = 'middle'; g.fillText(text, 128, 42);
      const s = new THREE.Sprite(new THREE.SpriteMaterial({ map: new THREE.CanvasTexture(c), depthTest: false, transparent: true })); s.scale.set(1.9, .6, 1); s.renderOrder = 10; scene.add(s); return s;
    }
    function lockModel() {
      const g = new THREE.Group();
      const body = new THREE.Mesh(new RoundedBox(.5, .42, .2), mat('#facc15', { metalness: .5, roughness: .3 })); body.position.y = .21; g.add(body);
      const sh = new THREE.Mesh(new THREE.TorusGeometry(.16, .045, 10, 24, Math.PI), mat('#cbd5e1', { metalness: .7, roughness: .25 })); sh.position.y = .42; g.add(sh); g.userData.shackle = sh;
      scene.add(g); return g;
    }
    function RoundedBox(w, h, d) { return new THREE.BoxGeometry(w, h, d); }
    function makeRun(n, forced) {
      const r = rnd(n + 11), type = forced || (mini ? 'loop' : SCENARIOS[n % SCENARIOS.length]), link = LINKS[Math.floor(r() * LINKS.length)];
      // the miniature keeps a fixed, readable layout: home back-left, hel.ink in the middle, the destination front-right
      const A = mini ? new THREE.Vector3(-W * .21, 0, -D * .16) : pick(r), B = type === 'share' ? pick(r, [A]) : null, from = B || A;
      const Dd = mini ? new THREE.Vector3(W * .24, 0, D * .14) : pick(r, [A, from]);
      const hub = (type === 'share' ? A.clone().lerp(B, .5) : from.clone().lerp(Dd, .5)); hub.z += (r() - .5);
      const run = {
        n, type, link, A, B, D: Dd, hub,
        rA: region(A.x, A.z, (1.5 + r() * .5) * K, n + 1), rB: B && region(B.x, B.z, (1.4 + r() * .5) * K, n + 2),
        rD: region(Dd.x, Dd.z, Math.max(1.9, (2.1 + r() * .4) * K), n + 3), rH: region(hub.x, hub.z, Math.max(.8, K), n + 4),
        bunnyA: bunnyModel(), bunnyB: B ? bunnyModel() : null, building: buildingModel(link[1]), lock: lockModel(),
        packet: new THREE.Mesh(new THREE.OctahedronGeometry(.18), mat('#d946ef', { emissive: '#d946ef', emissiveIntensity: .9 })),
        trail: new THREE.InstancedMesh(new THREE.SphereGeometry(.05, 8, 8), new THREE.MeshBasicMaterial({ color: '#c4b5fd' }), 40),
        label: makeLabel('hel.ink'),
      };
      scene.add(run.packet, run.trail);
      run.dur = { hop: 8, share: 9.5, 'pass-ok': 9.5, 'pass-fail': 8.5, loop: 10.8 }[type];
      return run;
    }
    function disposeRun(run) { for (const k of ['bunnyA', 'bunnyB', 'building', 'lock', 'packet', 'trail', 'label']) if (run[k]) scene.remove(run[k]); }
    const hopPos = (a, b, h, k) => new THREE.Vector3(a.x + (b.x - a.x) * k, a.y + (b.y - a.y) * k + Math.sin(k * Math.PI) * h, a.z + (b.z - a.z) * k);
    const ABC = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789/_-.?=&%#';
    function scramble(from, to, k, seed) {
      if (k <= 0) return from; if (k >= 1) return to;
      const len = Math.round(from.length + (to.length - from.length) * k), done = Math.floor(len * Math.max(0, k * 1.4 - .4));
      let out = ''; for (let i = 0; i < len; i++) out += i < done ? (to[i] ?? '') : ABC[Math.floor(hash(i + seed, Math.floor(k * 40)) * ABC.length)];
      return out;
    }
    function lands(run, t) {
      const up = (a, b) => ease(clamp((t - a) / (b - a))), down = (a, b) => 1 - up(a, b);
      // the miniature's loop: home island always up, hel.ink and the destination rise and sink again
      if (run.type === 'loop') return [[run.rA, 1.1], [run.rH, .7 * up(1.6, 2.2) * down(8.9, 9.8)], [run.rD, 1.3 * up(5.2, 6.1) * down(8.8, 9.8)]];
      const sink = 1 - ease(clamp((t - (run.dur - 1.1)) / 1));
      const L = [[run.rA, 1.1 * up(.2, 1.1) * sink], [run.rH, .7 * up(run.type === 'share' ? 2.4 : 1.6, run.type === 'share' ? 3 : 2.2) * sink]];
      if (run.rB) L.push([run.rB, 1.1 * up(1.2, 2) * sink]);
      const dAt = { hop: 3.2, share: 5, 'pass-ok': 5.2, 'pass-fail': 99 }[run.type];
      L.push([run.rD, 1.3 * up(dAt, dAt + .9) * sink]);
      return L;
    }

    // ------------------------------------------------ the URL, floating in the sea in front of the islands
    const PLACE = { school: 'school', mall: 'mall', government: 'city hall', stadium: 'stadium', tv: 'TV station', concert: 'concert', hospital: 'clinic' };
    const bc = document.createElement('canvas'); bc.width = 4096; bc.height = 160;
    const bctx = bc.getContext('2d'), btex = new THREE.CanvasTexture(bc); btex.colorSpace = THREE.SRGBColorSpace; btex.anisotropy = 4;
    const banner = new THREE.Sprite(new THREE.SpriteMaterial({ map: btex, depthTest: false, transparent: true }));
    const BH = mini ? 1 : small ? 1.3 : .95; banner.scale.set(BH * 4096 / 160, BH, 1); banner.position.set(0, .35, D * (mini ? .3 : .24)); banner.renderOrder = 20; scene.add(banner);
    let bannerKey = '';
    function drawBanner(text, isShort, dark) {
      const key = text + isShort + dark; if (key === bannerKey) return; bannerKey = key;
      const max = mini ? 64 : 92; if (text.length > max) text = text.slice(0, max - 1) + '…';
      bctx.clearRect(0, 0, 4096, 160); bctx.font = `${isShort ? 700 : 500} 64px ui-monospace, SFMono-Regular, Menlo, monospace`;
      const w = bctx.measureText(text).width + 96, x = (4096 - w) / 2;
      const fit = Math.min(1, W * (mini ? .52 : .6) / (BH * w / 160)); banner.scale.set(BH * 4096 / 160 * fit, BH * fit, 1); // long links shrink to stay inside the view
      bctx.fillStyle = dark ? 'rgba(15,18,38,.88)' : 'rgba(255,255,255,.94)'; bctx.strokeStyle = isShort ? '#818cf8' : dark ? 'rgba(255,255,255,.18)' : 'rgba(99,102,241,.25)'; bctx.lineWidth = 4;
      bctx.beginPath(); bctx.roundRect(x, 16, w, 128, 64); bctx.fill(); bctx.stroke();
      bctx.fillStyle = isShort ? (dark ? '#a5b4fc' : '#4f46e5') : dark ? '#e2e8f0' : '#334155'; bctx.textAlign = 'center'; bctx.textBaseline = 'middle'; bctx.fillText(text, 2048, 84);
      btex.needsUpdate = true;
    }

    // ------------------------------------------------ frame
    const story = root.querySelector('[data-hop-story]');
    const mouse = new THREE.Vector2(9, 9), ray = new THREE.Raycaster(), plane = new THREE.Plane(new THREE.Vector3(0, 1, 0), 0), cursor = new THREE.Vector3(2, 0, 1);
    el.addEventListener('pointermove', e => { const b = el.getBoundingClientRect(); mouse.set((e.clientX - b.left) / b.width * 2 - 1, -(e.clientY - b.top) / b.height * 2 + 1); });

    function render(run, t) {
      const dark = isDark(), L = lands(run, t);
      if (mouse.x < 5) { ray.setFromCamera(mouse, cam); ray.ray.intersectPlane(plane, cursor); }
      cursorLight.position.set(cursor.x, 2, cursor.z); cursorLight.color.copy(grad(clamp((cursor.x + W / 2) / W)));
      hemi.intensity = dark ? 1 : 1.5;
      const base = new THREE.Color(dark ? '#1b2140' : '#c7cfe6');
      cells.forEach((c, i) => {
        // land: the highest of the islands at this cell
        let land = 0, w = -1;
        for (const [rg, a] of L) if (a > 0) { const ww = inRegion(rg, c.x, c.z); if (ww > -.12) { const h = a * clamp((ww + .12) / .3) * (.45 + .55 * clamp(ww) ** .7) * (.85 + .3 * c.n); if (h > land) { land = h; w = ww; } } }
        land = Math.round(land / .1) * .1;
        if (land > .25) c.explored = Math.min(1, c.explored + .02);
        // the sea: low, always moving waves, with ripples spreading out from the cursor
        const dc = Math.hypot(c.x - cursor.x, c.z - cursor.z);
        const waves = .07 * Math.sin(c.x * .55 + t * 1.3) + .05 * Math.sin(c.z * .8 - t * 1.1 + c.x * .3) + .03 * Math.sin((c.x + c.z) * 1.7 + t * 2.1);
        const ripple = .14 * Math.sin(dc * 2.6 - t * 5) * Math.exp(-dc / 2.2);
        const top = Math.max(.03, (land > 0 ? land : .06 + waves * .7 + ripple) * c.fade + .03);
        P.set(c.x, top / 2 - .2, c.z); V.set(c.fade, top, c.fade); sea.setMatrixAt(i, M.compose(P, Q, V));
        const near = clamp(1 - dc / 3.4);
        if (land > 0 && w > -.12) C.copy(terrain(clamp(w), c.n)).lerp(base, dark ? .18 : .05);
        else { C.copy(base).lerp(grad(clamp((c.x + W / 2) / W + Math.sin(t * .3) * .1)), clamp(.22 + waves * 1.5 + near * .55 + c.explored * .15 + (dark ? .1 : 0))); if (dark) C.multiplyScalar(.75 + near * .6 + waves); }
        sea.setColorAt(i, C);
      });
      sea.instanceMatrix.needsUpdate = true; sea.instanceColor.needsUpdate = true;

      const ty = run.type, [slug, , long] = run.link, short = `hel.ink/${slug}`;
      const gy = h => Math.round(h / .1) * .1 * .95;
      const A = run.A.clone().setY(gy(1.1 * .9)), H = run.hub.clone().setY(gy(.7 * .9)), Dd = run.D.clone().add(new THREE.Vector3(-1.6, gy(1.3 * .7), 1.1));
      const loop = ty === 'loop', sink = loop ? clamp((t - 8.8) / 1) : clamp((t - (run.dur - 1.1)) / 1), show = loop || (t > .8 && sink < .7);
      run.label.position.set(run.hub.x, 1.7, run.hub.z); run.label.material.opacity = clamp((t - 2) / .4) * (1 - sink);
      run.packet.visible = false; run.lock.visible = false; run.trail.count = 0;
      const face = (a, b) => Math.atan2(-(b.z - a.z), b.x - a.x);
      const trail = (a, b, h, k) => { const n = Math.floor(k * 24); run.trail.count = n; for (let i = 0; i < n; i++) { const p = hopPos(a, b, h, i / 24); run.trail.setMatrixAt(i, M.compose(p.setY(p.y + .2), Q, V.setScalar(1))); } run.trail.instanceMatrix.needsUpdate = true; };
      const sq = k => k > 0 && k < 1 ? 1 + Math.sin(k * Math.PI) * .15 : 1;
      const BS = 1.9; // bunny scale
      let caption = '', url = long;
      if (ty === 'hop') {
        const h1 = ease(clamp((t - 2) / 1)), h2 = ease(clamp((t - 4.4) / 1));
        const pos = t < 4.4 ? hopPos(A, H, 2.4, h1) : hopPos(H, Dd, 2.2, h2);
        placeBunny(run.bunnyA, pos, t < 4.4 ? face(A, H) : face(H, Dd), BS, show, sq(t < 4.4 ? h1 : h2));
        if (t > 2 && t < 3.1) trail(A, H, 2.4, h1); else if (t > 4.4 && t < 5.5) trail(H, Dd, 2.2, h2);
        placeBuilding(run.building, run.D.clone().setY(gy(1.3 * .9)), clamp((t - 3.5) / 1.4) * (1 - sink));
        url = t < 3 ? long : t < 3.8 ? scramble(long, short, (t - 3) / .8, 1) : t < 4.4 ? short : t < 5.2 ? scramble(short, long, (t - 4.4) / .8, 2) : long;
        caption = t < 2 ? 'A bunny has a long link to share' : t < 3 ? 'Hop to hel.ink…' : t < 4.4 ? 'hel.ink makes it short' : t < 5.4 ? 'then on to where the link goes' : `Arrived at the ${PLACE[run.link[1]]} ✓`;
      } else if (ty === 'share') {
        const B = run.B.clone().setY(gy(1.1 * .9));
        placeBunny(run.bunnyA, A, face(A, H), BS, show);
        const k1 = ease(clamp((t - 3) / 1)), k2 = ease(clamp((t - 4) / 1)), h3 = ease(clamp((t - 6.2) / 1));
        if (t > 3 && t < 5) { run.packet.visible = true; run.packet.position.copy(t < 4 ? hopPos(A, H, 1.2, k1) : hopPos(H, B, 1.2, k2)); run.packet.position.y += .6; run.packet.rotation.y = t * 4; }
        placeBunny(run.bunnyB, t < 6.2 ? B : hopPos(B, Dd, 2.4, h3), t < 6.2 ? face(B, H) : face(B, Dd), BS, t > 1.6 && sink < .7, sq(h3));
        if (t > 6.2 && t < 7.2) trail(B, Dd, 2.4, h3);
        placeBuilding(run.building, run.D.clone().setY(gy(1.3 * .9)), clamp((t - 5.3) / 1.4) * (1 - sink));
        url = t < 4 ? long : t < 4.8 ? scramble(long, short, (t - 4) / .8, 3) : t < 6.2 ? short : t < 7 ? scramble(short, long, (t - 6.2) / .8, 4) : long;
        caption = t < 3 ? 'Two bunnies, far apart' : t < 5 ? 'One sends a long link; hel.ink makes it short on the way' : t < 6.2 ? `The other gets ${short}` : t < 7.2 ? 'and hops to where it goes' : `Arrived at the ${PLACE[run.link[1]]} ✓`;
      } else {
        // 2–3 hop to hel.ink · 3–3.8 the link gets short · 3.8 the lock appears · 4.2–5.2 password · 5.2 open (or not)
        // · 6.2–7.2 hop to the destination (or home, when it failed) · loop: 8.4–9.6 hop home, the rest sinks
        const ok = ty === 'pass-ok' || loop, h1 = ease(clamp((t - 2) / 1));
        let pos = hopPos(A, H, 2.4, h1), dir = face(A, H), k = h1;
        if (t > 2 && t < 3.1) trail(A, H, 2.4, h1);
        run.lock.visible = t > 3.8 && (ok ? t < 6.1 : t < run.dur - 1);
        const shake = !ok && t > 5.2 && t < 5.9 ? Math.sin(t * 60) * .07 : 0;
        run.lock.position.set(H.x + .95 + shake, H.y + .8, H.z); run.lock.scale.setScalar(1.6 * clamp((t - 3.8) / .3));
        run.lock.children[0].material.color.set(!ok && t > 5.2 ? '#ef4444' : '#facc15');
        run.lock.userData.shackle.position.y = ok && t > 5.2 ? .58 : .42;
        if (ok) { const h2 = ease(clamp((t - 6.2) / 1)); if (t > 6.2) { pos = hopPos(H, Dd, 2.2, h2); dir = face(H, Dd); k = h2; } if (t > 6.2 && t < 7.3) trail(H, Dd, 2.2, h2); placeBuilding(run.building, run.D.clone().setY(gy(1.3 * .9)), clamp((t - 5.3) / 1.4) * (1 - sink)); }
        else { run.building.visible = false; if (t > 5.9) { const hb = ease(clamp((t - 5.9) / 1)); pos = hopPos(H, A, 1.4, hb); dir = face(H, A); k = hb; } }
        if (loop && t > 8.4) { const hh = ease(clamp((t - 8.4) / 1.2)); pos = hopPos(Dd, A, 3.2, hh); dir = face(Dd, A); k = hh; if (t < 9.6) trail(Dd, A, 3.2, hh); } // home again: the loop starts where it ends
        placeBunny(run.bunnyA, pos, dir, BS, show, sq(k));
        const dots = '•'.repeat(clamp(Math.floor((t - 4.2) / .15), 0, 6));
        url = t < 3 ? long : t < 3.8 ? scramble(long, short, (t - 3) / .8, 5) : short;
        if (ok && t > 6.2) url = t < 7 ? scramble(short, long, (t - 6.2) / .8, 6) : long;
        if (loop && t > 8.4) url = long;
        caption = t < 2 ? 'A bunny has a long link to share' : t < 3 ? 'Hop to hel.ink…' : t < 3.8 ? 'hel.ink makes it short' : t < 4.2 ? 'This link has a password' : t < 5.2 ? `Password ${dots}`
          : ok ? (t < 6.2 ? 'Unlocked ✓' : t < 7.3 ? 'Off to where the link goes' : loop && t > 8.4 ? 'Back home for the next link' : `Arrived at the ${PLACE[run.link[1]]} ✓`)
          : (t < 5.9 ? 'Wrong password ✕' : 'No hop this time');
      }
      if (story) story.textContent = caption;
      drawBanner(url, url === short, dark);
      renderer.render(scene, cam);
    }

    // keep the canvas sized to its box
    new ResizeObserver(() => { const w = el.clientWidth, h = el.clientHeight; renderer.setSize(w, h, false); cam.aspect = w / h; cam.position.z = mini ? 17 : w < 640 ? 22 : 18.5; cam.position.y = mini ? 18 : w < 640 ? 21 : 16.5; cam.lookAt(0, 0, mini ? .6 : .9); cam.updateProjectionMatrix(); }).observe(el);

    let run = makeRun(0), n = 0, t0 = performance.now(), raf = 0, visible = false, paused = 0;
    const still = matchMedia('(prefers-reduced-motion: reduce)').matches;
    // scenario pills: autoplay keeps going, a click plays that scenario now
    const pills = [...root.querySelectorAll('[data-hop-play]')];
    const mark = () => pills.forEach(b => b.setAttribute('aria-pressed', String(b.dataset.hopPlay === run.type)));
    pills.forEach(b => b.addEventListener('click', () => {
      disposeRun(run); run = makeRun(++n, b.dataset.hopPlay); t0 = performance.now(); mark();
      if (still) render(run, 6.2);
    }));
    mark();
    if (still) { render(run, 6.2); return; } // one still moment, no motion
    const loop = (now) => {
      let t = (now - t0) / 1000;
      if (t > run.dur) { if (run.type === 'loop') { t0 = now; t = 0; } else { disposeRun(run); run = makeRun(++n); t0 = now; t = 0; mark(); } }
      render(run, t);
      raf = visible ? requestAnimationFrame(loop) : 0;
    };
    new IntersectionObserver(([e]) => {
      visible = e.isIntersecting;
      if (visible && !raf) { if (paused) t0 += performance.now() - paused; raf = requestAnimationFrame(loop); }
      else if (!visible) paused = performance.now();
    }).observe(root);
}

document.querySelectorAll('[data-hop-scene]').forEach(start);
