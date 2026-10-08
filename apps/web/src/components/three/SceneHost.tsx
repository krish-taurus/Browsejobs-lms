"use client";

import dynamic from "next/dynamic";
import { useEffect, useState } from "react";
import { usePathname } from "next/navigation";
import { isSceneId, prefersReducedMotion } from "@/lib/motion";
import { hideScene, isSceneAnchor, setSceneState, type ScenePins } from "@/lib/scene-bus";

const SceneCanvas = dynamic(() => import("./SceneCanvas"), { ssr: false });

/** Mounts the 3D canvas only after first paint, and only on pages that declare data-scene. */
export function SceneHost() {
  const pathname = usePathname();
  const [webgl, setWebgl] = useState(false);

  useEffect(() => {
    const sections = () => [...document.querySelectorAll<HTMLElement>("[data-scene]")];
    if (sections().length === 0) {
      hideScene();
      setWebgl(false);
      document.documentElement.classList.remove("argus-live");
      return;
    }

    const narrow = window.matchMedia("(max-width: 767px)");
    const reduced = prefersReducedMotion();
    let frame = 0;

    const measure = () => {
      const view = window.innerHeight || 1;
      for (const node of document.querySelectorAll<HTMLElement>("[data-canvas-clear]")) {
        const rect = node.getBoundingClientRect();
        const visible = Math.min(rect.bottom, view) - Math.max(rect.top, 0);
        if (visible > view * 0.08) {
          hideScene();
          return;
        }
      }
      for (const node of document.querySelectorAll<HTMLElement>(".argus-section")) {
        if (isSceneId(node.dataset.scene)) continue;
        const rect = node.getBoundingClientRect();
        if (rect.top < view * 0.82 && rect.bottom > view * 0.18) {
          hideScene();
          return;
        }
      }
      let best: HTMLElement | null = null;
      let bestRatio = 0;
      for (const node of sections()) {
        const rect = node.getBoundingClientRect();
        const visible = Math.min(rect.bottom, view) - Math.max(rect.top, 0);
        const ratio = visible / Math.min(view, Math.max(rect.height, 1));
        if (ratio > bestRatio) {
          bestRatio = ratio;
          best = node;
        }
      }
      const path = document.getElementById("below-75");
      if (best?.dataset.scene === "score" && path && isSceneId(path.dataset.scene)) {
        const prect = path.getBoundingClientRect();
        if (prect.top < view * 0.9 && prect.bottom > 0) best = path;
      }
      if (!best || bestRatio < 0.12) return;
      if (best.dataset.pinned === "true") {
        const rect = best.getBoundingClientRect();
        if (rect.top > -12 && rect.bottom > view * 0.9) return;
      }
      if (!isSceneId(best.dataset.scene)) {
        hideScene();
        return;
      }
      const rect = best.getBoundingClientRect();
      const progress = reduced
        ? 1
        : best.dataset.progress === "leave"
          ? Math.min(1, Math.max(0, -rect.top / (Math.max(rect.height, view) * 0.7)))
          : Math.min(1, Math.max(0, (view * 0.82 - rect.top) / (view * 0.7)));
      const anchor = isSceneAnchor(best.dataset.anchor) ? best.dataset.anchor : "center";
      const pins: ScenePins = best.dataset.pins === "claims" ? "claims" : best.dataset.pins === "none" ? "none" : "cities";
      setSceneState(best.dataset.scene, progress, { anchor, pins });
    };

    const onScroll = () => {
      cancelAnimationFrame(frame);
      frame = requestAnimationFrame(measure);
    };

    measure();
    const kick = window.setTimeout(() => setWebgl(!narrow.matches), 60);
    const onNarrow = () => setWebgl(!narrow.matches);
    window.addEventListener("scroll", onScroll, { passive: true });
    narrow.addEventListener("change", onNarrow);

    return () => {
      window.clearTimeout(kick);
      cancelAnimationFrame(frame);
      window.removeEventListener("scroll", onScroll);
      narrow.removeEventListener("change", onNarrow);
      document.documentElement.classList.remove("argus-live");
    };
  }, [pathname]);

  if (!webgl) return null;

  return (
    <SceneCanvas
      onReady={() => {
        document.documentElement.classList.add("argus-live");
      }}
    />
  );
}
