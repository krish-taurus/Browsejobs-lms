import * as THREE from 'three';
import { RoundedBoxGeometry } from 'three/addons/geometries/RoundedBoxGeometry.js';

// All geometry is generated here. No GLB, textures, or downloaded character model.
export function createTaurusRobot({ reducedMotion = false } = {}) {
  const root = new THREE.Group();
  root.name = 'Taurus';
  const geometries = new Set();
  const materials = new Set();
  let state = 'idle';
  let energy = 0;
  let lastTime = 0;
  let waveStarted = -Infinity;
  let disposed = false;

  function material(color, options = {}) {
    const m = new THREE.MeshStandardMaterial({
      color, roughness: 0.52, metalness: 0.02, ...options,
    });
    materials.add(m);
    return m;
  }
  const ivory = material(0xf1eee4);
  const emerald = material(0x164c3c, { roughness: 0.42 });
  const visor = material(0x0c342b, { roughness: 0.32 });
  const mint = material(0xa5f3d5, { emissive: 0x84dcb9, emissiveIntensity: 0.32 });
  const coreMaterial = material(0xa5f3d5, { emissive: 0x84dcb9, emissiveIntensity: 0.32 });

  function mesh(geometry, mat, parent, position = [0, 0, 0]) {
    geometries.add(geometry);
    const m = new THREE.Mesh(geometry, mat);
    m.position.set(...position);
    m.castShadow = true;
    m.receiveShadow = true;
    parent.add(m);
    return m;
  }
  function box(parent, size, pos, mat, radius = 0.26) {
    const m = mesh(new RoundedBoxGeometry(1, 1, 1, 4, radius), mat, parent, pos);
    m.scale.set(...size);
    return m;
  }
  function ball(parent, radius, pos, mat, scale = [1, 1, 1]) {
    const m = mesh(new THREE.SphereGeometry(radius, 24, 16), mat, parent, pos);
    m.scale.set(...scale);
    return m;
  }
  function capsule(parent, radius, totalHeight, pos, mat) {
    return mesh(new THREE.CapsuleGeometry(radius, Math.max(0.001, totalHeight - 2 * radius), 6, 16), mat, parent, pos);
  }
  function cylinder(parent, radius, height, pos, mat) {
    return mesh(new THREE.CylinderGeometry(radius, radius, height, 24), mat, parent, pos);
  }
  function group(parent, name, position) {
    const g = new THREE.Group();
    g.name = name;
    g.position.set(...position);
    parent.add(g);
    return g;
  }

  // Feet, shins, knees, thighs, hips.
  for (const side of [-1, 1]) {
    const x = side * 0.37;
    box(root, [0.62, 0.14, 0.88], [x, 0.10, 0.13], emerald, 0.3);
    box(root, [0.59, 0.35, 0.81], [x, 0.27, 0.12], ivory, 0.34);
    ball(root, 0.16, [x, 0.49, 0], emerald);
    capsule(root, 0.23, 0.99, [x, 0.96, 0], ivory);
    ball(root, 0.215, [x, 1.50, 0.015], emerald);
    capsule(root, 0.265, 0.96, [x, 2.00, 0], ivory);
    ball(root, 0.25, [x, 2.46, 0], emerald);
  }
  box(root, [0.97, 0.62, 0.63], [0, 2.68, 0.01], ivory, 0.32);
  cylinder(root, 0.39, 0.22, [0, 3.025, 0], emerald);

  const upper = group(root, 'upperBody', [0, 3.12, 0]);
  box(upper, [1.31, 1.25, 0.72], [0, 0.62, 0], ivory, 0.29);
  box(upper, [0.56, 0.61, 0.10], [0, 0.77, 0.37], emerald, 0.30);
  const core = ball(upper, 0.106, [0, 0.80, 0.44], coreMaterial, [1, 1, 0.35]);
  cylinder(upper, 0.23, 0.31, [0, 1.37, 0], emerald);

  const head = group(upper, 'head', [0, 2.05, 0]);
  box(head, [1.50, 1.22, 0.99], [0, 0, 0], ivory, 0.36);
  box(head, [1.18, 0.82, 0.105], [0, -0.015, 0.494], visor, 0.36);
  for (const side of [-1, 1]) ball(head, 0.16, [side * 0.76, 0.02, 0], emerald, [0.42, 1, 0.90]);
  const eyes = [-1, 1].map(side => capsule(head, 0.057, 0.255, [side * 0.28, 0.06, 0.562], mint));
  const smileCurve = new THREE.CatmullRomCurve3([
    new THREE.Vector3(-0.18, -0.205, 0.562),
    new THREE.Vector3(0, -0.26, 0.580),
    new THREE.Vector3(0.18, -0.205, 0.562),
  ]);
  const smile = mesh(new THREE.TubeGeometry(smileCurve, 20, 0.02, 8, false), mint, head);
  const openMouth = ball(head, 0.11, [0, -0.22, 0.568], mint, [1, 0.42, 0.16]);
  openMouth.visible = false;

  function makeArm(side, raised) {
    const shoulder = group(upper, side > 0 ? 'greetingShoulder' : 'restShoulder', [side * 0.79, 1.00, 0]);
    shoulder.rotation.z = raised ? 0.42 : -0.14;
    ball(shoulder, 0.245, [0, 0, 0], emerald);
    ball(shoulder, 0.237, [side * 0.06, 0.015, 0.07], ivory, [1, 1.02, 0.85]);
    capsule(shoulder, 0.185, 0.77, [0, -0.43, 0], ivory);
    const elbow = group(shoulder, 'elbow', [0, -0.83, 0]);
    ball(elbow, 0.18, [0, 0, 0], emerald);
    elbow.rotation.z = raised ? 1.90 : -0.05;
    capsule(elbow, 0.17, 0.68, [0, -0.39, 0], ivory);
    const hand = group(elbow, 'hand', [0, -0.77, 0]);
    ball(hand, 0.12, [0, 0, 0], emerald);
    box(hand, [0.30, 0.32, 0.20], [0, -0.16, 0.01], ivory, 0.30);
    for (let i = 0; i < 4; i++) {
      const x = (i - 1.5) * 0.074;
      const height = i === 0 || i === 3 ? 0.18 : 0.23;
      capsule(hand, 0.035, height, [x, -0.33 - height / 2, 0.015], emerald);
    }
    const thumb = capsule(hand, 0.044, 0.22, [-0.205, -0.205, 0.04], emerald);
    thumb.rotation.z = -0.62;
    return { shoulder, elbow, hand };
  }
  const restArm = makeArm(-1, false);
  const greetingArm = makeArm(1, true);
  const rig = { upper, head, eyes, smile, openMouth, core, restArm, greetingArm };

  function update(timeSeconds = 0) {
    if (disposed) return;
    const t = Number.isFinite(timeSeconds) ? timeSeconds : 0;
    lastTime = t;
    upper.position.y = 3.12 + (reducedMotion ? 0 : Math.sin(t * 1.5) * 0.016);
    head.rotation.z = reducedMotion ? 0 : Math.sin(t * 0.7) * 0.018;
    const blinkPhase = t % 4.7;
    const blink = reducedMotion || blinkPhase > 0.18
      ? 1 : Math.max(0.07, Math.abs(blinkPhase - 0.09) / 0.09);
    for (const eye of eyes) eye.scale.y = blink;
    const speaking = state === 'speaking' && energy > 0.015 && !reducedMotion;
    smile.visible = !speaking;
    openMouth.visible = speaking;
    openMouth.scale.y = 0.18 + energy * 0.85;
    const waveAge = Number.isFinite(waveStarted) ? t - waveStarted : 0;
    const envelope = !reducedMotion && Number.isFinite(waveStarted) && waveAge >= 0 && waveAge < 2.5 ? Math.sin(Math.PI * waveAge / 2.5) : 0;
    greetingArm.hand.rotation.z = envelope * Math.sin(waveAge * 9) * 0.26;
    greetingArm.elbow.rotation.z = 1.90 + envelope * Math.sin(waveAge * 6) * 0.10;
    const thinkingPulse = state === 'thinking' && !reducedMotion ? (Math.sin(t * 3) + 1) * 0.22 : 0;
    coreMaterial.emissiveIntensity = 0.30 + thinkingPulse + (state === 'listening' ? 0.20 : 0);
    coreMaterial.color.set(state === 'error' ? 0xc46f69 : 0xa5f3d5);
  }

  return {
    root, rig,
    update,
    get state() { return state; },
    setState(next) {
      if (!['idle', 'listening', 'thinking', 'speaking', 'error'].includes(next)) throw new RangeError('Unknown robot state: ' + next);
      state = next;
      if (next !== 'speaking') energy = 0;
      update(lastTime);
    },
    setSpeechEnergy(value) {
      energy = Number.isFinite(value) ? THREE.MathUtils.clamp(value, 0, 1) : 0;
      update(lastTime);
    },
    setReducedMotion(value) { reducedMotion = Boolean(value); update(lastTime); },
    wave() { waveStarted = lastTime; },
    dispose() {
      if (disposed) return;
      disposed = true;
      geometries.forEach(g => g.dispose());
      materials.forEach(m => m.dispose());
      root.removeFromParent();
    },
  };
}
