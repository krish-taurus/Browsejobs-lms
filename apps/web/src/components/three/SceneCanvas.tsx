"use client";

import { useEffect, useRef, useState, useSyncExternalStore } from "react";
import { Canvas, useFrame, useThree } from "@react-three/fiber";
import { Bloom, EffectComposer } from "@react-three/postprocessing";
import { getSceneState, getServerSceneState, subscribeScene } from "@/lib/scene-bus";
import { Stage } from "./heroes";

/**
 * Continuous motion is the default. A device that stays slower than 100ms per
 * frame for 30 frames drops to on-demand painting. Scroll and scene changes
 * still request a frame, so the object keeps up with the section in view.
 * Bloom is the expensive pass, so it drops first when frames are already slow.
 */
function FrameBudget({ onDimBloom, onSlow }: { onDimBloom: () => void; onSlow: () => void }) {
  const slow = useRef(0);
  const seen = useRef(0);
  const dimmed = useRef(false);
  const stopped = useRef(false);
  useFrame((_, delta) => {
    seen.current += 1;
    if (seen.current < 6) return;
    if (delta > 0.1) slow.current += 1;
    else slow.current = 0;
    if (!dimmed.current && slow.current >= 4) {
      dimmed.current = true;
      onDimBloom();
    }
    if (!stopped.current && slow.current >= 30) {
      stopped.current = true;
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
  const [bloom, setBloom] = useState(true);
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
          gl.setClearColor("#07081a", 1);
          if (isSoftwareRenderer(gl)) {
            setSoftware(true);
            setBloom(false);
          }
          onReady?.();
        }}
      >
        <FrameBudget onDimBloom={() => setBloom(false)} onSlow={() => setLive(false)} />
        <SoftwareSettle active={software} onDone={() => setLive(false)} />
        <FollowScene token={`${snap.scene}:${snap.anchor}:${snap.pins}:${snap.shown}:${snap.progress.toFixed(2)}`} />
        <ScrollFrames active={!live} />
        <ambientLight intensity={0.35} />
        <pointLight position={[3, 2, 4]} intensity={16} color="#b9a8ff" />
        <Stage
          scene={snap.scene}
          progress={progress}
          anchor={snap.anchor}
          pins={snap.pins}
          shown={snap.shown}
          settle={!live}
        />
        {bloom ? (
          <EffectComposer multisampling={0}>
            <Bloom intensity={1.2} luminanceThreshold={0.2} mipmapBlur />
          </EffectComposer>
        ) : null}
      </Canvas>
    </div>
  );
}
