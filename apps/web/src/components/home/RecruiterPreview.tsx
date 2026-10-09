"use client";

import dynamic from "next/dynamic";

const HiringFloor = dynamic(() => import("@/components/recruiter/HiringFloor").then((mod) => mod.HiringFloor), {
  ssr: false,
  loading: () => (
    <div className="min-h-[720px] rounded-[22px] border border-white/10 bg-white/5 p-6" aria-busy="true">
      <p className="kicker text-trust">Demo data</p>
      <p className="mt-3 text-sm text-muted">Loading the hiring floor…</p>
    </div>
  ),
});

/** Homepage embed. Loaded after first paint so the hero stays the LCP. */
export function RecruiterPreview() {
  return <HiringFloor variant="embed" initialElapsedMs={20_000} />;
}
