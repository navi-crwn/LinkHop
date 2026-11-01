// Home hero: the app logo as 3D blocks, in plain WebGL (no library to
// load). The blocks fly in and settle, float a little, turn slowly and follow
// a drag. Without WebGL the picture behind the canvas stays; with "reduce
// motion" the logo is drawn once, still.
// A canvas with data-morph="phone" (BioHop) goes one step further: once the
// logo has settled, its blocks glide into the outline of a phone and the
// canvas hands over to the HTML phone behind it (data-morph="done").
// data-morph="cycle" (BioHop) never stops: logo -> the word BIO -> the phone -> the canvas fades and the page
// inside the phone is shown (a live iframe next to the canvas) -> the blocks come back, scatter and start over.
// Once the page is shown the cycle stops there, so it can be read, scrolled and clicked; it plays again from the start
// when the stage has left the screen and comes back.
(function () {
  'use strict';
  var scenes = [], scheduled = 0, instances = new WeakMap();
  var preference = matchMedia('(prefers-reduced-motion: reduce)');
  function schedule() {
    if (scheduled || document.hidden || preference.matches || !scenes.some(function (s) { return s.visible && !s.dead; })) return;
    scheduled = requestAnimationFrame(function tick(now) {
      scheduled = 0;
      // The few most visible scenes animate together (the app marks sit side by side and each must settle);
      // anything beyond that waits for a slot.
      var live = scenes.filter(function (s) { return s.visible && !s.dead; }).sort(function (a, b) { return b.ratio - a.ratio; }).slice(0, 4);
      scenes.forEach(function (s) { var state = String(live.indexOf(s) !== -1); if (s.canvas.dataset.animating !== state) s.canvas.dataset.animating = state; });
      if (live.length && !document.hidden && !preference.matches) { live.forEach(function (s) { s.draw(now); }); schedule(); }
    });
  }
  function pause() {
    cancelAnimationFrame(scheduled); scheduled = 0;
    scenes.forEach(function (s) { s.canvas.dataset.animating = 'false'; });
  }
  document.addEventListener('visibilitychange', function () { if (document.hidden) pause(); else schedule(); });
  preference.addEventListener('change', function () {
    pause();
    scenes.forEach(function (s) { if (preference.matches) s.canvas.classList.remove('on'); });
    if (!preference.matches) init();
    schedule();
  });

  // ---------- the logos: one character per cell; '.' empty, '#' gradient, 'w' white, 'p' pink ----------
  var MAPS = {
    notehop: ['..#..#..', '..#..#..', '.#####..', '.######.', '.#...##.', '.######.', '.#..###.', '.######.'],
    hub: ['.#..#...', '.##.#...', '..####..', '..#####.', '######..', '.#####..', '##..###.', '#....###'],
    // the BioHop mark: a rounded square with three lines
    biohop: ['.#######.', '#########', '##wwwww##', '#########', '##wwwww##', '#########', '##www####', '#########', '.#######.'],
    // the PixelHop mark: the pixel rabbit
    pixelhop: ['.#....#.', '.#....#.', '.##..##.', '.######.', '##.##.##', '########', '########', '.######.']
  };
  // the LinkHop mark: a rabbit head, two tall ears over a ring-shaped face
  MAPS.linkhop = ['..#...#..', '..#...#..', '..#...#..', '..#####..', '.##...##.', '##.....##', '##.....##', '.##...##.', '..#####..'];
  var STOPS = {
    notehop: [[.639, .902, .208], [.133, .827, .933], [.545, .361, .965]],
    hub: [[.13, .83, .93], [.55, .36, .97], [.96, .28, .65]],
    linkhop: [[.22, .74, 1], [.3, .4, .95], [.55, .36, .97]],
    biohop: [[.85, .27, .94], [.7, .33, .96], [.55, .36, .97]],
    pixelhop: [[.64, .9, .21], [.13, .83, .93], [.55, .36, .97]]
  };
  var WHITE = [.97, .97, 1], PINK = [.957, .447, .714], DARK = [.11, .12, .2];

  // The phone the BioHop blocks become: a 9×16 outline with an avatar, three lines and a button inside.
  function phoneCells(n) {
    var W = 9, H = 16, out = [], inner = [];
    for (var r = 0; r < H; r++) for (var c = 0; c < W; c++) {
      var edge = r === 0 || r === H - 1 || c === 0 || c === W - 1;
      if (edge && ((r === 0 || r === H - 1) && (c === 0 || c === W - 1))) continue; // rounded corners
      if (edge) out.push([c, r, 'edge']);
      else inner.push([c, r]);
    }
    var screen = [];
    inner.forEach(function (p) {
      var c = p[0], r = p[1];
      if (r >= 2 && r <= 4 && c >= 3 && c <= 5) screen.push([c, r, 'avatar']);
      else if ((r === 7 || r === 9 || r === 11) && c >= 2 && c <= 6) screen.push([c, r, 'line']);
      else if (r === 13 && c >= 2 && c <= 6) screen.push([c, r, 'button']);
    });
    var all = out.concat(screen);
    // Any block left over sits behind the screen, dark like the frame.
    var i = 0;
    while (all.length < n && i < inner.length) { var p = inner[i++]; if (!screen.some(function (s) { return s[0] === p[0] && s[1] === p[1]; })) all.push([p[0], p[1], 'back']); }
    return all.slice(0, n).map(function (p) { return { at: [p[0] - (W - 1) / 2, (H - 1) / 2 - p[1], p[2] === 'back' ? -.6 : 0], kind: p[2] }; });
  }

  // The word BIO in a 3x5 pixel font, two layers deep: one block per lit cell, the second layer behind it.
  var LETTERS = { B: ['##.', '#.#', '##.', '#.#', '##.'], I: ['###', '.#.', '.#.', '.#.', '###'], O: ['###', '#.#', '#.#', '#.#', '###'] };
  function wordCells(n) {
    var cells = [], x = 0;
    'BIO'.split('').forEach(function (ch, li) {
      LETTERS[ch].forEach(function (row, r) { row.split('').forEach(function (c, ci) { if (c === '#') cells.push([x + ci, r, li]); }); });
      x += 4;
    });
    var W = 11, H = 5, out = [];
    cells.forEach(function (c) { out.push({ at: [c[0] - (W - 1) / 2, (H - 1) / 2 - c[1], 0], letter: c[2], frac: (c[0] + c[1]) / (W + H) }); });
    cells.forEach(function (c) { out.push({ at: [c[0] - (W - 1) / 2, (H - 1) / 2 - c[1], -1], letter: c[2], frac: (c[0] + c[1]) / (W + H) }); });
    return out.slice(0, n);
  }

  function mount(cv, options) {
  options = options || {};
  if (!cv) return;
  if (instances.has(cv)) return instances.get(cv);
  if (preference.matches) return;
  var mobile = options.preset !== 'notehop' && matchMedia('(max-width: 600px)').matches;
  var gl = cv.getContext('webgl', { antialias: true, alpha: true, preserveDrawingBuffer: true });
  if (!gl) return;
  var reduce = false, events = new AbortController();
  var instanced = gl.getExtension('ANGLE_instanced_arrays');
  function listen(type, callback) { cv.addEventListener(type, callback, {signal: events.signal}); }

  var VS = 'attribute vec3 p;attribute vec3 n;uniform mat4 M;uniform mat4 VP;varying vec3 vn;varying vec3 vw;' +
    'void main(){vec4 w=M*vec4(p,1.);vw=w.xyz;vn=mat3(M)*n;gl_Position=VP*w;}';
  // Soft studio light: sky/ground fill, a key light, a cyan back light, a highlight and a violet rim.
  var FS = 'precision mediump float;uniform vec3 col;uniform vec3 eye;varying vec3 vn;varying vec3 vw;' +
    'void main(){vec3 N=normalize(vn);vec3 V=normalize(eye-vw);vec3 L=normalize(vec3(.45,.75,.9));vec3 B=normalize(vec3(-.7,-.35,-.6));' +
    'float d=max(dot(N,L),0.);float b=max(dot(N,B),0.);float hemi=.55+.45*N.y;float s=pow(max(dot(N,normalize(L+V)),0.),56.);' +
    'float rim=pow(1.-max(dot(N,V),0.),2.5);' +
    'vec3 c=col*(.38+.34*hemi)+col*d*.5+vec3(.13,.83,.93)*b*.3+vec3(1.)*s*.55+vec3(.6,.5,1.)*rim*.32;gl_FragColor=vec4(c,1.);}';
  if (instanced) {
    VS = VS.replace('uniform mat4 M;', 'attribute mat4 M;attribute vec3 color;varying vec3 col;').replace('void main(){', 'void main(){col=color;');
    FS = FS.replace('uniform vec3 col;', 'varying vec3 col;');
  }
  function shader(type, src) { var s = gl.createShader(type); gl.shaderSource(s, src); gl.compileShader(s); return s; }
  var prog = gl.createProgram();
  var vertex = shader(gl.VERTEX_SHADER, VS), fragment = shader(gl.FRAGMENT_SHADER, FS);
  gl.attachShader(prog, vertex);
  gl.attachShader(prog, fragment);
  gl.linkProgram(prog);
  gl.deleteShader(vertex); gl.deleteShader(fragment);
  if (!gl.getProgramParameter(prog, gl.LINK_STATUS)) { gl.deleteProgram(prog); return; }
  gl.useProgram(prog);
  var U = {};
  ['M', 'VP', 'col', 'eye'].forEach(function (k) { U[k] = gl.getUniformLocation(prog, k); });
  var aP = gl.getAttribLocation(prog, 'p'), aN = gl.getAttribLocation(prog, 'n');
  var aM = instanced && gl.getAttribLocation(prog, 'M'), aC = instanced && gl.getAttribLocation(prog, 'color');
  var instanceBuffer = instanced && gl.createBuffer(), batch = new Float32Array(160 * 19), batchCount = 0;
  if (instanced) {
    [aM, aM + 1, aM + 2, aM + 3, aC].forEach(function (a) { gl.enableVertexAttribArray(a); instanced.vertexAttribDivisorANGLE(a, 1); });
  }

  // ---------- geometry: a cube (slightly bevel-less, the light does the work) and an octahedron ----------
  function mesh(data) {
    var b = gl.createBuffer();
    gl.bindBuffer(gl.ARRAY_BUFFER, b);
    gl.bufferData(gl.ARRAY_BUFFER, new Float32Array(data), gl.STATIC_DRAW);
    return { buf: b, count: data.length / 6 };
  }
  function cube(s) {
    var out = [], F = [[0, 0, 1], [0, 0, -1], [1, 0, 0], [-1, 0, 0], [0, 1, 0], [0, -1, 0]];
    F.forEach(function (n) {
      var u = n[0] ? [0, 1, 0] : [1, 0, 0], v = [n[1] * u[2] - n[2] * u[1], n[2] * u[0] - n[0] * u[2], n[0] * u[1] - n[1] * u[0]];
      var c = [[-1, -1], [1, -1], [1, 1], [-1, -1], [1, 1], [-1, 1]];
      c.forEach(function (q) {
        for (var i = 0; i < 3; i++) out.push(s * (n[i] + q[0] * u[i] + q[1] * v[i]));
        out.push(n[0], n[1], n[2]);
      });
    });
    return out;
  }
  function octa(s) {
    var P = [[1, 0, 0], [-1, 0, 0], [0, 1, 0], [0, -1, 0], [0, 0, 1], [0, 0, -1]], out = [];
    [[0, 2, 4], [2, 1, 4], [1, 3, 4], [3, 0, 4], [2, 0, 5], [1, 2, 5], [3, 1, 5], [0, 3, 5]].forEach(function (f) {
      var a = P[f[0]], b = P[f[1]], c = P[f[2]], n = [a[0] + b[0] + c[0], a[1] + b[1] + c[1], a[2] + b[2] + c[2]], l = Math.hypot(n[0], n[1], n[2]);
      [a, b, c].forEach(function (p) { out.push(p[0] * s, p[1] * s, p[2] * s, n[0] / l, n[1] / l, n[2] / l); });
    });
    return out;
  }
  var CUBE = mesh(cube(.46)), GEM = mesh(octa(1));

  // ---------- tiny matrix helpers (column-major) ----------
  function mul(a, b) {
    var o = new Float32Array(16);
    for (var c = 0; c < 4; c++) for (var r = 0; r < 4; r++) {
      o[c * 4 + r] = a[r] * b[c * 4] + a[4 + r] * b[c * 4 + 1] + a[8 + r] * b[c * 4 + 2] + a[12 + r] * b[c * 4 + 3];
    }
    return o;
  }
  function T(x, y, z) { return new Float32Array([1, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1, 0, x, y, z, 1]); }
  function S(k) { return new Float32Array([k, 0, 0, 0, 0, k, 0, 0, 0, 0, k, 0, 0, 0, 0, 1]); }
  function RX(a) { var c = Math.cos(a), s = Math.sin(a); return new Float32Array([1, 0, 0, 0, 0, c, s, 0, 0, -s, c, 0, 0, 0, 0, 1]); }
  function RY(a) { var c = Math.cos(a), s = Math.sin(a); return new Float32Array([c, 0, -s, 0, 0, 1, 0, 0, s, 0, c, 0, 0, 0, 0, 1]); }
  function persp(fov, asp, n, f) {
    var t = 1 / Math.tan(fov / 2), nf = 1 / (n - f);
    return new Float32Array([t / asp, 0, 0, 0, 0, t, 0, 0, 0, 0, (f + n) * nf, -1, 0, 0, 2 * f * n * nf, 0]);
  }

  // ---------- the logo as blocks ----------
  var preset = options.preset || 'notehop';
  var MAP = MAPS[preset] || MAPS.notehop, stops = options.colors && options.colors.length === 3 ? options.colors : STOPS[preset] || STOPS.notehop;
  var W = MAP[0].length, H = MAP.length, extent = Math.max(W, H);
  function grad(t) { var a = t < .5 ? stops[0] : stops[1], b = t < .5 ? stops[1] : stops[2], k = t < .5 ? t * 2 : (t - .5) * 2; return [0, 1, 2].map(function (i) { return a[i] + (b[i] - a[i]) * k; }); }
  function rnd(a) { return (Math.random() - .5) * a; }
  var blocks = [];
  MAP.forEach(function (row, r) {
    row.split('').forEach(function (ch, c) {
      if (ch === '.') return;
      var col = ch === 'w' ? WHITE : ch === 'p' ? PINK : grad((c + r) / (W + H - 2));
      if (preset === 'notehop' && r === 2 && c === 5) col = PINK;
      blocks.push({ home: [c - (W - 1) / 2, (H - 1) / 2 - r, 0], from: [rnd(26), rnd(18), rnd(14)], spin: [Math.random() * 6, Math.random() * 6],
        delay: Math.random() * .5, col: col });
    });
  });
  var gems = [[[.133, .827, .933], -6, 4, -2, .5], [[.655, .545, .98], 6.2, -3.5, -1, .62], [[.957, .447, .714], 5.4, 4.6, -3, .4], [[.639, .902, .208], -5.6, -4.4, 0, .45], [[.506, .549, .973], 3.4, -6.2, -4, .36]];
  var gemScale = extent / 8;

  // The phone morph (BioHop): each block gets a place in the phone and a colour for it.
  var morph = options.morph || cv.dataset.morph;
  var morphK = 0, morphAt = null, morphDone = false, phone = null;
  var cycle = morph === 'cycle', word = null;
  if (morph === 'phone' || cycle) {
    phone = phoneCells(blocks.length);
    blocks.forEach(function (b, i) {
      var p = phone[i % phone.length];
      b.to = p.at;
      b.toCol = p.kind === 'edge' || p.kind === 'back' ? DARK : p.kind === 'avatar' ? stops[0] : p.kind === 'button' ? stops[2] : WHITE;
    });
  }
  if (cycle) {
    word = wordCells(blocks.length);
    blocks.forEach(function (b, i) {
      var w = word[i];
      // a block with no cell in the word waits behind it, in the colour of the end of the gradient
      b.word = w ? w.at : [b.home[0] * .3, b.home[1] * .3, -2.4];
      b.wordCol = w ? grad(w.frac) : stops[2];
    });
  }
  // cycle clock: [time ms, stage] keyframes; the stage is where the blocks are at that moment.
  var KEYS = [[0, 'home'], [1600, 'home'], [3000, 'word'], [5200, 'word'], [6800, 'phone'], [7200, 'phone'], [16200, 'phone'], [17600, 'scatter'], [19000, 'home']];
  var REVEAL = [7200, 7700, 15700, 16200]; // canvas out, in the page, canvas back
  var cycleT = 0, lastNow = null;
  function restartCycle() { cycleT = 0; lastNow = null; }

  // ---------- drawing ----------
  var eye = [0, 0, 22 * extent / 8], eyeHome = eye[2], VP, dpr = Math.min(window.devicePixelRatio || 1, mobile ? 1.25 : 2);
  function size() {
    var w = cv.clientWidth, h = cv.clientHeight, Wp = Math.round(w * dpr), Hp = Math.round(h * dpr);
    // Setting the size clears the canvas; only do it when the size really changed.
    if (cv.width !== Wp || cv.height !== Hp) { cv.width = Wp; cv.height = Hp; }
    gl.viewport(0, 0, cv.width, cv.height);
    VP = mul(persp(32 * Math.PI / 180, w / Math.max(1, h), .1, 200), T(-eye[0], -eye[1], -eye[2]));
  }
  function draw(m, model, col) {
    if (instanced) { batch.set(model, batchCount * 19); batch.set(col, batchCount * 19 + 16); batchCount++; return; }
    gl.bindBuffer(gl.ARRAY_BUFFER, m.buf);
    gl.vertexAttribPointer(aP, 3, gl.FLOAT, false, 24, 0);
    gl.vertexAttribPointer(aN, 3, gl.FLOAT, false, 24, 12);
    gl.uniformMatrix4fv(U.M, false, model);
    gl.uniform3fv(U.col, col);
    gl.drawArrays(gl.TRIANGLES, 0, m.count);
  }
  function flush(m) {
    if (!instanced) return;
    gl.bindBuffer(gl.ARRAY_BUFFER, m.buf);
    gl.vertexAttribPointer(aP, 3, gl.FLOAT, false, 24, 0);
    gl.vertexAttribPointer(aN, 3, gl.FLOAT, false, 24, 12);
    gl.bindBuffer(gl.ARRAY_BUFFER, instanceBuffer);
    gl.bufferData(gl.ARRAY_BUFFER, batch.subarray(0, batchCount * 19), gl.DYNAMIC_DRAW);
    for (var i = 0; i < 4; i++) gl.vertexAttribPointer(aM + i, 4, gl.FLOAT, false, 76, i * 16);
    gl.vertexAttribPointer(aC, 3, gl.FLOAT, false, 76, 64);
    instanced.drawArraysInstancedANGLE(gl.TRIANGLES, 0, m.count, batchCount);
    batchCount = 0;
  }
  gl.enableVertexAttribArray(aP);
  gl.enableVertexAttribArray(aN);
  gl.enable(gl.DEPTH_TEST);
  gl.enable(gl.CULL_FACE);

  var rotY = 0, rotX = 0, vel = .004, drag = null, tiltX = 0, tiltY = 0, t0 = null, settled = false, lastFrame = -Infinity;
  function mix(a, b, k) { return [a[0] + (b[0] - a[0]) * k, a[1] + (b[1] - a[1]) * k, a[2] + (b[2] - a[2]) * k]; }
  function frame(now) {
    if (scene.dead || preference.matches || document.hidden) return;
    if (mobile && now - lastFrame < 32) return;
    lastFrame = now;
    if (t0 === null) t0 = now;
    var t = (now - t0) / 1000, all = true;
    var wts = null, phoneW = 0;
    if (cycle) {
      var dtms = lastNow === null ? 0 : Math.min(80, now - lastNow); lastNow = now;
      var shownNow = cycleT >= REVEAL[1] && cycleT < REVEAL[2];
      if (settled && !drag && !shownNow) cycleT = (cycleT + dtms) % 19000;
      var i = 0; while (i < KEYS.length - 2 && cycleT >= KEYS[i + 1][0]) i++;
      var a = KEYS[i], bk = KEYS[i + 1], e = Math.min(1, Math.max(0, (cycleT - a[0]) / (bk[0] - a[0]))); e = e * e * (3 - 2 * e);
      wts = { home: 0, word: 0, phone: 0, scatter: 0 };
      wts[a[1]] += 1 - e; wts[bk[1]] += e;
      phoneW = wts.phone;
      var wordW = wts.word;
      var op = cycleT < REVEAL[0] ? 1 : cycleT < REVEAL[1] ? 1 - (cycleT - REVEAL[0]) / (REVEAL[1] - REVEAL[0]) : cycleT < REVEAL[2] ? 0 : cycleT < REVEAL[3] ? (cycleT - REVEAL[2]) / (REVEAL[3] - REVEAL[2]) : 1;
      cv.style.opacity = String(op);
      var shown = op < .3 ? 'true' : 'false';
      if (cv.dataset.reveal !== shown) { cv.dataset.reveal = shown; cv.dispatchEvent(new CustomEvent('hop-hero-reveal', { bubbles: true, detail: { shown: shown === 'true' } })); }
      morphK = Math.max(phoneW, wordW);
      eye[2] = eyeHome + (22 * 16 / 8 - eyeHome) * phoneW; size();
    }
    // The morph: 1.4 s after the logo has settled, 1.6 s to the phone, then the HTML phone takes over.
    if (phone && !cycle && settled && !morphDone && !drag) {
      if (morphAt === null) morphAt = now + 1400;
      morphK = Math.min(1, Math.max(0, (now - morphAt) / 1600));
      morphK = morphK * morphK * (3 - 2 * morphK);
      eye[2] = eyeHome + (22 * 16 / 8 - eyeHome) * morphK; size();
      cv.style.opacity = morphK < .8 ? '' : String(1 - (morphK - .8) / .2);
      if (morphK >= 1) { morphDone = true; cv.dataset.morph = 'done'; cv.dispatchEvent(new CustomEvent('hop-hero-morph')); }
    }
    gl.clearColor(0, 0, 0, 0);
    gl.clear(gl.COLOR_BUFFER_BIT | gl.DEPTH_BUFFER_BIT);
    gl.uniformMatrix4fv(U.VP, false, VP);
    gl.uniform3fv(U.eye, eye);
    if (!drag && !reduce) { vel += (.004 - vel) * .02; rotY += vel * (mobile ? 2 : 1); rotX *= .96; }
    var swing = Math.sin(rotY) * .6 * (1 - morphK), bob = (reduce ? 0 : Math.sin(t * .6) * .05) * (1 - morphK);
    var group = mul(RX(rotX * .4 + tiltX + bob), RY(swing + tiltY));
    blocks.forEach(function (b) {
      var k = reduce ? 1 : Math.min(1, Math.max(0, (t - b.delay) / 1.4)), e = 1 - Math.pow(1 - k, 3);
      if (k < 1) all = false;
      var p = [0, 1, 2].map(function (i) { return b.from[i] + (b.home[i] - b.from[i]) * e; });
      if (k >= 1 && !reduce) p[2] = Math.sin(t * 1.6 + b.home[0] * .7 + b.home[1] * .5) * .18 * (1 - morphK);
      var col = b.col;
      if (wts) {
        var base = p, sc = [b.from[0] * 1.15, b.from[1] * 1.15, b.from[2] * 1.15];
        p = [0, 1, 2].map(function (j) { return base[j] * wts.home + b.word[j] * wts.word + b.to[j] * wts.phone + sc[j] * wts.scatter; });
        col = [0, 1, 2].map(function (j) { return b.col[j] * (wts.home + wts.scatter) + b.wordCol[j] * wts.word + b.toCol[j] * wts.phone; });
        if (wts.phone < .5) p[2] += Math.sin(t * 1.6 + b.home[0] * .7) * .12 * (1 - morphK);
      } else if (morphK > 0) { p = mix(p, b.to, morphK); col = mix(b.col, b.toCol, Math.min(1, morphK * 1.5)); }
      draw(CUBE, mul(group, mul(T(p[0], p[1], p[2]), mul(RX(b.spin[0] * (1 - e)), RY(b.spin[1] * (1 - e))))), col);
    });
    flush(CUBE);
    if (morphK < 1) gems.forEach(function (g, i) {
      var y = g[2] + (reduce ? 0 : Math.sin(t + i) * .35);
      draw(GEM, mul(T(g[1] * gemScale, y * gemScale, g[3]), mul(mul(RX(reduce ? .4 : t * (.4 + i * .1)), RY(reduce ? .6 : t * .7)), S(g[4] * gemScale * (1 - morphK)))), g[0]);
    });
    flush(GEM);
    settled = all;
    if (cv.dataset.settled !== String(settled)) cv.dataset.settled = String(settled);
    if (!cv.classList.contains('on')) cv.classList.add('on');
  }
  function kick() { schedule(); }

  listen('pointerdown', function (e) { if (options.interactive === false || preference.matches || morphDone) return; drag = { x: e.clientX, y: e.clientY }; cv.setPointerCapture(e.pointerId); });
  listen('pointermove', function (e) {
    if (options.interactive === false || preference.matches) return;
    var r = cv.getBoundingClientRect();
    tiltY = ((e.clientX - r.left) / r.width - .5) * .5; tiltX = ((e.clientY - r.top) / r.height - .5) * .35;
    if (drag) { vel = (e.clientX - drag.x) * .004; rotY += vel; rotX += (e.clientY - drag.y) * .003; drag = { x: e.clientX, y: e.clientY }; }
    kick();
  });
  listen('pointerup', function () { drag = null; });
  listen('pointercancel', function () { drag = null; });
  listen('lostpointercapture', function () { drag = null; });
  // After a resize the cleared canvas is redrawn at once, even while another
  // canvas on the page holds the animation slot.
  var resize = new ResizeObserver(function () { size(); if (cv.classList.contains('on')) { lastFrame = -Infinity; frame(typeof performance === 'undefined' ? 0 : performance.now()); } kick(); });
  resize.observe(cv);
  listen('keydown', function (e) {
    if (options.interactive === false || preference.matches || !['ArrowLeft','ArrowRight','ArrowUp','ArrowDown','Home'].includes(e.key)) return;
    e.preventDefault();
    if (e.key === 'Home') { rotX = 0; rotY = 0; }
    else if (e.key === 'ArrowLeft') rotY -= .2;
    else if (e.key === 'ArrowRight') rotY += .2;
    else rotX += e.key === 'ArrowUp' ? -.2 : .2;
    kick();
  });
  // Stop drawing while it is off screen or the tab is hidden.
  var scene = { canvas: cv, draw: frame, visible: false, ratio: 0, dead: false };
  scenes.push(scene);
  var observer = new IntersectionObserver(function (es) {
    if (scene.visible && !es[0].isIntersecting && cycle) restartCycle();
    scene.visible = es[0].isIntersecting;
    scene.ratio = es[0].intersectionRatio;
    schedule();
  }, { threshold: [0,.25,.5,.75,1] });
  observer.observe(cv);
  size();
  var api = { settled: function () { return settled || preference.matches; }, render: frame, destroy: function () {
    if (scene.dead) return;
    scene.dead = true; observer.disconnect(); resize.disconnect(); events.abort();
    scenes = scenes.filter(function (s) { return s !== scene; });
    instances.delete(cv); delete cv.dataset.mounted;
    cv.dataset.animating = 'false'; cv.classList.remove('on');
    gl.deleteBuffer(CUBE.buf); gl.deleteBuffer(GEM.buf); if (instanceBuffer) gl.deleteBuffer(instanceBuffer); gl.deleteProgram(prog);
  } };
  listen('webglcontextlost', function (e) { e.preventDefault(); api.destroy(); });
  instances.set(cv, api); cv.dataset.mounted = 'true';
  return api;
  }
  window.HopHero3D = { mount: mount, maps: MAPS };
  var lazy = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (!entry.isIntersecting) return;
      var api = mount(entry.target, {preset: entry.target.dataset.hopHero || 'notehop', interactive: true});
      if (api || !preference.matches) lazy.unobserve(entry.target);
      if (entry.target.id === 'h3d') window.NHHero3D = api || {settled: function () { return true; }};
    });
  }, {rootMargin:'160px'});
  function init() { document.querySelectorAll('[data-hop-hero]').forEach(function (cv) { if (!instances.has(cv)) { lazy.unobserve(cv); lazy.observe(cv); } }); }
  // Let text, fonts and the fallback paint before allocating the live scene.
  function afterPaint() { requestAnimationFrame(function () { requestAnimationFrame(init); }); }
  if (document.readyState === 'complete') afterPaint(); else addEventListener('load', afterPaint, {once:true});
})();
