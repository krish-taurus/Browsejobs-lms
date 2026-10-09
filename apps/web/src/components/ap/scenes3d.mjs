/**
 * 3D process scenes for the Apple-direction pages (ported from the approved
 * demo's three-scenes.js to the site's own `three`). Monochrome studio look:
 * white ceramic, grey, gloss black. Text never lives in the canvas; every
 * scene sits beside real HTML that says the same thing. Scenes hook into the
 * scroll engine (effects.mjs) through host.__3d(progress).
 *
 * Light intensities are ×π versus the r128 original: three ≥ r155 uses
 * physically based light units, and this keeps the approved studio look.
 */
import * as T from "three";

const clamp = (v, a = 0, b = 1) => Math.min(b, Math.max(a, v));
const smooth = (t) => t * t * (3 - 2 * t);
const PI = Math.PI;

function studio(host, disposers) {
  const renderer = new T.WebGLRenderer({ antialias: true, alpha: true, powerPreference: "high-performance" });
  renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.5));
  renderer.shadowMap.enabled = true;
  renderer.shadowMap.type = T.PCFSoftShadowMap;
  renderer.domElement.setAttribute("aria-hidden", "true");
  host.appendChild(renderer.domElement);

  const scene = new T.Scene();
  const camera = new T.PerspectiveCamera(32, 1, 0.1, 100);
  scene.add(new T.HemisphereLight(0xffffff, 0xd6d6dc, 0.62 * PI));
  const key = new T.DirectionalLight(0xffffff, 0.75 * PI);
  key.position.set(4, 9, 6);
  key.castShadow = true;
  key.shadow.mapSize.set(1024, 1024);
  key.shadow.radius = 6;
  Object.assign(key.shadow.camera, { left: -12, right: 12, top: 12, bottom: -12, near: 1, far: 30 });
  scene.add(key);
  const rim = new T.DirectionalLight(0xffffff, 0.35 * PI);
  rim.position.set(-6, 4, -6);
  scene.add(rim);

  const floor = new T.Mesh(new T.PlaneGeometry(60, 60), new T.ShadowMaterial({ opacity: 0.09 }));
  floor.rotation.x = -Math.PI / 2;
  floor.receiveShadow = true;
  scene.add(floor);

  const mats = {
    white: new T.MeshPhysicalMaterial({ color: 0xf7f7f9, roughness: 0.38, clearcoat: 0.6, clearcoatRoughness: 0.25 }),
    grey: new T.MeshPhysicalMaterial({ color: 0xc9c9cf, roughness: 0.5, clearcoat: 0.3 }),
    line: new T.MeshStandardMaterial({ color: 0x8e8e94, roughness: 0.7 }),
    black: new T.MeshPhysicalMaterial({ color: 0x141416, roughness: 0.3, clearcoat: 1, clearcoatRoughness: 0.1 }),
  };

  const resize = () => {
    const w = host.clientWidth || 1;
    const h = host.clientHeight || 1;
    renderer.setSize(w, h, false);
    camera.aspect = w / h;
    camera.updateProjectionMatrix();
  };
  resize();
  const ro = new ResizeObserver(resize);
  ro.observe(host);
  disposers.push(() => {
    ro.disconnect();
    scene.traverse((o) => {
      o.geometry?.dispose?.();
      const ms = Array.isArray(o.material) ? o.material : o.material ? [o.material] : [];
      ms.forEach((m) => m.dispose?.());
    });
    renderer.dispose();
    renderer.domElement.remove();
  });
  return { renderer, scene, camera, mats };
}

const shadowed = (m) => {
  m.castShadow = true;
  m.receiveShadow = true;
  return m;
};

function cvSheet(m) {
  const g = new T.Group();
  g.add(shadowed(new T.Mesh(new T.BoxGeometry(1.25, 1.65, 0.07), m.white)));
  const photo = new T.Mesh(new T.BoxGeometry(0.34, 0.34, 0.08), m.black);
  photo.position.set(-0.36, 0.5, 0.04);
  g.add(photo);
  [[0.18, 0.56, 0.5], [0.18, 0.42, 0.5], [0, 0.08, 0.95], [0, -0.12, 0.95], [0, -0.32, 0.8], [0, -0.52, 0.95]].forEach(([x, y, w]) => {
    const bar = new T.Mesh(new T.BoxGeometry(w, 0.07, 0.03), m.line);
    bar.position.set(x, y, 0.05);
    g.add(bar);
  });
  g.position.y = 0.95;
  g.rotation.y = 0.18;
  return g;
}

function microphone(m) {
  const g = new T.Group();
  const head = shadowed(new T.Mesh(new T.SphereGeometry(0.42, 48, 32), m.black));
  head.scale.set(1, 1.28, 1);
  head.position.y = 1.55;
  g.add(head);
  const band = new T.Mesh(new T.CylinderGeometry(0.43, 0.43, 0.08, 48), m.grey);
  band.position.y = 1.32;
  g.add(band);
  const cradle = shadowed(new T.Mesh(new T.TorusGeometry(0.58, 0.05, 16, 64, Math.PI), m.white));
  cradle.rotation.z = Math.PI;
  cradle.position.y = 1.4;
  g.add(cradle);
  const neck = shadowed(new T.Mesh(new T.CylinderGeometry(0.06, 0.06, 0.62, 24), m.white));
  neck.position.y = 0.5;
  g.add(neck);
  const base = shadowed(new T.Mesh(new T.CylinderGeometry(0.55, 0.6, 0.12, 48), m.white));
  base.position.y = 0.06;
  g.add(base);
  return g;
}

function scoreRing(m) {
  const g = new T.Group();
  g.add(shadowed(new T.Mesh(new T.TorusGeometry(0.9, 0.06, 24, 160), m.grey)));
  const arc = shadowed(new T.Mesh(new T.TorusGeometry(0.9, 0.17, 32, 160, Math.PI * 2 * 0.75), m.black));
  arc.rotation.z = Math.PI / 2;
  arc.scale.x = -1;
  g.add(arc);
  const stand = shadowed(new T.Mesh(new T.BoxGeometry(1.1, 0.14, 0.5), m.white));
  stand.position.y = -1.12;
  g.add(stand);
  g.position.y = 1.2;
  return g;
}

function hrStack(m) {
  const g = new T.Group();
  const n = 24;
  const inst = new T.InstancedMesh(new T.BoxGeometry(0.62, 0.84, 0.04), m.white, n);
  inst.castShadow = inst.receiveShadow = true;
  const d = new T.Object3D();
  for (let i = 0; i < n; i++) {
    const col = i % 6;
    const row = Math.floor(i / 6);
    d.position.set((col - 2.5) * 0.36, 0.5 + row * 0.12, -row * 0.32 - Math.abs(col - 2.5) * 0.05);
    d.rotation.set(-0.18, (col - 2.5) * -0.08, 0);
    d.updateMatrix();
    inst.setMatrixAt(i, d.matrix);
  }
  g.add(inst);
  const yours = shadowed(new T.Mesh(new T.BoxGeometry(0.7, 0.94, 0.05), m.black));
  yours.position.set(0, 1.25, 0.55);
  yours.rotation.x = -0.12;
  g.add(yours);
  g.userData.yours = yours;
  return g;
}

function phoneCall(m) {
  const g = new T.Group();
  const body = shadowed(new T.Mesh(new T.BoxGeometry(0.86, 1.72, 0.12), m.black));
  body.position.y = 1.05;
  body.rotation.y = -0.22;
  g.add(body);
  const screen = new T.Mesh(new T.PlaneGeometry(0.74, 1.56), m.white);
  screen.position.set(0, 0, 0.064);
  body.add(screen);
  const call = new T.Mesh(new T.CircleGeometry(0.13, 32), m.black);
  call.position.set(0, -0.5, 0.066);
  body.add(call);
  const waves = [];
  for (let i = 0; i < 3; i++) {
    const w = new T.Mesh(new T.TorusGeometry(0.62 + i * 0.26, 0.022, 12, 96), m.grey);
    w.position.copy(body.position);
    w.rotation.y = body.rotation.y;
    g.add(w);
    waves.push(w);
  }
  g.userData.waves = waves;
  return g;
}

function journey(host, sceneEl, ctx) {
  const { renderer, scene, camera, mats } = studio(host, ctx.disposers);
  const pts = [new T.Vector3(-8, 0, 1.2), new T.Vector3(-4, 0, -0.8), new T.Vector3(0, 0, 1.0), new T.Vector3(4, 0, -0.8), new T.Vector3(8, 0, 1.2)];
  const objs = [cvSheet, microphone, scoreRing, hrStack, phoneCall].map((b, i) => {
    const o = b(mats);
    o.position.x += pts[i].x;
    o.position.z += pts[i].z;
    o.userData.baseY = o.position.y;
    scene.add(o);
    return o;
  });

  const curve = new T.CatmullRomCurve3(pts.map((p) => new T.Vector3(p.x, 0.04, p.z + 1.25)));
  const N = 120;
  const dots = new T.InstancedMesh(new T.CylinderGeometry(0.06, 0.06, 0.02, 16), new T.MeshStandardMaterial({ roughness: 0.6 }), N);
  const d = new T.Object3D();
  const grey = new T.Color(0xc8c8ce);
  const ink = new T.Color(0x1d1d1f);
  for (let i = 0; i < N; i++) {
    d.position.copy(curve.getPointAt(i / (N - 1)));
    d.updateMatrix();
    dots.setMatrixAt(i, d.matrix);
    dots.setColorAt(i, grey);
  }
  scene.add(dots);

  const loop = new T.QuadraticBezierCurve3(
    new T.Vector3(pts[2].x - 0.4, 2.3, pts[2].z - 0.2),
    new T.Vector3((pts[1].x + pts[2].x) / 2, 4.2, -1.6),
    new T.Vector3(pts[1].x + 0.4, 2.4, pts[1].z - 0.2),
  );
  scene.add(new T.Mesh(new T.TubeGeometry(loop, 64, 0.035, 12, false), mats.grey));
  const arrow = new T.Mesh(new T.ConeGeometry(0.12, 0.3, 20), mats.grey);
  arrow.position.copy(loop.getPoint(1));
  arrow.lookAt(loop.getPoint(0.94));
  arrow.rotateX(-Math.PI / 2);
  scene.add(arrow);

  const overview = !ctx.root.classList.contains("motion");
  let t = 0;
  let active = 0;
  const camFor = (x) => {
    const i = clamp(x, 0, 4);
    const a = Math.floor(i);
    const b = Math.min(4, a + 1);
    const f = smooth(i - a);
    const px = pts[a].x + (pts[b].x - pts[a].x) * f;
    const pz = pts[a].z + (pts[b].z - pts[a].z) * f;
    return { pos: new T.Vector3(px + 1.6, 3.4, pz + 7.6), look: new T.Vector3(px, 1.1, pz) };
  };

  const steps = sceneEl ? [...sceneEl.querySelectorAll("[data-step]")] : [];
  let dirty = true;
  const setProgress = (p) => {
    t = clamp(p * 1.08, 0, 1) * 4;
    const a = Math.round(t);
    if (a !== active) {
      active = a;
      steps.forEach((s, k) => s.classList.toggle("is-on", k === a));
    }
    const lit = Math.round((t / 4) * (N - 1));
    for (let i = 0; i < N; i++) dots.setColorAt(i, i <= lit ? ink : grey);
    dots.instanceColor.needsUpdate = true;
    dirty = true;
  };
  setProgress(0);

  let visible = false;
  let raf = 0;
  const clock = new T.Clock();
  const frame = () => {
    raf = 0;
    if (ctx.dead) return;
    const now = clock.getElapsedTime();
    if (overview) {
      const halfW = 8.8;
      const tanH = Math.tan((camera.fov * Math.PI) / 360) * camera.aspect;
      const dist = halfW / tanH;
      const ang = ctx.reduce ? 0.12 : Math.sin(now * 0.15) * 0.35;
      camera.position.set(Math.sin(ang) * dist * 0.86, dist * 0.52, Math.cos(ang) * dist * 0.86);
      camera.lookAt(0, 0.6, 0);
    } else {
      const c = camFor(t);
      camera.position.lerp(c.pos, 0.12);
      camera.lookAt(c.look);
    }
    objs.forEach((o, i) => {
      const onStep = overview || i === active;
      const target = o.userData.baseY + (onStep ? 0.12 + (ctx.reduce ? 0 : Math.sin(now * 1.6 + i) * 0.05) : 0);
      o.position.y += (target - o.position.y) * 0.1;
      if (!ctx.reduce && onStep && i !== 2) o.rotation.y += 0.004;
    });
    objs[4].userData.waves.forEach((w, k) => w.scale.setScalar(ctx.reduce ? 1 : 1 + ((now * 0.6 + k / 3) % 1) * 0.35));
    if (!ctx.reduce) objs[3].userData.yours.position.y = 1.25 + Math.sin(now * 2) * 0.06;
    renderer.render(scene, camera);
    if (visible && !ctx.reduce) raf = requestAnimationFrame(frame);
    else if (dirty) {
      dirty = false;
      raf = requestAnimationFrame(frame);
    }
  };
  const io = new IntersectionObserver(([en]) => {
    visible = en.isIntersecting;
    if (visible && !raf) raf = requestAnimationFrame(frame);
  });
  io.observe(host);
  ctx.disposers.push(() => {
    io.disconnect();
    cancelAnimationFrame(raf);
  });
  camera.position.copy(overview ? new T.Vector3(0, 12, 20) : camFor(0).pos);
  raf = requestAnimationFrame(frame);
  return (p) => {
    setProgress(p);
    if (!raf) raf = requestAnimationFrame(frame);
  };
}

function days(host, _sceneEl, ctx) {
  const { renderer, scene, camera } = studio(host, ctx.disposers);
  const N = 90;
  const cols = 10;
  const cubes = new T.InstancedMesh(new T.BoxGeometry(0.42, 0.42, 0.42), new T.MeshPhysicalMaterial({ roughness: 0.35, clearcoat: 0.6 }), N);
  cubes.castShadow = cubes.receiveShadow = true;
  scene.add(cubes);
  const grey = new T.Color(0xe4e4e8);
  const ink = new T.Color(0x1d1d1f);
  const tmp = new T.Color();
  const d = new T.Object3D();
  const grid = (i) => new T.Vector3(((i % cols) - (cols - 1) / 2) * 0.56, 0.21, (Math.floor(i / cols) - 4) * 0.56);
  const keep = [0, 1, 2];
  const final = [new T.Vector3(-1.5, 0.62, 0), new T.Vector3(0, 0.62, 0), new T.Vector3(1.5, 0.62, 0)];
  const frame = () => {
    const tanH = Math.tan((camera.fov * Math.PI) / 360) * camera.aspect;
    const dist = Math.max(3.4 / tanH, 2.9 / Math.tan((camera.fov * Math.PI) / 360));
    camera.position.set(0, dist * 0.82, dist * 0.6);
    camera.lookAt(0, 0, 0.1);
  };
  frame();
  let last = 0;
  const draw = (p) => {
    if (ctx.dead) return;
    last = p;
    frame();
    const f = clamp(p / 0.8);
    const nf = 90 - 87 * (1 - Math.pow(1 - f, 3));
    for (let i = 0; i < N; i++) {
      const k = keep.indexOf(i);
      if (k >= 0) {
        const e = smooth(clamp((f - 0.55) / 0.45));
        d.position.copy(grid(i)).lerp(final[k], e);
        d.scale.setScalar(1 + e * 1.6);
        d.rotation.set(0, e * 0.6, 0);
        tmp.copy(grey).lerp(ink, smooth(clamp(f / 0.6)));
      } else {
        const e = smooth(clamp(i + 1 - nf));
        d.position.copy(grid(i));
        d.position.y = 0.21 - e * 0.5;
        d.scale.setScalar(Math.max(1 - e, 0.0001));
        d.rotation.set(0, 0, 0);
        tmp.copy(grey);
      }
      d.updateMatrix();
      cubes.setMatrixAt(i, d.matrix);
      cubes.setColorAt(i, tmp);
    }
    cubes.instanceMatrix.needsUpdate = true;
    cubes.instanceColor.needsUpdate = true;
    renderer.render(scene, camera);
  };
  const ro = new ResizeObserver(() => draw(last));
  ro.observe(host);
  ctx.disposers.push(() => ro.disconnect());
  draw(0);
  return draw;
}

const SCENES = { journey, days };

/** Mount every [data-3d] stage inside root when it nears the viewport. Returns a cleanup. */
export function mountApScenes(root) {
  const ctx = { root, disposers: [], dead: false, reduce: matchMedia("(prefers-reduced-motion: reduce)").matches };
  let hasWebGL = false;
  try {
    const c = document.createElement("canvas");
    hasWebGL = !!(c.getContext("webgl2") || c.getContext("webgl"));
  } catch {
    hasWebGL = false;
  }
  if (!hasWebGL) return () => {};
  const near = new IntersectionObserver(
    (entries) =>
      entries.forEach((en) => {
        if (!en.isIntersecting || ctx.dead) return;
        near.unobserve(en.target);
        const host = en.target;
        const make = SCENES[host.dataset["3d"]];
        if (!make) return;
        try {
          const sceneEl = host.closest("[data-scene]");
          const set = make(host, sceneEl, ctx);
          host.classList.add("is-3d");
          if (sceneEl) {
            sceneEl.__3d = set;
            set(Number(sceneEl.dataset.p || 0));
          } else set(1);
        } catch {
          host.classList.add("no-3d");
        }
      }),
    { rootMargin: "100% 0px" },
  );
  root.querySelectorAll("[data-3d]").forEach((h) => near.observe(h));
  return () => {
    ctx.dead = true;
    near.disconnect();
    root.querySelectorAll("[data-scene]").forEach((el) => delete el.__3d);
    ctx.disposers.forEach((fn) => fn());
  };
}
