"use client";

/**
 * Landing v3 — the workforce floor (Sprint 058).
 *
 * The same 3D floor the dashboard runs on a customer's real AI Employees,
 * here on a made-up business running the five crews from the use cases.
 * Visitors can switch the three looks (Obsidian, Titanium, Noir), approve
 * what a robot is waiting on, and ask Taurus what is happening. Everything on
 * it is sample data and the floor labels it so.
 *
 * The 3D floor only mounts once the section is close to the viewport, so the
 * hero never waits on WebGL.
 */

import { useEffect, useMemo, useRef, useState } from "react";
import { Reveal, SectionHeading } from "@/components/landing/v3/motion";
import { WorkforceFloor } from "@/components/workforce-floor/WorkforceFloor";
import { workforceDemo } from "@/lib/workforce-floor/demo";

export const FLOOR_LEGEND = [
  { label: "Working", tone: "bg-sky-400" },
  { label: "Planning", tone: "bg-violet-400" },
  { label: "Waiting for you", tone: "bg-amber-400" },
  { label: "Error", tone: "bg-red-500" },
  { label: "Idle", tone: "bg-neutral-400" },
];

export function WorkforceFloorSection() {
  const hostRef = useRef<HTMLDivElement | null>(null);
  const [near, setNear] = useState(false);
  const source = useMemo(() => (near ? workforceDemo() : null), [near]);

  useEffect(() => {
    const el = hostRef.current;
    if (!el || near) return;
    if (typeof IntersectionObserver === "undefined") {
      setNear(true);
      return;
    }
    const io = new IntersectionObserver((entries) => {
      if (entries.some((e) => e.isIntersecting)) setNear(true);
    }, { rootMargin: "600px 0px" });
    io.observe(el);
    return () => io.disconnect();
  }, [near]);

  return (
    <section id="floor" className="relative scroll-mt-24 py-28 sm:py-36">
      <div className="mx-auto max-w-6xl px-5 sm:px-8">
        <SectionHeading
          eyebrow="The workforce floor"
          title="Watch your [AI Employees] work, live."
          description="Every AI Employee is a robot at a desk in its team's zone. Its ring shows what it is doing, finished work flies to the core, and anything waiting for you appears over the robot that asked. Switch the look, approve a request, or ask Taurus what's happening."
        />
        <Reveal>
          <ul className="mt-8 flex flex-wrap justify-center gap-x-5 gap-y-2 text-[12.5px] text-neutral-500">
            {FLOOR_LEGEND.map((l) => (
              <li key={l.label} className="flex items-center gap-2">
                <span aria-hidden className={`h-2 w-2 rounded-full ${l.tone}`} />
                {l.label}
              </li>
            ))}
          </ul>
        </Reveal>
      </div>

      <div className="mx-auto mt-10 max-w-[1400px] px-3 sm:px-6">
        <div
          ref={hostRef}
          className="relative h-[78svh] min-h-[560px] overflow-hidden rounded-[1.75rem] border border-neutral-200 bg-black shadow-[0_40px_100px_-40px_rgba(0,0,0,0.5)] sm:min-h-[620px]"
        >
          {source ? (
            <WorkforceFloor
              source={source}
              mode="ops"
              variant="hero"
              title="WORKFORCE FLOOR"
              subtitle="A sample business on Taurus"
              askPlaceholder="Ask Taurus: what needs me right now?"
            />
          ) : (
            <div className="grid h-full place-items-center text-[13px] text-neutral-500">Loading the floor…</div>
          )}
        </div>
        <p className="mt-4 text-center text-[12px] text-neutral-500">
          Sample data. In your workspace the floor shows your own AI Employees, workflow runs and approvals.
        </p>
      </div>
    </section>
  );
}
