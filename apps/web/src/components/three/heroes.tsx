"use client";

import { useEffect, useMemo, useRef, useState, type MutableRefObject } from "react";
import { useFrame, useThree } from "@react-three/fiber";
import { Float, Html } from "@react-three/drei";
import * as THREE from "three";
import { SCENE_IDS, type SceneAnchor, type SceneId, type ScenePins } from "@/lib/scene-bus";
import { prefersReducedMotion } from "@/lib/motion";

const RING_VERT = `
  varying vec2 vUv;
  varying vec3 vN;
  void main() {
    vUv = uv;
    vN = normalize(normalMatrix * normal);
    gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0);
  }
`;

const RING_FRAG = `
  uniform float uProgress;
  uniform float uFade;
  uniform float uClip;
  uniform vec2 uRes;
  uniform vec4 uCard;
  uniform vec4 uBtn;
  uniform vec3 uColor;
  uniform vec3 uHot;
  varying vec2 vUv;
  varying vec3 vN;
  float cover(vec4 r, vec2 p) {
    if (r.z <= r.x || r.w <= r.y) return 0.0;
    float ix = smoothstep(r.x - 0.01, r.x + 0.03, p.x) * smoothstep(r.z + 0.01, r.z - 0.03, p.x);
    float iy = smoothstep(r.y - 0.01, r.y + 0.03, p.y) * smoothstep(r.w + 0.01, r.w - 0.03, p.y);
    return ix * iy;
  }
  void main() {
    float reveal = smoothstep(uProgress + 0.02, uProgress - 0.06, vUv.x);
    float rim = pow(1.0 - abs(dot(normalize(vN), vec3(0.0, 0.0, 1.0))), 1.2);
    vec3 col = mix(uColor, uHot, clamp(rim + 0.35, 0.0, 1.0));
    float side = uClip > 0.0 ? smoothstep(uClip, uClip + 0.16, gl_FragCoord.x / max(uRes.x, 1.0)) : 1.0;
    vec2 suv = gl_FragCoord.xy / max(uRes, vec2(1.0));
    suv.y = 1.0 - suv.y;
    float ui = max(cover(uCard, suv), cover(uBtn, suv));
    float alpha = reveal * (0.55 + rim) * uFade * side * mix(1.0, 0.38, ui);
    if (alpha < 0.02) discard;
    gl_FragColor = vec4(col, alpha);
  }
`;

const SCORE_FRAG = `
  uniform float uProgress;
  uniform float uFade;
  uniform float uFlash;
  uniform vec3 uColor;
  uniform vec3 uHot;
  varying vec2 vUv;
  varying vec3 vN;
  void main() {
    float fill = step(vUv.x, uProgress);
    float head = smoothstep(0.04, 0.0, abs(vUv.x - uProgress));
    float rim = pow(1.0 - abs(dot(normalize(vN), vec3(0.0, 0.0, 1.0))), 1.2);
    vec3 col = mix(uColor, uHot, clamp(head + rim + fill * 0.35 + uFlash * 0.9, 0.0, 1.0));
    float alpha = (0.28 + fill * 0.7 + head) * (0.55 + rim) * uFade;
    if (alpha < 0.02) discard;
    gl_FragColor = vec4(col, alpha);
  }
`;

const GRID_FRAG = `
  uniform float uTime;
  uniform float uFade;
  varying vec2 vUv;
  void main() {
    vec2 uv = vUv;
    uv.y = fract(uv.y * 18.0 + uTime * 0.15);
    float gx = abs(fract(vUv.x * 22.0) - 0.5);
    float gy = abs(fract(vUv.y * 28.0 - uTime * 0.18) - 0.5);
    float line = smoothstep(0.045, 0.0, gx) + smoothstep(0.04, 0.0, gy);
    float depth = smoothstep(0.05, 0.85, vUv.y);
    vec3 col = mix(vec3(0.23, 0.17, 1.0), vec3(0.91, 0.89, 1.0), depth * 0.55);
    float alpha = line * depth * uFade;
    if (alpha < 0.02) discard;
    gl_FragColor = vec4(col, alpha);
  }
`;

function useProgressUniform(progress: MutableRefObject<number>, fade: MutableRefObject<Record<SceneId, number>>, id: SceneId) {
  const mat = useRef<THREE.ShaderMaterial>(null);
  useFrame(({ clock }) => {
    const material = mat.current;
    if (!material) return;
    material.uniforms.uProgress.value = progress.current;
    material.uniforms.uFade.value = fade.current[id];
    if (material.uniforms.uTime) material.uniforms.uTime.value = clock.elapsedTime;
  });
  return mat;
}

function screenRect(selector: string, width: number, height: number, target: THREE.Vector4) {
  const node = document.querySelector(selector);
  if (!node || width < 1 || height < 1) {
    target.set(0, 0, 0, 0);
    return;
  }
  const rect = node.getBoundingClientRect();
  const pad = 12;
  target.set((rect.left - pad) / width, (rect.top - pad) / height, (rect.right + pad) / width, (rect.bottom + pad) / height);
}

function shaderUniforms(extra?: Record<string, THREE.IUniform>) {
  return {
    uProgress: { value: 0 },
    uFade: { value: 1 },
    uColor: { value: new THREE.Color("#7B5CFF") },
    uHot: { value: new THREE.Color("#E9E4FF") },
    ...extra,
  };
}

export function HorizonRing({
  progress,
  fades,
  anchor,
}: {
  progress: MutableRefObject<number>;
  fades: MutableRefObject<Record<SceneId, number>>;
  anchor: SceneAnchor;
}) {
  const mat = useRef<THREE.ShaderMaterial>(null);
  const mesh = useRef<THREE.Mesh>(null);
  const intro = useRef(0);
  const invalidate = useThree((state) => state.invalidate);
  const size = useThree((state) => state.size);
  const uniforms = useMemo(
    () =>
      shaderUniforms({
        uClip: { value: 0 },
        uRes: { value: new THREE.Vector2(1, 1) },
        uCard: { value: new THREE.Vector4() },
        uBtn: { value: new THREE.Vector4() },
      }),
    [],
  );
  useFrame(({ clock }, delta) => {
    const material = mat.current;
    if (!material) return;
    if (prefersReducedMotion()) intro.current = 1;
    else if (intro.current < 1) {
      const step = delta > 0.08 ? Math.max(delta, 0.2) : delta;
      intro.current = Math.min(1, intro.current + step / 1.6);
      invalidate();
    }
    const drawn = 1 - Math.pow(1 - intro.current, 3);
    material.uniforms.uProgress.value = drawn;
    material.uniforms.uClip.value = anchor === "top" ? 0.4 : 0;
    material.uniforms.uRes.value.set(size.width, size.height);
    const card = material.uniforms.uCard.value as THREE.Vector4;
    const button = material.uniforms.uBtn.value as THREE.Vector4;
    if (anchor === "top") {
      screenRect("#top .argus-sample-card", size.width, size.height, card);
      screenRect("#top .argus-capsule", size.width, size.height, button);
    } else {
      card.set(0, 0, 0, 0);
      button.set(0, 0, 0, 0);
    }
    const leave = anchor === "top" ? progress.current : 0;
    material.uniforms.uFade.value = fades.current.ring * (1 - leave * 0.72);
    if (material.uniforms.uTime) material.uniforms.uTime.value = clock.elapsedTime;
    if (mesh.current) mesh.current.rotation.x = 1.25 + leave * 0.55;
  });
  return (
    <mesh ref={mesh} rotation={[1.25, 0, 0]} position={[0, 0.45, 0]}>
      <torusGeometry args={[2.35, 0.062, 16, 220]} />
      <shaderMaterial
        ref={mat}
        transparent
        depthWrite={false}
        uniforms={uniforms}
        vertexShader={RING_VERT}
        fragmentShader={RING_FRAG}
      />
    </mesh>
  );
}

export function ScoreRing({
  progress,
  fades,
  anchor,
}: {
  progress: MutableRefObject<number>;
  fades: MutableRefObject<Record<SceneId, number>>;
  anchor: SceneAnchor;
}) {
  const mat = useProgressUniform(progress, fades, "score");
  const intro = useRef(0);
  const invalidate = useThree((state) => state.invalidate);
  const uniforms = useMemo(() => shaderUniforms({ uFlash: { value: 0 } }), []);
  useFrame((_, delta) => {
    const material = mat.current;
    if (!material) return;
    if (anchor === "behind") {
      if (prefersReducedMotion()) intro.current = 1;
      else if (intro.current < 1) {
        const step = delta > 0.08 ? Math.max(delta, 0.2) : delta;
        intro.current = Math.min(1, intro.current + step / 1.2);
        invalidate();
      }
      const fill = (1 - Math.pow(1 - intro.current, 3)) * 0.75;
      material.uniforms.uProgress.value = fill;
      material.uniforms.uFlash.value = fill >= 0.74 ? 1 : 0;
      return;
    }
    if (anchor !== "halo") return;
    const fill = Math.min(0.75, (progress.current / 0.4) * 0.75);
    material.uniforms.uProgress.value = fill;
    material.uniforms.uFlash.value = fill >= 0.74 ? 1 : 0;
  });
  return (
    <mesh>
      <torusGeometry args={[1.55, 0.11, 28, 160]} />
      <shaderMaterial
        ref={mat}
        transparent
        depthWrite={false}
        uniforms={uniforms}
        vertexShader={RING_VERT}
        fragmentShader={SCORE_FRAG}
      />
    </mesh>
  );
}

export function GlassSphere({ fades }: { fades: MutableRefObject<Record<SceneId, number>> }) {
  const comet = useRef<THREE.Group>(null);
  const shell = useRef<THREE.Mesh>(null);
  useFrame(({ clock }) => {
    const fade = fades.current.sphere;
    const glass = shell.current?.material;
    if (glass && "opacity" in glass) {
      glass.transparent = true;
      glass.opacity = fade;
    }
    const t = clock.elapsedTime * 1.15;
    comet.current?.position.set(Math.cos(t) * 1.85, Math.sin(t * 0.65) * 0.55, Math.sin(t) * 1.85);
    comet.current?.traverse((obj) => {
      const mesh = obj as THREE.Mesh;
      if (mesh.material && "opacity" in mesh.material) {
        const material = mesh.material as THREE.Material;
        material.transparent = true;
        material.opacity = fades.current.sphere;
      }
    });
  });
  return (
    <Float speed={1.4} rotationIntensity={0.25} floatIntensity={0.35}>
      <mesh ref={shell}>
        <sphereGeometry args={[1.25, 64, 64]} />
        <meshPhysicalMaterial
          transmission={1}
          roughness={0.08}
          thickness={1.4}
          iridescence={0.6}
          iridescenceIOR={1.3}
          ior={1.45}
          color="#b9a8ff"
          emissive="#3b2bff"
          emissiveIntensity={0.35}
          transparent
        />
      </mesh>
      <group ref={comet}>
        <mesh>
          <sphereGeometry args={[0.055, 16, 16]} />
          <meshBasicMaterial color="#e9e4ff" />
        </mesh>
        <pointLight intensity={18} distance={5} color="#e9e4ff" />
      </group>
    </Float>
  );
}

export function Eclipse({
  progress,
  fades,
  anchor = "center",
}: {
  progress: MutableRefObject<number>;
  fades: MutableRefObject<Record<SceneId, number>>;
  anchor?: SceneAnchor;
}) {
  const dark = useRef<THREE.Mesh>(null);
  const bright = useRef<THREE.Mesh>(null);
  useFrame(() => {
    if (!dark.current) return;
    const slide = anchor === "aside" ? 0.82 : progress.current;
    dark.current.position.x = -2.35 + slide * 1.7;
    const fade = fades.current.eclipse;
    const material = dark.current.material as THREE.MeshBasicMaterial;
    material.opacity = fade;
    const glow = bright.current?.material as THREE.MeshBasicMaterial | undefined;
    if (glow) glow.opacity = fade;
  });
  return (
    <group>
      <mesh ref={bright} position={[0, 0, -0.35]}>
        <sphereGeometry args={[1.15, 48, 48]} />
        <meshBasicMaterial color="#7b5cff" transparent />
      </mesh>
      <pointLight position={[0, 0, -0.8]} intensity={30} color="#e9e4ff" distance={6} />
      <mesh ref={dark} position={[-2.35, 0, 0.15]}>
        <sphereGeometry args={[1.12, 48, 48]} />
        <meshBasicMaterial color="#07081a" transparent />
      </mesh>
    </group>
  );
}

function latLng(lat: number, lng: number, radius: number) {
  const latR = (lat * Math.PI) / 180;
  const lonR = (lng * Math.PI) / 180;
  return new THREE.Vector3(
    radius * Math.cos(latR) * Math.cos(lonR),
    radius * Math.sin(latR),
    radius * Math.cos(latR) * Math.sin(lonR),
  );
}

const PINS = [
  { lat: 12.97, lng: 77.59, label: "Bengaluru" },
  { lat: 51.51, lng: -0.13, label: "London" },
];

const CLAIM_PINS = [
  { lat: 12.97, lng: 77.59, label: "3,000 HRs" },
  { lat: 51.51, lng: -0.13, label: "4.9 ★ Google" },
];

/** Holds both claim cities on the front of the globe, labels inside a 1280 and 1440 viewport. */
const CLAIM_YAW = (316 * Math.PI) / 180;

function GlobePin({ lat, lng, label }: { lat: number; lng: number; label: string }) {
  const surface = useMemo(() => latLng(lat, lng, 1.62), [lat, lng]);
  const outer = useMemo(() => latLng(lat, lng, 2.05), [lat, lng]);
  const line = useMemo(() => {
    const geometry = new THREE.BufferGeometry().setFromPoints([surface, outer]);
    return new THREE.Line(geometry, new THREE.LineBasicMaterial({ color: "#e9e4ff" }));
  }, [surface, outer]);
  const side = outer.x >= 0 ? "is-right" : "is-left";
  return (
    <group>
      <primitive object={line} />
      <Html position={outer} zIndexRange={[8, 0]} style={{ pointerEvents: "none" }}>
        <span className={`argus-pin-label ${side}`}>{label}</span>
      </Html>
    </group>
  );
}

export function DotGlobe({
  fades,
  pins = PINS,
}: {
  fades: MutableRefObject<Record<SceneId, number>>;
  pins?: { lat: number; lng: number; label: string }[];
}) {
  const [geometry, setGeometry] = useState<THREE.BufferGeometry | null>(null);
  const group = useRef<THREE.Group>(null);
  const material = useRef<THREE.PointsMaterial>(null);
  const invalidate = useThree((state) => state.invalidate);

  useEffect(() => {
    let dead = false;
    const image = new Image();
    image.src = "/argus/world.webp";
    image.onload = () => {
      if (dead) return;
      const canvas = document.createElement("canvas");
      canvas.width = image.width;
      canvas.height = image.height;
      const context = canvas.getContext("2d");
      if (!context) return;
      context.drawImage(image, 0, 0);
      const { data, width, height } = context.getImageData(0, 0, canvas.width, canvas.height);
      const land: number[] = [];
      for (let y = 0; y < height; y += 1) {
        for (let x = 0; x < width; x += 1) {
          const index = (y * width + x) * 4;
          if (data[index] > 80) land.push(x, y);
        }
      }
      const landCount = land.length / 2;
      const count = Math.min(20000, landCount);
      const positions = new Float32Array(count * 3);
      const radius = 1.6;
      for (let i = 0; i < count; i += 1) {
        const pick = Math.floor(((i + 0.5) * landCount) / count);
        const x = land[pick * 2];
        const y = land[pick * 2 + 1];
        const lon = (x / width) * Math.PI * 2 - Math.PI;
        const lat = Math.PI / 2 - (y / height) * Math.PI;
        positions[i * 3] = radius * Math.cos(lat) * Math.cos(lon);
        positions[i * 3 + 1] = radius * Math.sin(lat);
        positions[i * 3 + 2] = radius * Math.cos(lat) * Math.sin(lon);
      }
      const next = new THREE.BufferGeometry();
      next.setAttribute("position", new THREE.BufferAttribute(positions, 3));
      setGeometry(next);
      invalidate();
    };
    return () => {
      dead = true;
    };
  }, [invalidate]);

  const claims = pins === CLAIM_PINS;
  useFrame((_, delta) => {
    if (group.current) {
      if (claims) group.current.rotation.y = CLAIM_YAW;
      else if (!prefersReducedMotion()) group.current.rotation.y += delta * 0.08;
    }
    if (material.current) material.current.opacity = fades.current.globe;
  });

  return (
    <group ref={group}>
      <mesh>
        <sphereGeometry args={[1.55, 32, 32]} />
        <meshBasicMaterial color="#14173d" />
      </mesh>
      {geometry ? (
        <points geometry={geometry} frustumCulled={false}>
          <pointsMaterial ref={material} color="#b9a8ff" size={0.028} sizeAttenuation transparent depthWrite={false} />
        </points>
      ) : null}
      {pins.map((pin) => (
        <GlobePin key={pin.label} lat={pin.lat} lng={pin.lng} label={pin.label} />
      ))}
    </group>
  );
}

export function GridFloor({ fades }: { fades: MutableRefObject<Record<SceneId, number>> }) {
  const mat = useRef<THREE.ShaderMaterial>(null);
  const uniforms = useMemo(() => shaderUniforms({ uTime: { value: 0 } }), []);
  useFrame(({ clock }) => {
    if (!mat.current) return;
    mat.current.uniforms.uTime.value = clock.elapsedTime;
    mat.current.uniforms.uFade.value = fades.current.grid;
  });
  return (
    <mesh rotation={[-Math.PI / 2.15, 0, 0]} position={[0, -1.35, -2]}>
      <planeGeometry args={[26, 26, 1, 1]} />
      <shaderMaterial ref={mat} transparent depthWrite={false} uniforms={uniforms} vertexShader={RING_VERT} fragmentShader={GRID_FRAG} />
    </mesh>
  );
}

const CAM: Record<SceneId, { pos: THREE.Vector3; look: THREE.Vector3 }> = {
  ring: { pos: new THREE.Vector3(0, 0.2, 6.6), look: new THREE.Vector3(0, 0.45, 0) },
  score: { pos: new THREE.Vector3(0, 0, 5.4), look: new THREE.Vector3(0, 0, 0) },
  sphere: { pos: new THREE.Vector3(0.35, 0.15, 4.3), look: new THREE.Vector3(0, 0, 0) },
  eclipse: { pos: new THREE.Vector3(0, 0, 5.2), look: new THREE.Vector3(0, 0, 0) },
  globe: { pos: new THREE.Vector3(0, 0.15, 5), look: new THREE.Vector3(0, 0, 0) },
  grid: { pos: new THREE.Vector3(0, 1.7, 4.2), look: new THREE.Vector3(0, -0.4, -2) },
};

function placed(id: SceneId, anchor: SceneAnchor): { position: [number, number, number]; scale: number } {
  if (id === "grid") return { position: [0, 0, 0], scale: 1 };
  if (id === "ring" && anchor === "top") return { position: [0.45, 0.2, 0], scale: 1.08 };
  if (id === "score" && anchor === "halo") return { position: [0.15, 0.95, 0], scale: 0.5 };
  if (id === "sphere" && anchor === "right") return { position: [1.7, 0.05, 0], scale: 0.72 };
  if (id === "eclipse" && anchor === "right") return { position: [1.55, 0.08, 0], scale: 0.74 };
  if (id === "score" && anchor === "behind") return { position: [1.62, 0.05, 0], scale: 0.42 };
  if (id === "eclipse" && anchor === "aside") return { position: [1.95, 0.02, 0], scale: 0.52 };
  if (id === "globe" && anchor === "aside") return { position: [0.15, -1.72, 0], scale: 0.4 };
  if (id === "globe" && anchor === "right") return { position: [1.22, -0.4, 0], scale: 0.55 };
  return { position: [0, 0, 0], scale: 1 };
}

export function Stage({
  scene,
  progress,
  anchor,
  pins,
  shown,
  settle = false,
}: {
  scene: SceneId;
  progress: MutableRefObject<number>;
  anchor: SceneAnchor;
  pins: ScenePins;
  shown: boolean;
  /** Slow renderers snap to the active object instead of lerping across frames. */
  settle?: boolean;
}) {
  const fades = useRef<Record<SceneId, number>>({
    ring: scene === "ring" ? 1 : 0,
    score: 0,
    sphere: 0,
    eclipse: 0,
    globe: 0,
    grid: 0,
  });
  const [alive, setAlive] = useState<SceneId[]>([scene]);
  const aliveRef = useRef(alive);
  const desired = useRef(new THREE.Vector3());
  const look = useRef(new THREE.Vector3());

  useFrame(({ camera }, delta) => {
    const next: SceneId[] = [];
    for (const id of SCENE_IDS) {
      const withFloor = shown && scene === "globe" && anchor === "aside" && id === "grid";
      const goal = shown && (id === scene || withFloor) ? 1 : 0;
      fades.current[id] = settle ? goal : fades.current[id] + (goal - fades.current[id]) * Math.min(1, delta * 2.4);
      if (fades.current[id] > 0.045 || id === scene) next.push(id);
    }
    const previous = aliveRef.current;
    if (previous.length !== next.length || previous.some((id, index) => id !== next[index])) {
      aliveRef.current = next;
      setAlive(next);
    }

    desired.current.set(0, 0, 0);
    look.current.set(0, 0, 0);
    let weight = 0;
    for (const id of SCENE_IDS) {
      const fade = fades.current[id];
      if (fade < 0.02) continue;
      desired.current.addScaledVector(CAM[id].pos, fade);
      look.current.addScaledVector(CAM[id].look, fade);
      weight += fade;
    }
    if (weight < 0.02) return;
    desired.current.multiplyScalar(1 / weight);
    look.current.multiplyScalar(1 / weight);
    if (settle) camera.position.copy(desired.current);
    else camera.position.lerp(desired.current, 1 - Math.pow(0.05, delta));
    camera.lookAt(look.current);
  });

  const globePins = pins === "claims" ? CLAIM_PINS : pins === "none" ? [] : PINS;

  return (
    <>
      {alive.map((id) => {
        const spot = placed(id, anchor);
        return (
          <group key={id} position={spot.position} scale={spot.scale}>
            {id === "ring" ? <HorizonRing progress={progress} fades={fades} anchor={anchor} /> : null}
            {id === "score" ? <ScoreRing progress={progress} fades={fades} anchor={anchor} /> : null}
            {id === "sphere" ? <GlassSphere fades={fades} /> : null}
            {id === "eclipse" ? <Eclipse progress={progress} fades={fades} anchor={anchor} /> : null}
            {id === "globe" ? <DotGlobe fades={fades} pins={globePins} /> : null}
            {id === "grid" ? <GridFloor fades={fades} /> : null}
          </group>
        );
      })}
    </>
  );
}
