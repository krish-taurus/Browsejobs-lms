"use client";

import dynamic from "next/dynamic";
import { useEffect, useRef, useState } from "react";

const HiringFloor = dynamic(() => import("@/components/recruiter/HiringFloor").then((mod) => mod.HiringFloor), {
  ssr: false,
});

/**
 * The real floor, inside a device frame.
 * Laptop on desktop, phone crop on mobile. The canvas mounts only once it
 * enters the viewport, so the hero can stay a static poster.
 */
export function MacFloor({ credit = true, tone = "light" }: { credit?: boolean; tone?: "light" | "dark" }) {
  const frame = useRef<HTMLDivElement>(null);
  const [scale, setScale] = useState(0.7);
  const [phone, setPhone] = useState(false);
  const [live, setLive] = useState(false);

  useEffect(() => {
    const node = frame.current;
    if (!node) return;
    const measure = () => {
      const narrow = window.matchMedia("(max-width: 767px)").matches;
      setPhone(narrow);
      const next = narrow ? node.clientHeight / 800 : node.clientWidth / 1280;
      if (next > 0) setScale(next);
    };
    measure();
    const resize = new ResizeObserver(measure);
    resize.observe(node);
    const seen = new IntersectionObserver(
      ([entry]) => {
        if (entry?.isIntersecting) setLive(true);
      },
      { rootMargin: "0px" },
    );
    seen.observe(node);
    return () => {
      resize.disconnect();
      seen.disconnect();
    };
  }, []);

  const caption = tone === "dark" ? "text-[#a1a1a6]" : "text-[#6e6e73]";

  return (
    <figure className="mx-auto w-[280px] md:w-full md:max-w-[1080px]">
      <div className="apple-rise rounded-[2.5rem] bg-[#1d1d1f] p-[11px] shadow-[0_30px_80px_rgba(0,0,0,0.28)] md:rounded-[1.15rem] md:p-3">
        <div className="mb-2 hidden justify-center md:flex" aria-hidden>
          <span className="h-1.5 w-1.5 rounded-full bg-[#3a3a3c]" />
        </div>
        <div
          ref={frame}
          className="relative aspect-[9/16] overflow-hidden rounded-[1.9rem] bg-[#01040a] md:aspect-[16/10] md:rounded-[0.65rem]"
        >
          <div className="pointer-events-none absolute left-1/2 top-2 z-10 h-5 w-24 -translate-x-1/2 rounded-full bg-black md:hidden" aria-hidden />
          <div
            className="absolute h-[800px] w-[1280px]"
            style={
              phone
                ? {
                    left: "50%",
                    top: "42%",
                    transform: `translate(-50%, -50%) scale(${scale})`,
                    transformOrigin: "center center",
                  }
                : {
                    left: 0,
                    top: 0,
                    transform: `scale(${scale})`,
                    transformOrigin: "top left",
                  }
            }
          >
            {live ? <HiringFloor variant="shot" credit={credit} frozenAtMs={52_000} /> : <FloorPoster />}
          </div>
        </div>
      </div>
      <div className="mx-auto hidden h-[10px] w-[22%] rounded-b-xl bg-[#c8c8cd] md:block" aria-hidden />
      <div className="mx-auto hidden h-[4px] w-[32%] rounded-b-md bg-[#e4e4e8] md:block" aria-hidden />
      <figcaption className={`mt-5 text-center text-[12px] ${caption}`}>Demo data. Not a live hiring desk.</figcaption>
    </figure>
  );
}

function FloorPoster() {
  return (
    <div className="grid h-[800px] w-[1280px] place-items-center bg-[#01040a] text-[#9aafc0]" aria-hidden>
      <div className="relative h-[420px] w-[420px]">
        <div className="absolute inset-0 rounded-full border border-[#00d4ff]/40" />
        <div className="absolute inset-[18%] rounded-full border border-[#00d4ff]/30" />
        <div className="absolute inset-[36%] rounded-full border border-[#c9a227]/50" />
        <div className="absolute left-1/2 top-1/2 h-3 w-3 -translate-x-1/2 -translate-y-1/2 rounded-full bg-[#00d4ff]" />
      </div>
    </div>
  );
}
