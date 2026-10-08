"use client";

import { useEffect, useRef, useState, useSyncExternalStore } from "react";
import { Canvas, useFrame, useThree } from "@react-three/fiber";
import { Bloom, EffectComposer } from "@react-three/postprocessing";
import { getSceneState, getServerSceneState, subscribeScene } from "@/lib/scene-bus";
import { Stage } from "./heroes";

/**
 * The first paint is a single bloomed frame. A second pair of frames, after the
 * page has gone quiet, decides whether this GPU can hold a continuous loop.
 * Software GL stays on demand and paints again when the page scrolls.
 */
function DelayedLive({ onFast }: { onFast: () => void }) {
  const invalidate = useThree((state) => state.invalidate);
  const arm = useRef(false);
  const frames = useRef(0);
  const done = useRef(false);
  useEffect(() => {
    const id = window.setTimeout(() => {
      arm.current = true;
      invalidate();
    }, 6500);
    return () => window.clearTimeout(id);
  }, [invalidate]);
  useFrame((_, delta) => {
    if (!arm.current || done.current) return;
    frames.current += 1;
    if (frames.current < 2) {
      invalidate();
      return;
    }
    done.current = true;
    if (delta < 0.05) onFast();
  });
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
  const [live, setLive] = useState(false);

  useEffect(() => {
    const onVisibility = () => setHidden(document.hidden);
    document.addEventListener("visibilitychange", onVisibility);
    return () => document.removeEventListener("visibilitychange", onVisibility);
  }, []);

  return (
    <div className="argus-canvas" aria-hidden>
      <Canvas
        dpr={[1, 1.5]}
        frameloop={hidden ? "never" : live ? "always" : "demand"}
        camera={{ position: [0, 0.2, 6.6], fov: 42 }}
        gl={{ antialias: false, alpha: false, stencil: false, powerPreference: "high-performance" }}
        onCreated={({ gl }) => {
          gl.setClearColor("#07081a", 1);
          onReady?.();
        }}
      >
        <DelayedLive onFast={() => setLive(true)} />
        <ScrollFrames active={!live} />
        <ambientLight intensity={0.35} />
        <pointLight position={[3, 2, 4]} intensity={16} color="#b9a8ff" />
        <Stage scene={snap.scene} progress={progress} settle={!live} />
        <EffectComposer>
          <Bloom intensity={1.2} luminanceThreshold={0.2} mipmapBlur />
        </EffectComposer>
      </Canvas>
    </div>
  );
}
