"use client";

import { useEffect, useRef, useState, useSyncExternalStore } from "react";
import { Canvas, useFrame, useThree } from "@react-three/fiber";
import { getSceneState, getServerSceneState, subscribeScene, type SceneId } from "@/lib/scene-bus";
import { Stage } from "./heroes";

/**
 * Continuous motion is the default. Only the glass sphere can trip the slow-frame
 * latch, and only while that section is on screen. Leaving it turns motion back on.
 * Software GL never uses this latch — it idles on its own path.
 */
function FrameBudget({
  scene,
  enabled,
  onSlow,
  onRecover,
}: {
  scene: SceneId;
  enabled: boolean;
  onSlow: () => void;
  onRecover: () => void;
}) {
  const slow = useRef(0);
  const seen = useRef(0);
  const latched = useRef(false);
  const sceneRef = useRef(scene);
  sceneRef.current = scene;
  useFrame((_, delta) => {
    if (!enabled) return;
    seen.current += 1;
    if (seen.current < 6) return;
    if (latched.current) {
      if (sceneRef.current !== "sphere") {
        latched.current = false;
        slow.current = 0;
        onRecover();
      }
      return;
    }
    if (sceneRef.current !== "sphere") {
      slow.current = 0;
      return;
    }
    if (delta > 0.1) slow.current += 1;
    else slow.current = 0;
    if (slow.current >= 30) {
      latched.current = true;
      onSlow();
    }
  });
  return null;
}

function isSoftwareRenderer(gl: { getContext: () => WebGLRenderingContext }): boolean {
  const context = gl.getContext();
  const ext = context.getExtension("WEBGL_debug_renderer_info");
  if (!ext) return false;
  const renderer = String(context.getParameter(ext.UNMASKED_RENDERER_WEBGL) ?? "");
  return /swiftshader|llvmpipe|softpipe|software/i.test(renderer);
}

/** Software GL draws the intro, then idles. Scroll still requests a frame. */
function SoftwareSettle({ active, onDone }: { active: boolean; onDone: () => void }) {
  const frames = useRef(0);
  const done = useRef(false);
  useFrame(() => {
    if (!active || done.current) return;
    frames.current += 1;
    if (frames.current >= 12) {
      done.current = true;
      onDone();
    }
  });
  return null;
}

function FollowScene({ token }: { token: string }) {
  const invalidate = useThree((state) => state.invalidate);
  useEffect(() => {
    invalidate();
  }, [token, invalidate]);
  return null;
}

function ScrollFrames({ active }: { active: boolean }) {
  const invalidate = useThree((state) => state.invalidate);
  useEffect(() => {
    if (!active) return;
    const onScroll = () => invalidate();
    window.addEventListener("scroll", onScroll, { passive: true });
    return () => window.removeEventListener("scroll", onScroll);
  }, [active, invalidate]);
  return null;
}

/**
 * One fixed WebGL canvas. Loaded with next/dynamic and ssr:false.
 * The world map stays in /public and is fetched in the browser.
 */
export default function SceneCanvas({ onReady }: { onReady?: () => void }) {
  const snap = useSyncExternalStore(subscribeScene, getSceneState, getServerSceneState);
  const progress = useRef(snap.progress);
  progress.current = snap.progress;
  const [hidden, setHidden] = useState(false);
  const [live, setLive] = useState(true);
  const [software, setSoftware] = useState(false);

  useEffect(() => {
    const onVisibility = () => setHidden(document.hidden);
    document.addEventListener("visibilitychange", onVisibility);
    return () => document.removeEventListener("visibilitychange", onVisibility);
  }, []);

  return (
    <div className="argus-canvas" aria-hidden>
      <Canvas
        dpr={software ? 1 : [1, 1.5]}
        frameloop={hidden ? "never" : live ? "always" : "demand"}
        camera={{ position: [0, 0.2, 6.6], fov: 42 }}
        gl={{ antialias: false, alpha: false, stencil: false, powerPreference: "high-performance" }}
        onCreated={({ gl }) => {
          gl.setClearColor("#ffffff", 1);
          if (isSoftwareRenderer(gl)) setSoftware(true);
          onReady?.();
        }}
      >
        <FrameBudget
          scene={snap.scene}
          enabled={!software}
          onSlow={() => setLive(false)}
          onRecover={() => setLive(true)}
        />
        <SoftwareSettle active={software} onDone={() => setLive(false)} />
        <FollowScene token={`${snap.scene}:${snap.anchor}:${snap.pins}:${snap.shown}:${snap.progress.toFixed(2)}`} />
        <ScrollFrames active={!live} />
        <ambientLight intensity={0.85} color="#ffffff" />
        <hemisphereLight args={["#ffffff", "#d1d1d6", 0.45]} />
        <directionalLight position={[4, 6, 5]} intensity={1.15} color="#ffffff" />
        <Stage
          scene={snap.scene}
          progress={progress}
          anchor={snap.anchor}
          pins={snap.pins}
          shown={snap.shown}
          settle={!live}
        />
      </Canvas>
    </div>
  );
}
