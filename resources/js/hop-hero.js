// Home page hero: the LinkHop bunny (the logo's head, in 3D) hops from a short link to its
// destination. Smooth shapes only, no pixels. Loaded on the home page alone; skipped without
// WebGL (the static mark stays), frozen mid-hop under prefers-reduced-motion, paused off screen.
import {
    CapsuleGeometry, Color, DirectionalLight, Group, HemisphereLight, Mesh, MeshPhysicalMaterial,
    MeshStandardMaterial, PerspectiveCamera, Scene, SphereGeometry, TorusGeometry, WebGLRenderer,
} from 'three';
import { RoundedBoxGeometry } from 'three/addons/geometries/RoundedBoxGeometry.js';

const SKY = new Color('#0ea5e9'), INDIGO = new Color('#6366f1'), FUCHSIA = new Color('#d946ef');
const grad = (t) => (t < 0.5 ? SKY.clone().lerp(INDIGO, t * 2) : INDIGO.clone().lerp(FUCHSIA, (t - 0.5) * 2));

function bunny() {
    const g = new Group();
    const glossy = (c) => new MeshPhysicalMaterial({ color: c, roughness: 0.25, clearcoat: 1, clearcoatRoughness: 0.2 });
    // the head is the logo's "o": a thick ring
    const head = new Mesh(new TorusGeometry(1, 0.42, 32, 72), glossy(grad(0.45)));
    head.scale.set(1, 0.94, 1);
    g.add(head);
    [-1, 1].forEach((s) => {
        const ear = new Mesh(new CapsuleGeometry(0.34, 1.6, 12, 24), glossy(grad(s < 0 ? 0.15 : 0.8)));
        ear.position.set(s * 0.45, 2.1, 0);
        ear.rotation.z = -s * 0.22;
        g.add(ear);
    });
    g.rotation.z = -0.17; // the italic slant of the wordmark
    return g;
}

function pad(x, color) {
    const m = new Mesh(new RoundedBoxGeometry(4.6, 0.5, 2.6, 4, 0.22), new MeshStandardMaterial({ color, roughness: 0.5, transparent: true, opacity: 0.9 }));
    m.position.set(x, -2.6, 0);
    return m;
}

export default function start(el) {
    const canvas = document.createElement('canvas');
    let renderer;
    try {
        renderer = new WebGLRenderer({ canvas, antialias: true, alpha: true });
    } catch (e) {
        return; // no WebGL: keep the static fallback
    }
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    el.querySelector('[data-hop-fallback]')?.remove();
    el.prepend(canvas);
    canvas.className = 'block h-full w-full';
    canvas.setAttribute('aria-hidden', 'true');

    const scene = new Scene();
    const cam = new PerspectiveCamera(30, 1, 0.1, 100);
    cam.position.set(0, 1.4, 21);
    cam.lookAt(0, 0.4, 0);
    scene.add(new HemisphereLight(0xffffff, 0xc7d2fe, 1.7));
    const sun = new DirectionalLight(0xffffff, 1.8);
    sun.position.set(4, 8, 9);
    scene.add(sun);

    scene.add(pad(-7.5, '#c7d2fe'), pad(7.5, '#f5d0fe'));
    const hopper = bunny();
    hopper.scale.setScalar(0.9);
    scene.add(hopper);
    // dotted trail along the hop
    const dots = [];
    for (let i = 0; i < 15; i++) {
        const d = new Mesh(new SphereGeometry(0.09, 12, 12), new MeshStandardMaterial({ color: grad(i / 14), transparent: true }));
        const k = (i + 1) / 16;
        d.position.set(-7.5 + 15 * k, -1.2 + Math.sin(k * Math.PI) * 4.8, 0);
        scene.add(d);
        dots.push(d);
    }

    const resize = () => {
        const w = el.clientWidth, h = el.clientHeight;
        renderer.setSize(w, h, false);
        cam.aspect = w / h;
        // keep both pads in view on narrow screens
        cam.position.z = w < 640 ? 30 : 21;
        cam.updateProjectionMatrix();
    };
    resize();
    new ResizeObserver(resize).observe(el);

    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const PERIOD = 2400;
    const frame = (t) => {
        // 0..0.15 crouch, 0.15..0.75 flight, 0.75..1 land and settle
        const p = reduce ? 0.45 : (t % PERIOD) / PERIOD;
        const f = Math.min(1, Math.max(0, (p - 0.15) / 0.6));
        const e = f < 0.5 ? 2 * f * f : 1 - Math.pow(-2 * f + 2, 2) / 2;
        const x = -7.5 + 15 * e, y = -1.45 + Math.sin(e * Math.PI) * 4.6;
        const squash = p < 0.15 ? 1 - Math.sin((p / 0.15) * Math.PI) * 0.18 : p > 0.75 ? 1 - Math.sin(((p - 0.75) / 0.25) * Math.PI) * 0.14 : 1 + Math.sin(f * Math.PI) * 0.08;
        hopper.position.set(x, y, 0);
        hopper.scale.set(0.9 / Math.sqrt(squash), 0.9 * squash, 0.9);
        hopper.rotation.y = Math.sin(t / 900) * 0.35;
        hopper.rotation.z = -0.17 - (f > 0 && f < 1 ? Math.cos(e * Math.PI) * 0.25 : 0);
        dots.forEach((d, i) => { d.material.opacity = (i + 1) / 16 < e ? 0.9 : 0.18; });
        renderer.render(scene, cam);
    };

    let raf = 0, visible = false;
    const loop = (t) => { frame(t); raf = visible && !reduce ? requestAnimationFrame(loop) : 0; };
    new IntersectionObserver(([entry]) => {
        visible = entry.isIntersecting;
        if (visible && !raf) raf = requestAnimationFrame(loop);
    }).observe(el);
    frame(0);
}

const el = document.querySelector('[data-hop-scene]');
if (el) start(el);
