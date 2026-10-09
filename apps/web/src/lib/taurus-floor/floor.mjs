/**
 * Taurus floor — the cinematic 3D agent floor behind /taurusai, the admin
 * Taurus console and the employer hiring floor.
 *
 * Plain JS (like lib/taurus/mount-taurus.mjs) with hand-written types in
 * floor.d.ts, so we don't pull in @types/three for one module. The engine
 * owns the WebGL scene and the small 3D-anchored name tags; React owns every
 * panel, number and button around it and pushes data in through sync().
 *
 * Zones are arranged round a ring: business areas on the ops floor, hiring
 * stages (in pipeline order) on the recruitment floor. Each agent is a robot
 * at a desk inside its zone; deliver() walks it to the core, flow() sends
 * candidate tokens from one stage to the next.
 */
import * as THREE from "three";
import { RoomEnvironment } from "three/addons/environments/RoomEnvironment.js";
import { EffectComposer } from "three/addons/postprocessing/EffectComposer.js";
import { RenderPass } from "three/addons/postprocessing/RenderPass.js";
import { UnrealBloomPass } from "three/addons/postprocessing/UnrealBloomPass.js";
import { OutputPass } from "three/addons/postprocessing/OutputPass.js";
import { Reflector } from "three/addons/objects/Reflector.js";

const STATUS_TEXT = {
  working: "Working",
  thinking: "Planning",
  needs: "Needs you",
  error: "Error",
  idle: "Idle",
  offline: "Offline",
};

/** Scene parameters per look. UI colours live in taurus-console.css. */
export const FLOOR_LOOKS = {
  obsidian: {
    bg: "#000000", fog: ["#000000", 0.02], exposure: 0.92,
    shell: ["#dcdce0", 0.32, 0.04, 1], joint: "#1c1d21", desk: ["#0c0d10", 0.3, 0.7], pedestal: "#0b0c0f",
    floor: ["#030405", 0.8], line: ["#ffffff", 0.1], zone: ["#ffffff", 0.028],
    core: ["#e8f4ff", 2.6], ring: ["#9fd8ff", 1.8], pillar: ["#9fd8ff", 0.26], beams: ["#ffffff", 0],
    dust: ["#cfe8ff", 0.3], hemi: ["#cfd8ff", "#000000", 0.22], key: ["#ffffff", 0.75], rim: ["#7fc8ff", 1.4], corePt: ["#9fd8ff", 14],
    bloom: [0.62, 0.45, 0.42], shadows: false, env: 0.55, visorK: 2.2, haloK: 1.5,
    screen: { bg: "#07090c", ink: "#f5f5f7", dim: "rgba(235,235,245,.45)" },
    status: { working: "#64d2ff", thinking: "#bf5af2", needs: "#ffd60a", error: "#ff453a", idle: "#98989d", offline: "#2c2c2e" },
    token: "#64d2ff",
    cam: { r: 28, h: 12.5, fov: 34 },
  },
  titanium: {
    bg: "#e8e9ed", fog: ["#e8e9ed", 0.0075], exposure: 1.0,
    shell: ["#7d8087", 0.28, 0.88, 0.7], joint: "#1d1d1f", desk: ["#fbfbfc", 0.45, 0], pedestal: "#f6f6f8",
    floor: ["#dcdee3", 0.9], line: ["#1d1d1f", 0.1], zone: ["#0071e3", 0.035],
    core: ["#cfe6ff", 2.4], ring: ["#0071e3", 2], pillar: ["#5ac8fa", 0], beams: ["#ffffff", 0],
    dust: ["#8e8e93", 0], hemi: ["#ffffff", "#a9aeb9", 0.35], key: ["#ffffff", 3.3], rim: ["#dfe8ff", 1.0], corePt: ["#5ac8fa", 10],
    bloom: [0.32, 0.3, 0.97], shadows: true, env: 0.8, visorK: 2.0, haloK: 1.4,
    screen: { bg: "#111214", ink: "#f5f5f7", dim: "rgba(235,235,245,.5)" },
    status: { working: "#0071e3", thinking: "#af52de", needs: "#ff9500", error: "#ff3b30", idle: "#8e8e93", offline: "#c7c7cc" },
    token: "#0071e3",
    cam: { r: 27, h: 13, fov: 32 },
  },
  noir: {
    bg: "#050406", fog: ["#0a0605", 0.026], exposure: 0.95,
    shell: ["#2b2c30", 0.38, 0.75, 0.5], joint: "#121214", desk: ["#0f0d0b", 0.4, 0.6], pedestal: "#0c0a09",
    floor: ["#070605", 0.62], line: ["#ffb040", 0.16], zone: ["#ffb040", 0.03],
    core: ["#fff1dc", 3.4], ring: ["#ffb040", 2.2], pillar: ["#ffb36b", 0.32], beams: ["#ffcf99", 0.05],
    dust: ["#ffcf99", 0.32], hemi: ["#4a3a2a", "#000000", 0.2], key: ["#ffd9a8", 0.5], rim: ["#ff9a4a", 1.7], corePt: ["#ffb36b", 22],
    bloom: [1.0, 0.6, 0.24], shadows: false, env: 0.35, visorK: 2.6, haloK: 2.0,
    screen: { bg: "#0a0806", ink: "#f3ede4", dim: "rgba(243,237,228,.45)" },
    status: { working: "#f3ede4", thinking: "#8fa8ff", needs: "#ffb040", error: "#ff4a3d", idle: "#7a746c", offline: "#2e2b28" },
    token: "#ffb040",
    cam: { r: 26, h: 8.5, fov: 30 },
  },
};

const esc = (s) => String(s ?? "").replace(/[&<>"]/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" })[c]);
const rand = (a, b) => a + Math.random() * (b - a);
const damp = (dt, k) => 1 - Math.exp(-dt * k);
const P = (a, r, y = 0) => new THREE.Vector3(Math.cos(a) * r, y, -Math.sin(a) * r);

export function createTaurusFloor(container, options = {}) {
  const opts = {
    look: "obsidian",
    zones: [],
    reducedMotion: false,
    interactive: true,
    wheelZoom: false,
    showZoneLabels: true,
    ...options,
  };
  if (!FLOOR_LOOKS[opts.look]) opts.look = "obsidian";
  let look = opts.look;
  const reduce = !!opts.reducedMotion;

  /* ---------------------------------------------------------- DOM */
  const canvas = document.createElement("canvas");
  canvas.className = "tf-canvas";
  canvas.setAttribute("aria-hidden", "true");
  const labelsHost = document.createElement("div");
  labelsHost.className = "tf-labels";
  labelsHost.setAttribute("aria-hidden", "true");
  const flare = document.createElement("div");
  flare.className = "tf-flare";
  container.append(canvas, flare, labelsHost);

  /* ---------------------------------------------------------- renderer */
  let renderer;
  try {
    renderer = new THREE.WebGLRenderer({ canvas, antialias: true, powerPreference: "high-performance" });
  } catch (e) {
    canvas.remove();
    flare.remove();
    labelsHost.remove();
    throw e;
  }
  const narrow = () => container.clientWidth < 860;
  renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, narrow() ? 1.25 : 1.5));
  renderer.toneMapping = THREE.ACESFilmicToneMapping;
  renderer.outputColorSpace = THREE.SRGBColorSpace;
  renderer.shadowMap.enabled = true;
  renderer.shadowMap.type = THREE.PCFSoftShadowMap;

  const scene = new THREE.Scene();
  scene.fog = new THREE.FogExp2(0x000000, 0.02);
  const camera = new THREE.PerspectiveCamera(34, 1, 0.1, 220);
  const pmrem = new THREE.PMREMGenerator(renderer);
  const envTex = pmrem.fromScene(new RoomEnvironment(), 0.04).texture;
  scene.environment = envTex;

  const composer = new EffectComposer(renderer);
  composer.addPass(new RenderPass(scene, camera));
  const bloom = new UnrealBloomPass(new THREE.Vector2(512, 512), 0.6, 0.45, 0.4);
  composer.addPass(bloom);
  composer.addPass(new OutputPass());

  const M = {
    shell: new THREE.MeshPhysicalMaterial({ roughness: 0.3, clearcoat: 1, clearcoatRoughness: 0.2 }),
    joint: new THREE.MeshStandardMaterial({ roughness: 0.5, metalness: 0.6 }),
    desk: new THREE.MeshPhysicalMaterial({ clearcoat: 0.6 }),
    pedestal: new THREE.MeshPhysicalMaterial({ roughness: 0.25, metalness: 0.6, clearcoat: 1 }),
    floor: new THREE.MeshStandardMaterial({ roughness: 0.55, metalness: 0.2, transparent: true }),
    line: new THREE.LineBasicMaterial({ transparent: true, depthWrite: false }),
    zone: new THREE.MeshBasicMaterial({ transparent: true, depthWrite: false, side: THREE.DoubleSide }),
    core: new THREE.MeshBasicMaterial({ toneMapped: false }),
    ring: new THREE.MeshBasicMaterial({ toneMapped: false, transparent: true, opacity: 0.9 }),
    wire: new THREE.MeshBasicMaterial({ toneMapped: false, wireframe: true, transparent: true, opacity: 0.35 }),
  };
  const mesh = (geo, mat, parent, shadow = true) => {
    const m = new THREE.Mesh(geo, mat);
    m.castShadow = shadow;
    m.receiveShadow = shadow;
    (parent || scene).add(m);
    return m;
  };

  /* ---------------------------------------------------------- floor + rings */
  let reflector = null;
  if (!narrow()) {
    reflector = new Reflector(new THREE.CircleGeometry(70, 64), { clipBias: 0.003, textureWidth: 1024, textureHeight: 1024, color: 0x8a8a8a });
    reflector.rotation.x = -Math.PI / 2;
    reflector.position.y = -0.003;
    scene.add(reflector);
  }
  const floor = mesh(new THREE.CircleGeometry(70, 64), M.floor);
  floor.rotation.x = -Math.PI / 2;
  floor.castShadow = false;
  for (const r of [3.4, 6.6, 9.3, 13.6, 17.6]) {
    const pts = [];
    for (let i = 0; i < 160; i++) pts.push(P((i / 160) * Math.PI * 2, r, 0.01));
    scene.add(new THREE.LineLoop(new THREE.BufferGeometry().setFromPoints(pts), M.line));
  }
  {
    const pts = [];
    for (let i = 0; i < 180; i++) {
      const a = (i / 180) * Math.PI * 2;
      pts.push(P(a, 17.6, 0.01), P(a, i % 10 === 0 ? 18.6 : 18.0, 0.01));
    }
    scene.add(new THREE.LineSegments(new THREE.BufferGeometry().setFromPoints(pts), M.line));
  }

  /* ---------------------------------------------------------- core */
  const core = new THREE.Group();
  scene.add(core);
  mesh(new THREE.CylinderGeometry(2.6, 3.0, 0.3, 96), M.pedestal, core).position.y = 0.15;
  const coreDisc = mesh(new THREE.RingGeometry(1.25, 2.35, 96), M.ring, core, false);
  coreDisc.rotation.x = -Math.PI / 2;
  coreDisc.position.y = 0.31;
  const orb = mesh(new THREE.SphereGeometry(0.62, 64, 32), M.core, core, false);
  orb.position.y = 2.3;
  const shell = mesh(new THREE.IcosahedronGeometry(1.02, 1), M.wire, core, false);
  shell.position.y = 2.3;
  const rings = [1.45, 1.8, 2.15].map((r, i) => {
    const piv = new THREE.Group();
    piv.position.y = 2.3;
    piv.rotation.set(0.5 + i * 0.55, i * 0.9, 0.2 * i);
    core.add(piv);
    mesh(new THREE.TorusGeometry(r, 0.018, 8, 160), M.ring, piv, false);
    return piv;
  });
  const beamMat = (up) =>
    new THREE.ShaderMaterial({
      transparent: true,
      depthWrite: false,
      blending: THREE.AdditiveBlending,
      side: THREE.DoubleSide,
      uniforms: { uColor: { value: new THREE.Color() }, uOpacity: { value: 0 }, uUp: { value: up } },
      vertexShader: `varying vec2 vUv; varying vec3 vN; varying vec3 vV;
        void main(){ vUv=uv; vec4 mv=modelViewMatrix*vec4(position,1.); vN=normalize(normalMatrix*normal); vV=normalize(-mv.xyz); gl_Position=projectionMatrix*mv; }`,
      fragmentShader: `uniform vec3 uColor; uniform float uOpacity; uniform float uUp; varying vec2 vUv; varying vec3 vN; varying vec3 vV;
        void main(){ float h = uUp > .5 ? .15 + .85*vUv.y : pow(1.-vUv.y, 2.2); float a = h * pow(abs(dot(vN,vV)), 1.6) * uOpacity; gl_FragColor = vec4(uColor, a); }`,
    });
  const pillarMat = beamMat(0);
  const pillar = mesh(new THREE.CylinderGeometry(0.55, 1.1, 18, 48, 1, true), pillarMat, core, false);
  pillar.position.y = 9;
  const beamM = beamMat(1);

  const corePt = new THREE.PointLight(0xffffff, 14, 26, 1.6);
  corePt.position.y = 2.6;
  scene.add(corePt);
  const hemi = new THREE.HemisphereLight(0xffffff, 0x000000, 0.3);
  scene.add(hemi);
  const key = new THREE.DirectionalLight(0xffffff, 1);
  key.position.set(12, 22, 8);
  key.shadow.mapSize.set(2048, 2048);
  Object.assign(key.shadow.camera, { left: -20, right: 20, top: 20, bottom: -20, near: 1, far: 60 });
  key.shadow.bias = -0.0004;
  scene.add(key);
  const rim = new THREE.DirectionalLight(0xffffff, 1);
  rim.position.set(-14, 10, -18);
  scene.add(rim);

  const pulses = Array.from({ length: 6 }, () => {
    const m = new THREE.MeshBasicMaterial({ toneMapped: false, transparent: true, opacity: 0, depthWrite: false });
    const p = mesh(new THREE.RingGeometry(0.96, 1, 128), m, scene, false);
    p.rotation.x = -Math.PI / 2;
    p.position.y = 0.33;
    return { p, m, t: 1 };
  });
  function pulse(color) {
    const p = pulses.find((x) => x.t >= 1) || pulses[0];
    p.t = 0;
    p.m.color.set(color || FLOOR_LOOKS[look].status.working).multiplyScalar(2.2);
  }

  /* ---------------------------------------------------------- light streams + candidate tokens */
  const SN = 360;
  const sparkGeo = new THREE.SphereGeometry(0.055, 10, 8);
  const streamMesh = new THREE.InstancedMesh(sparkGeo, new THREE.MeshBasicMaterial({ toneMapped: false }), SN);
  streamMesh.instanceMatrix.setUsage(THREE.DynamicDrawUsage);
  streamMesh.count = 0;
  scene.add(streamMesh);
  const sparks = [];
  const CORE_PT = new THREE.Vector3(0, 2.3, 0);
  const tmpM = new THREE.Matrix4();
  const tmpV = new THREE.Vector3();
  const tmpS = new THREE.Vector3();

  const TN = 120;
  const tokenMesh = new THREE.InstancedMesh(new THREE.IcosahedronGeometry(0.13, 1), new THREE.MeshBasicMaterial({ toneMapped: false }), TN);
  tokenMesh.instanceMatrix.setUsage(THREE.DynamicDrawUsage);
  tokenMesh.count = 0;
  scene.add(tokenMesh);
  const tokens = [];

  /* dust */
  const DUST = 1400;
  const dpos = new Float32Array(DUST * 3);
  for (let i = 0; i < DUST; i++) {
    const r = Math.sqrt(Math.random()) * 30;
    const a = Math.random() * 6.283;
    dpos.set([Math.cos(a) * r, Math.random() * 14, Math.sin(a) * r], i * 3);
  }
  const dustGeo = new THREE.BufferGeometry();
  dustGeo.setAttribute("position", new THREE.BufferAttribute(dpos, 3));
  const dustMat = new THREE.PointsMaterial({ size: 0.06, transparent: true, depthWrite: false, sizeAttenuation: true });
  const dust = new THREE.Points(dustGeo, dustMat);
  scene.add(dust);

  /* ---------------------------------------------------------- robots */
  const geo = {
    torso: new THREE.CapsuleGeometry(0.3, 0.42, 8, 24),
    head: new THREE.SphereGeometry(0.26, 40, 28),
    visor: new THREE.SphereGeometry(0.266, 40, 12, Math.PI / 2 - 0.95, 1.9, 1.22, 0.42),
    neck: new THREE.CylinderGeometry(0.07, 0.09, 0.14, 16),
    arm: new THREE.CapsuleGeometry(0.075, 0.4, 6, 12),
    hand: new THREE.SphereGeometry(0.085, 16, 12),
    leg: new THREE.CapsuleGeometry(0.095, 0.5, 6, 12),
    halo: new THREE.RingGeometry(0.5, 0.58, 64),
    disc: new THREE.CircleGeometry(0.5, 48),
    chest: new THREE.CircleGeometry(0.055, 20),
    box: new THREE.BoxGeometry(0.3, 0.22, 0.22),
    orbit: new THREE.SphereGeometry(0.045, 10, 8),
    deskLeg: new THREE.CylinderGeometry(0.07, 0.14, 0.95, 16),
    deskTop: new THREE.BoxGeometry(1.6, 0.07, 0.62),
    frame: new THREE.BoxGeometry(1.56, 0.92, 0.04),
    screen: new THREE.PlaneGeometry(1.5, 0.86),
  };
  function limb(parent, x, y, g, len, hand) {
    const pivot = new THREE.Group();
    pivot.position.set(x, y, 0);
    parent.add(pivot);
    mesh(g, M.shell, pivot).position.y = -len;
    if (hand) mesh(geo.hand, M.joint, pivot).position.y = -len * 2 + 0.02;
    return pivot;
  }
  function makeRobot(ag) {
    const g = new THREE.Group();
    const body = new THREE.Group();
    g.add(body);
    const torso = mesh(geo.torso, M.shell, body);
    torso.position.y = 1.24;
    torso.userData.agentId = ag.id;
    const chestMat = new THREE.MeshBasicMaterial({ toneMapped: false });
    mesh(geo.chest, chestMat, body, false).position.set(0, 1.42, 0.302);
    mesh(geo.neck, M.joint, body).position.y = 1.78;
    const head = new THREE.Group();
    head.position.y = 1.97;
    body.add(head);
    const skull = mesh(geo.head, M.shell, head);
    skull.scale.set(1, 0.92, 1);
    skull.userData.agentId = ag.id;
    const visorMat = new THREE.MeshBasicMaterial({ toneMapped: false });
    mesh(geo.visor, visorMat, head, false);
    const armL = limb(body, 0.4, 1.56, geo.arm, 0.27, true);
    const armR = limb(body, -0.4, 1.56, geo.arm, 0.27, true);
    const legL = limb(g, 0.15, 0.86, geo.leg, 0.36, false);
    const legR = limb(g, -0.15, 0.86, geo.leg, 0.36, false);
    const haloMat = new THREE.MeshBasicMaterial({ toneMapped: false, transparent: true, depthWrite: false });
    const halo = mesh(geo.halo, haloMat, g, false);
    halo.rotation.x = -Math.PI / 2;
    halo.position.y = 0.02;
    const discMat = new THREE.MeshBasicMaterial({ transparent: true, opacity: 0.12, depthWrite: false, toneMapped: false });
    const disc = mesh(geo.disc, discMat, g, false);
    disc.rotation.x = -Math.PI / 2;
    disc.position.y = 0.015;
    const carryMat = new THREE.MeshBasicMaterial({ toneMapped: false });
    const carry = mesh(geo.box, carryMat, body, false);
    carry.position.set(0, 1.2, 0.48);
    carry.visible = false;
    const orbit = new THREE.Group();
    orbit.position.y = 2.45;
    body.add(orbit);
    for (let i = 0; i < 3; i++) mesh(geo.orbit, carryMat, orbit, false).position.set(Math.cos((i / 3) * 6.28) * 0.36, 0, Math.sin((i / 3) * 6.28) * 0.36);
    scene.add(g);
    return { g, body, head, armL, armR, legL, legR, halo, haloMat, discMat, visorMat, chestMat, carry, carryMat, orbit, pick: [torso, skull], mats: [chestMat, visorMat, haloMat, discMat, carryMat] };
  }
  function makeDesk() {
    const group = new THREE.Group();
    scene.add(group);
    mesh(geo.deskLeg, M.desk, group).position.y = 0.47;
    mesh(geo.deskTop, M.desk, group).position.y = 0.96;
    const frame = mesh(geo.frame, M.desk, group);
    frame.position.set(0, 1.78, -0.2);
    frame.rotation.x = -0.12;
    const c = document.createElement("canvas");
    c.width = 256;
    c.height = 150;
    const tex = new THREE.CanvasTexture(c);
    tex.colorSpace = THREE.SRGBColorSpace;
    const mat = new THREE.MeshBasicMaterial({ map: tex, toneMapped: false });
    const screen = mesh(geo.screen, mat, group, false);
    screen.position.set(0, 1.78, -0.176);
    screen.rotation.x = -0.12;
    return { group, c, ctx: c.getContext("2d"), tex, mat, bars: Array.from({ length: 14 }, () => Math.random()) };
  }
  function drawScreen(ag) {
    const L = FLOOR_LOOKS[look];
    const { ctx, c, bars } = ag.desk;
    const col = L.status[ag.status] || L.status.idle;
    ctx.fillStyle = L.screen.bg;
    ctx.fillRect(0, 0, c.width, c.height);
    ctx.fillStyle = col;
    ctx.fillRect(0, 0, c.width, 3);
    ctx.fillStyle = L.screen.ink;
    ctx.font = "600 17px Inter, Helvetica, sans-serif";
    ctx.fillText(ag.name.length > 20 ? `${ag.name.slice(0, 19)}…` : ag.name, 14, 28);
    ctx.fillStyle = L.screen.dim;
    ctx.font = "500 12px Inter, Helvetica, sans-serif";
    ctx.fillText((STATUS_TEXT[ag.status] || "").toUpperCase(), 14, 47);
    const live = ag.status === "working" || ag.status === "thinking";
    for (let i = 0; i < bars.length; i++) {
      if (live && Math.random() < 0.25) bars[i] = Math.min(1, Math.max(0.08, bars[i] + rand(-0.25, 0.25)));
      const h = bars[i] * 52 * (ag.status === "offline" ? 0.15 : 1);
      ctx.globalAlpha = i === bars.length - 1 ? 1 : 0.55;
      ctx.fillStyle = col;
      ctx.fillRect(14 + i * 16, 118 - h, 10, h);
    }
    ctx.globalAlpha = 1;
    ctx.fillStyle = "rgba(127,127,127,.25)";
    ctx.fillRect(14, 132, 228, 4);
    ctx.fillStyle = col;
    ctx.fillRect(14, 132, 228 * Math.min(1, Math.max(0, ag.progress || 0)), 4);
    ag.desk.tex.needsUpdate = true;
  }

  /* ---------------------------------------------------------- zones + layout */
  const layoutGroup = new THREE.Group();
  scene.add(layoutGroup);
  const zoneBeams = [];
  let zoneTags = [];
  let zoneMid = new Map();
  const agents = new Map();

  function relayout() {
    layoutGroup.children.slice().forEach((o) => {
      layoutGroup.remove(o);
      o.geometry?.dispose();
    });
    zoneBeams.forEach((b) => {
      scene.remove(b);
      b.geometry.dispose();
    });
    zoneBeams.length = 0;
    zoneTags.forEach((z) => z.el.remove());
    zoneTags = [];
    zoneMid = new Map();

    const zones = opts.zones.length ? opts.zones : [{ key: "_", label: "" }];
    const byZone = zones.map((z) => [...agents.values()].filter((a) => a.zone === z.key));
    const stray = [...agents.values()].filter((a) => !zones.some((z) => z.key === a.zone));
    if (stray.length) byZone[byZone.length - 1].push(...stray);
    const slots = byZone.reduce((n, list) => n + Math.max(1, list.length), 0);
    const GAP = zones.length > 1 ? 0.17 : 0;
    const per = (Math.PI * 2 - GAP * zones.length) / slots;
    let ang = Math.PI * 0.62;
    zones.forEach((z, zi) => {
      const list = byZone[zi];
      const start = ang;
      list.forEach((a, i) => {
        a.angle = start + per * (i + 0.5);
        const deskPos = P(a.angle, 12.15);
        a.desk.group.position.copy(deskPos);
        a.desk.group.rotation.y = Math.atan2(-deskPos.x, -deskPos.z);
        a.home = P(a.angle, 11.0);
        a.deskYaw = Math.atan2(deskPos.x - a.home.x, deskPos.z - a.home.z);
        if (a.mode === "desk") a.pos.copy(a.home);
        else if (a.mode === "back") a.target = a.home.clone();
      });
      ang += per * Math.max(1, list.length);
      const end = ang;
      ang += GAP;
      zoneMid.set(z.key, (start + end) / 2);
      if (!z.label) return;
      const carpet = new THREE.Mesh(new THREE.RingGeometry(9.3, 13.6, 48, 1, start, end - start), M.zone);
      carpet.rotation.x = -Math.PI / 2;
      carpet.position.y = 0.006;
      layoutGroup.add(carpet);
      const edge = [P(start, 9.3, 0.012), P(start, 13.6, 0.012), P(end, 9.3, 0.012), P(end, 13.6, 0.012)];
      layoutGroup.add(new THREE.LineSegments(new THREE.BufferGeometry().setFromPoints(edge), M.line));
      const beam = new THREE.Mesh(new THREE.ConeGeometry(3.6, 22, 48, 1, true), beamM);
      beam.position.copy(P((start + end) / 2, 11.6, 11));
      scene.add(beam);
      zoneBeams.push(beam);
      if (opts.showZoneLabels) {
        const el = document.createElement("div");
        el.className = "tf-zone";
        el.innerHTML = `<div class="tf-in">${zi + 1 < 10 && opts.numberZones ? `<b>${zi + 1}</b> ` : ""}${esc(z.label)}</div>`;
        labelsHost.appendChild(el);
        zoneTags.push({ el, p: P((start + end) / 2, 15.4, 0.05) });
      }
    });
  }

  function makeTag(ag) {
    const el = document.createElement("div");
    el.className = "tf-tag";
    el.innerHTML = `<div class="tf-in"><div class="tf-detail"></div><div class="tf-pill" data-s=""><i class="tf-dot"></i><span></span></div><div class="tf-stem"></div></div>`;
    labelsHost.appendChild(el);
    ag.tag = el;
    ag.tagPill = el.querySelector(".tf-pill");
    ag.tagDetail = el.querySelector(".tf-detail");
  }
  labelsHost.addEventListener("click", (e) => {
    const b = e.target.closest("[data-act]");
    if (!b) return;
    const id = b.getAttribute("data-id");
    if (b.getAttribute("data-act") === "approve") opts.onApprove?.(id);
    else {
      focus(id, 10);
      opts.onSelect?.(id);
    }
  });

  function applyStatus(ag) {
    const L = FLOOR_LOOKS[look];
    const c = L.status[ag.status] || L.status.idle;
    const off = ag.status === "offline";
    ag.r.visorMat.color.set(c).multiplyScalar(off ? 0.15 : L.visorK);
    ag.r.chestMat.color.set(c).multiplyScalar(off ? 0.1 : L.visorK);
    ag.r.haloMat.color.set(c).multiplyScalar(off ? 0.3 : L.haloK);
    ag.r.discMat.color.set(c);
    ag.r.carryMat.color.set(c).multiplyScalar(L.visorK);
    ag.r.orbit.visible = ag.status === "thinking";
    ag.tagPill.dataset.s = ag.status;
    ag._dk = "";
  }
  function renderDetail(ag) {
    const k = `${ag.status}|${ag.task}|${Math.round((ag.progress || 0) * 100)}|${ag.mode}|${ag.approval}`;
    if (ag._dk === k) return;
    ag._dk = k;
    let html = `<b>${esc(ag.name)}</b>`;
    if (ag.status === "needs" && ag.approval) {
      html += `Wants to: <span class="tf-strong">${esc(ag.approval)}</span>`;
      if (opts.onApprove) html += `<div class="tf-acts"><button class="tf-btn primary" type="button" data-act="approve" data-id="${esc(ag.id)}">Approve</button><button class="tf-btn" type="button" data-act="review" data-id="${esc(ag.id)}">Review</button></div>`;
    } else if (ag.mode === "out") html += `Delivering: ${esc(ag.task || "finished work")}`;
    else if (ag.mode === "back") html += "Heading back to the desk";
    else {
      html += esc(ag.task || STATUS_TEXT[ag.status] || "");
      if (ag.status === "working" || ag.status === "thinking") html += `<div class="tf-bar" data-s="${ag.status}"><i style="width:${Math.round((ag.progress || 0) * 100)}%"></i></div>`;
    }
    ag.tagDetail.innerHTML = html;
  }

  /* ---------------------------------------------------------- public: data */
  function sync(list) {
    const seen = new Set();
    let layoutDirty = false;
    for (const d of list) {
      seen.add(d.id);
      let ag = agents.get(d.id);
      if (!ag) {
        ag = { id: d.id, name: d.name, zone: d.zone, status: d.status || "idle", task: d.task || "", progress: d.progress || 0, approval: d.approval || null, phase: rand(0, 6.28), mode: "desk", pos: new THREE.Vector3(), yaw: 0, deskYaw: 0, home: new THREE.Vector3(), angle: 0 };
        ag.desk = makeDesk();
        ag.r = makeRobot(ag);
        makeTag(ag);
        agents.set(d.id, ag);
        layoutDirty = true;
      }
      if (ag.zone !== d.zone) layoutDirty = true;
      ag.name = d.name;
      ag.zone = d.zone;
      ag.task = d.task || "";
      ag.progress = typeof d.progress === "number" ? d.progress : ag.progress;
      ag.approval = d.approval || null;
      ag.tagPill.querySelector("span").textContent = d.name;
      if (ag.status !== d.status) {
        if (d.status === "needs") ag.needsSince = performance.now();
        ag.status = d.status || "idle";
        applyStatus(ag);
      }
    }
    for (const [id, ag] of agents) {
      if (seen.has(id)) continue;
      scene.remove(ag.r.g);
      scene.remove(ag.desk.group);
      ag.r.mats.forEach((m) => m.dispose());
      ag.desk.mat.dispose();
      ag.desk.tex.dispose();
      ag.tag.remove();
      agents.delete(id);
      layoutDirty = true;
    }
    if (layoutDirty) {
      relayout();
      agents.forEach((a) => {
        a.r.g.position.copy(a.pos);
        a.yaw = a.deskYaw;
        applyStatus(a);
        drawScreen(a);
      });
    }
  }
  function setZones(zones) {
    opts.zones = zones;
    relayout();
  }
  function deliver(id) {
    const ag = agents.get(id);
    if (!ag || ag.mode !== "desk") return;
    ag.mode = "out";
    ag.target = P(ag.angle + rand(-0.06, 0.06), 3.75);
    ag.r.carry.visible = true;
  }
  function stream(id, n = 6, color) {
    const ag = agents.get(id);
    if (!ag) return;
    const a = ag.pos.clone().setY(1.6);
    const c = a.clone().lerp(CORE_PT, 0.5).setY(4.6 + Math.random() * 1.5);
    const L = FLOOR_LOOKS[look];
    const col = new THREE.Color(color || L.status[ag.status === "offline" ? "idle" : ag.status] || L.status.working).multiplyScalar(2.4);
    for (let i = 0; i < n && sparks.length < SN; i++) sparks.push({ a, c, b: CORE_PT, t: -i * 0.07, sp: rand(0.5, 0.65), col });
  }
  /** Candidate tokens hopping from one stage to the next along the inner ring. */
  function flow(fromZone, toZone, n = 1) {
    const a0 = zoneMid.get(fromZone);
    const a1 = zoneMid.get(toZone);
    if (a0 === undefined || a1 === undefined) return;
    let d = a1 - a0;
    d = Math.atan2(Math.sin(d), Math.cos(d));
    for (let i = 0; i < n && tokens.length < TN; i++) tokens.push({ a0, d, t: -i * 0.18, sp: rand(0.22, 0.3), r: rand(7.4, 8.4) });
  }

  /* ---------------------------------------------------------- per-frame agent update */
  function updateAgent(ag, t, dt) {
    const R = ag.r;
    if (ag.mode !== "desk") {
      const to = tmpV.copy(ag.target).sub(ag.pos).setY(0);
      const d = to.length();
      const step = 1.75 * dt * (reduce ? 0.6 : 1);
      if (d < step || d < 0.05) {
        ag.pos.copy(ag.target);
        if (ag.mode === "out") {
          R.carry.visible = false;
          pulse(FLOOR_LOOKS[look].status.working);
          stream(ag.id, 12, FLOOR_LOOKS[look].core[0]);
          ag.mode = "back";
          ag.target = ag.home.clone();
          opts.onDelivered?.(ag.id);
        } else ag.mode = "desk";
      } else ag.pos.addScaledVector(to.normalize(), step);
    }
    R.g.position.copy(ag.pos);

    const s = ag.status;
    const ph = ag.phase;
    let lL = 0, lR = 0, aL = 0, aR = 0, aRz = 0, bob = 0, hx = 0, hy = 0, lean = 0;
    let yaw = ag.deskYaw;
    if (ag.mode !== "desk") {
      const w = t * 8 + ph;
      lL = Math.sin(w) * 0.6;
      lR = -lL;
      aL = R.carry.visible ? -1.25 : -lL * 0.7;
      aR = R.carry.visible ? -1.25 : -lR * 0.7;
      bob = Math.abs(Math.cos(w)) * 0.05;
      yaw = Math.atan2(ag.target.x - ag.pos.x, ag.target.z - ag.pos.z);
    } else if (s === "working") {
      aL = -1.0 + Math.sin(t * 15 + ph) * 0.09;
      aR = -1.0 + Math.sin(t * 15 + ph + 1.7) * 0.09;
      hx = 0.16 + Math.sin(t * 0.7 + ph) * 0.05;
      hy = Math.sin(t * 0.45 + ph) * 0.18;
      bob = Math.sin(t * 2 + ph) * 0.012;
    } else if (s === "thinking") {
      aR = -2.2;
      aRz = -0.5;
      aL = -0.2;
      hx = -0.12;
      hy = Math.sin(t * 0.9 + ph) * 0.4;
      R.orbit.rotation.y = t * 2.4;
    } else if (s === "needs") {
      aR = -2.75 + Math.sin(t * 3.2 + ph) * 0.22;
      aRz = -0.25;
      hx = -0.12;
      yaw = ag.deskYaw + Math.PI * 0.85;
      bob = Math.abs(Math.sin(t * 3.2 + ph)) * 0.03;
    } else if (s === "error") {
      hx = 0.45 + Math.sin(t * 22) * 0.03;
      aL = aR = 0.08;
      lean = 0.08;
    } else if (s === "idle") {
      hy = Math.sin(t * 0.3 + ph) * 0.7;
      bob = Math.sin(t * 1.4 + ph) * 0.015;
    } else {
      hx = 0.6;
      lean = 0.12;
      aL = aR = 0.12;
    }
    const k = damp(dt, 7);
    R.legL.rotation.x += (lL - R.legL.rotation.x) * k;
    R.legR.rotation.x += (lR - R.legR.rotation.x) * k;
    R.armL.rotation.x += (aL - R.armL.rotation.x) * k;
    R.armR.rotation.x += (aR - R.armR.rotation.x) * k;
    R.armR.rotation.z += (aRz - R.armR.rotation.z) * k;
    R.head.rotation.x += (hx - R.head.rotation.x) * k;
    R.head.rotation.y += (hy - R.head.rotation.y) * k;
    R.body.rotation.x += (lean - R.body.rotation.x) * k;
    R.body.position.y = bob;
    let dy = yaw - ag.yaw;
    dy = Math.atan2(Math.sin(dy), Math.cos(dy));
    ag.yaw += dy * damp(dt, 6);
    R.g.rotation.y = ag.yaw;

    let o = 0.85;
    if (s === "needs") o = 0.55 + 0.45 * Math.sin(t * 4 + ph);
    else if (s === "error") o = Math.random() < 0.12 ? 0.15 : 0.9;
    else if (s === "offline") o = 0.25;
    R.haloMat.opacity = o;
    R.halo.scale.setScalar(s === "needs" ? 1 + 0.12 * Math.sin(t * 4 + ph) : 1);
  }

  /* ---------------------------------------------------------- camera */
  const cam = { angle: 0.35, intro: reduce ? 0 : 1, user: 0, zoom: 1, pos: new THREE.Vector3(0, 30, 50), tgt: new THREE.Vector3(0, 1.2, 0) };
  let focused = null;
  function focus(id, secs = 6) {
    if (reduce || cam.user > 0 || !agents.has(id)) return;
    focused = { id, until: performance.now() + secs * 1000 };
  }
  function updateCamera(dt, t) {
    const L = FLOOR_LOOKS[look].cam;
    const portrait = camera.aspect < 1;
    if (cam.intro > 0) cam.intro = Math.max(0, cam.intro - dt / 3.4);
    const e = 1 - Math.pow(cam.intro, 3);
    cam.angle += dt * (reduce ? 0.008 : 0.032);
    cam.user = Math.max(0, cam.user - dt);
    const r = L.r * (portrait ? 1.55 : 1) * cam.zoom + 34 * (1 - e);
    const h = L.h * (portrait ? 1.3 : 1) + 20 * (1 - e) + Math.sin(t * 0.15) * 0.6;
    const a = cam.angle - 0.9 * (1 - e);
    const want = tmpV.set(Math.cos(a) * r, h, Math.sin(a) * r);
    const wantT = tmpS.set(0, 1.2, 0);
    const fa = focused && agents.get(focused.id);
    if (fa && performance.now() < focused.until && cam.user <= 0 && cam.intro <= 0) {
      const out = fa.pos.clone().setY(0).normalize();
      const side = out.applyAxisAngle(new THREE.Vector3(0, 1, 0), 1.05);
      want.copy(fa.pos).addScaledVector(side, portrait ? 8 : 6).setY(portrait ? 4.2 : 3.1);
      wantT.copy(fa.pos).setY(1.5);
    } else if (focused && (!fa || performance.now() >= focused.until)) focused = null;
    cam.pos.lerp(want, cam.intro > 0 ? 1 : damp(dt, 1.3));
    cam.tgt.lerp(wantT, cam.intro > 0 ? 1 : damp(dt, 1.6));
    camera.position.copy(cam.pos);
    camera.lookAt(cam.tgt);
  }

  /* ---------------------------------------------------------- input */
  let drag = null;
  const pickables = () => [...agents.values()].flatMap((a) => a.r.pick);
  const onDown = (e) => {
    drag = { x: e.clientX, y: e.clientY, moved: 0 };
    canvas.setPointerCapture?.(e.pointerId);
  };
  const onMove = (e) => {
    if (!drag) return;
    const dx = e.clientX - drag.x;
    drag.moved += Math.abs(dx) + Math.abs(e.clientY - drag.y);
    drag.x = e.clientX;
    drag.y = e.clientY;
    if (drag.moved > 4) {
      cam.angle -= dx * 0.005;
      cam.user = 6;
      focused = null;
    }
  };
  const onUp = (e) => {
    if (drag && drag.moved <= 4) {
      const rect = canvas.getBoundingClientRect();
      const ray = new THREE.Raycaster();
      ray.setFromCamera(new THREE.Vector2(((e.clientX - rect.left) / rect.width) * 2 - 1, -((e.clientY - rect.top) / rect.height) * 2 + 1), camera);
      const hit = ray.intersectObjects(pickables(), false)[0];
      if (hit) {
        cam.user = 0;
        focus(hit.object.userData.agentId, 10);
        opts.onSelect?.(hit.object.userData.agentId);
      }
    }
    drag = null;
  };
  const onWheel = (e) => {
    if (!opts.wheelZoom && !(e.ctrlKey || e.metaKey)) return;
    e.preventDefault();
    cam.zoom = Math.min(1.5, Math.max(0.55, cam.zoom * (1 + e.deltaY * 0.0012)));
  };
  if (opts.interactive) {
    canvas.addEventListener("pointerdown", onDown);
    canvas.addEventListener("pointermove", onMove);
    canvas.addEventListener("pointerup", onUp);
    canvas.addEventListener("wheel", onWheel, { passive: false });
  }

  /* ---------------------------------------------------------- look */
  function setLook(next, intro = true) {
    if (!FLOOR_LOOKS[next]) return;
    look = next;
    const L = FLOOR_LOOKS[next];
    scene.background = new THREE.Color(L.bg);
    scene.fog.color.set(L.fog[0]);
    scene.fog.density = L.fog[1];
    renderer.toneMappingExposure = L.exposure;
    M.shell.color.set(L.shell[0]);
    M.shell.roughness = L.shell[1];
    M.shell.metalness = L.shell[2];
    M.shell.clearcoat = L.shell[3];
    M.joint.color.set(L.joint);
    M.desk.color.set(L.desk[0]);
    M.desk.roughness = L.desk[1];
    M.desk.metalness = L.desk[2];
    M.pedestal.color.set(L.pedestal);
    M.floor.color.set(L.floor[0]);
    M.floor.opacity = L.floor[1];
    M.line.color.set(L.line[0]);
    M.line.opacity = L.line[1];
    M.zone.color.set(L.zone[0]);
    M.zone.opacity = L.zone[1];
    M.core.color.set(L.core[0]).multiplyScalar(L.core[1]);
    M.ring.color.set(L.ring[0]).multiplyScalar(L.ring[1]);
    M.wire.color.set(L.ring[0]).multiplyScalar(L.ring[1] * 0.6);
    [M.shell, M.joint, M.desk, M.pedestal].forEach((m) => (m.envMapIntensity = L.env));
    pillarMat.uniforms.uColor.value.set(L.pillar[0]);
    pillarMat.uniforms.uOpacity.value = L.pillar[1];
    beamM.uniforms.uColor.value.set(L.beams[0]);
    beamM.uniforms.uOpacity.value = L.beams[1];
    dustMat.color.set(L.dust[0]);
    dustMat.opacity = L.dust[1];
    dust.visible = L.dust[1] > 0;
    hemi.color.set(L.hemi[0]);
    hemi.groundColor.set(L.hemi[1]);
    hemi.intensity = L.hemi[2];
    key.color.set(L.key[0]);
    key.intensity = L.key[1];
    key.castShadow = L.shadows;
    rim.color.set(L.rim[0]);
    rim.intensity = L.rim[1];
    corePt.color.set(L.corePt[0]);
    corePt.intensity = L.corePt[1];
    bloom.strength = L.bloom[0];
    bloom.radius = L.bloom[1];
    bloom.threshold = L.bloom[2];
    tokenMesh.material.color.set(L.token).multiplyScalar(2.2);
    camera.fov = L.cam.fov;
    camera.updateProjectionMatrix();
    agents.forEach((a) => {
      applyStatus(a);
      drawScreen(a);
    });
    if (intro && !reduce) {
      cam.intro = 1;
      focused = null;
    }
  }

  /* ---------------------------------------------------------- loop */
  function resize() {
    const w = Math.max(1, container.clientWidth);
    const h = Math.max(1, container.clientHeight);
    renderer.setSize(w, h, false);
    composer.setSize(w, h);
    bloom.resolution.set(w, h);
    camera.aspect = w / h;
    camera.updateProjectionMatrix();
  }
  const ro = new ResizeObserver(resize);
  ro.observe(container);
  resize();

  const pv = new THREE.Vector3();
  function project(p, el, opacity, minY = 0) {
    pv.copy(p).project(camera);
    if (pv.z > 1 || pv.z < -1) {
      el.style.opacity = "0";
      return;
    }
    const x = (pv.x * 0.5 + 0.5) * container.clientWidth;
    const y = Math.max(minY, (-pv.y * 0.5 + 0.5) * container.clientHeight);
    el.style.transform = `translate3d(${x.toFixed(1)}px,${y.toFixed(1)}px,0)`;
    el.style.opacity = String(opacity);
  }

  let visible = true;
  const io = new IntersectionObserver((entries) => (visible = entries[0]?.isIntersecting ?? true));
  io.observe(container);
  let raf = 0;
  let last = performance.now();
  let scrI = 0;
  let elapsed = 0;
  let lost = false;
  canvas.addEventListener("webglcontextlost", (e) => {
    e.preventDefault();
    lost = true;
    opts.onError?.(new Error("WebGL context lost"));
  });

  function frame(now) {
    raf = requestAnimationFrame(frame);
    const dt = Math.min(0.05, (now - last) / 1000);
    last = now;
    if (!visible || document.hidden || lost) return;
    elapsed += dt;
    const t = elapsed;
    agents.forEach((a) => updateAgent(a, t, dt));
    const list = [...agents.values()];
    for (let n = 0; n < Math.min(3, list.length); n++) drawScreen(list[scrI++ % list.length]);

    rings.forEach((r, i) => {
      r.rotation.y += dt * (0.25 + i * 0.18) * (i % 2 ? -1 : 1);
      r.rotation.x += dt * 0.07 * (i + 1);
    });
    shell.rotation.y -= dt * 0.15;
    orb.scale.setScalar(1 + Math.sin(t * 2.2) * 0.05);
    pulses.forEach((p) => {
      if (p.t >= 1) return;
      p.t = Math.min(1, p.t + dt / 2.4);
      p.p.scale.setScalar(1 + p.t * 9);
      p.m.opacity = (1 - p.t) * 0.9;
    });
    dust.rotation.y += dt * 0.01;

    // light streams into the core
    for (let i = sparks.length - 1; i >= 0; i--) {
      sparks[i].t += dt * sparks[i].sp;
      if (sparks[i].t > 1) sparks.splice(i, 1);
    }
    let n = 0;
    for (const s of sparks) {
      if (s.t < 0) continue;
      const u = s.t;
      const iu = 1 - u;
      tmpV.set(0, 0, 0).addScaledVector(s.a, iu * iu).addScaledVector(s.c, 2 * iu * u).addScaledVector(s.b, u * u);
      const sc = 0.6 + Math.sin(u * Math.PI) * 0.8;
      tmpM.makeScale(sc, sc, sc).setPosition(tmpV);
      streamMesh.setMatrixAt(n, tmpM);
      streamMesh.setColorAt(n, s.col);
      n++;
    }
    streamMesh.count = n;
    streamMesh.instanceMatrix.needsUpdate = true;
    if (streamMesh.instanceColor) streamMesh.instanceColor.needsUpdate = true;

    // candidate tokens
    for (let i = tokens.length - 1; i >= 0; i--) {
      tokens[i].t += dt * tokens[i].sp;
      if (tokens[i].t > 1) tokens.splice(i, 1);
    }
    let m = 0;
    for (const k of tokens) {
      if (k.t < 0) continue;
      const u = k.t < 0.5 ? 2 * k.t * k.t : 1 - Math.pow(-2 * k.t + 2, 2) / 2;
      const a = k.a0 + k.d * u;
      tmpV.copy(P(a, k.r, 0.55 + Math.sin(u * Math.PI) * 1.4));
      const sc = 0.7 + Math.sin(u * Math.PI) * 0.6;
      tmpM.makeScale(sc, sc, sc).setPosition(tmpV);
      tokenMesh.setMatrixAt(m++, tmpM);
    }
    tokenMesh.count = m;
    tokenMesh.instanceMatrix.needsUpdate = true;

    updateCamera(dt, t);
    composer.render();

    // tags follow the robots
    const topSafe = opts.topSafe ? opts.topSafe() : 0;
    // Only the newest approval opens its card; the rest pulse until it's handled.
    let primary = null;
    agents.forEach((a) => {
      if (a.status === "needs" && a.approval && (!primary || (a.needsSince || 0) > (primary.needsSince || 0))) primary = a;
    });
    agents.forEach((a) => {
      const isFocus = !!focused && focused.id === a.id;
      const waiting = a.status === "needs" && !!a.approval;
      a.tag.classList.toggle("focus", isFocus);
      a.tag.classList.toggle("needs", a === primary);
      a.tag.classList.toggle("waiting", waiting && a !== primary);
      const open = isFocus || a === primary;
      const d = camera.position.distanceTo(a.pos);
      const op = open ? 1 : Math.max(0.28, Math.min(1, 1.5 - (d - 14) / 16));
      project(tmpV.copy(a.pos).setY(2.75 + a.r.body.position.y), a.tag, op, open ? topSafe : 0);
      if (open) renderDetail(a);
    });
    zoneTags.forEach((z) => project(z.p, z.el, 1));
    pv.copy(CORE_PT).project(camera);
    flare.style.transform = `translate3d(${((pv.x * 0.5 + 0.5) * container.clientWidth).toFixed(1)}px,${((-pv.y * 0.5 + 0.5) * container.clientHeight).toFixed(1)}px,0)`;
  }

  relayout();
  setLook(look, !reduce);
  raf = requestAnimationFrame((t) => {
    last = t;
    frame(t);
  });

  function dispose() {
    cancelAnimationFrame(raf);
    ro.disconnect();
    io.disconnect();
    canvas.removeEventListener("pointerdown", onDown);
    canvas.removeEventListener("pointermove", onMove);
    canvas.removeEventListener("pointerup", onUp);
    canvas.removeEventListener("wheel", onWheel);
    scene.traverse((o) => {
      o.geometry?.dispose?.();
      const mats = Array.isArray(o.material) ? o.material : o.material ? [o.material] : [];
      mats.forEach((mt) => {
        mt.map?.dispose?.();
        mt.dispose?.();
      });
    });
    reflector?.getRenderTarget?.().dispose();
    envTex.dispose();
    pmrem.dispose();
    composer.dispose?.();
    renderer.dispose();
    canvas.remove();
    flare.remove();
    labelsHost.remove();
  }

  return { setLook, sync, setZones, deliver, stream, flow, focus, pulse: () => pulse(), dispose };
}
