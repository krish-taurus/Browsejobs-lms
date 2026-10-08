"use client";

import { useEffect } from "react";
import { usePathname } from "next/navigation";
import { prefersReducedMotion } from "@/lib/motion";

/**
 * Lenis (lerp 0.1) synced to GSAP's ticker. Runs only on `.argus` pages, and
 * stays off when the visitor asks for reduced motion.
 */
export function MotionProvider({ children }: { children: React.ReactNode }) {
  const pathname = usePathname();

  useEffect(() => {
    if (prefersReducedMotion()) return;
    if (!document.querySelector(".argus")) return;

    let dead = false;
    let cleanup = () => {};

    void (async () => {
      const [{ default: gsap }, { ScrollTrigger }, { default: Lenis }] = await Promise.all([
        import("gsap"),
        import("gsap/ScrollTrigger"),
        import("lenis"),
      ]);
      if (dead) return;
      gsap.registerPlugin(ScrollTrigger);
      const lenis = new Lenis({ lerp: 0.1 });
      lenis.on("scroll", ScrollTrigger.update);
      const tick = (time: number) => {
        lenis.raf(time * 1000);
      };
      gsap.ticker.add(tick);
      gsap.ticker.lagSmoothing(0);
      document.documentElement.classList.add("argus-scroll");
      cleanup = () => {
        gsap.ticker.remove(tick);
        lenis.destroy();
        document.documentElement.classList.remove("argus-scroll");
      };
    })();

    return () => {
      dead = true;
      cleanup();
    };
  }, [pathname]);

  return children;
}
