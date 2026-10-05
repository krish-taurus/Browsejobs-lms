"use client";

import { forwardRef, useEffect, useImperativeHandle, useRef, useState } from "react";
import type { TaurusRobotState, TaurusView } from "@/lib/taurus/mount-taurus";

export type TaurusRobotHandle = {
  wave: () => void;
};

/**
 * The full-body procedural robot (approved kit, Sept 2026) — mounted client-
 * only via a dynamic import so the WebGL renderer never touches SSR, torn
 * down on unmount, and swapped for the static reference PNG if WebGL isn't
 * available at all (a laptop with software rendering blocked, an old
 * browser) or the context is lost mid-session. Losing the 3D scene never
 * blocks text chat — see the page using this component.
 */
export const TaurusRobotScene = forwardRef<TaurusRobotHandle, { state: TaurusRobotState; speechEnergy?: number }>(
  function TaurusRobotScene({ state, speechEnergy = 0 }, ref) {
    const containerRef = useRef<HTMLDivElement | null>(null);
    const viewRef = useRef<TaurusView | null>(null);
    const [fallback, setFallback] = useState(false);

    useImperativeHandle(ref, () => ({
      wave: () => viewRef.current?.wave(),
    }), []);

    useEffect(() => {
      let cancelled = false;
      const container = containerRef.current;
      if (!container) return;

      import("@/lib/taurus/mount-taurus")
        .then(({ mountTaurus }) => {
          if (cancelled || !containerRef.current) return;
          try {
            const view = mountTaurus(containerRef.current, {
              onError: () => setFallback(true),
            });
            viewRef.current = view;
          } catch {
            setFallback(true);
          }
        })
        .catch(() => setFallback(true));

      return () => {
        cancelled = true;
        viewRef.current?.dispose();
        viewRef.current = null;
      };
      // Mounted once per visit to this page — state/energy are pushed via
      // the effects below rather than remounting the whole renderer.
      // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    useEffect(() => { viewRef.current?.setState(state); }, [state]);
    useEffect(() => { viewRef.current?.setSpeechEnergy(speechEnergy); }, [speechEnergy]);

    return (
      // Fills whatever box the caller gives it (a square, in the current
      // page) — sizing lives with the caller so the canvas and its backdrop
      // always share the same shape instead of drifting apart.
      <div className="absolute inset-0">
        <div ref={containerRef} className="absolute inset-0" aria-hidden={!fallback} />
        {fallback && (
          // eslint-disable-next-line @next/next/no-img-element -- static fallback only, no next/image benefit for a one-off decorative swap
          <img
            src="/img/employer/taurus-robot-reference.png"
            alt=""
            aria-hidden
            className="absolute inset-0 h-full w-full object-contain"
          />
        )}
      </div>
    );
  },
);
