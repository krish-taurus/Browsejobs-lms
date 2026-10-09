"use client";

import { useState } from "react";
import { TaurusDemo } from "./TaurusDemo";

const TABS = [
  { key: "story", label: "One hire, start to finish" },
  { key: "recruitment", label: "The live hiring floor" },
] as const;

/** The /taurusai/recruitment demo: the scripted WhatsApp story, or the free-running floor. */
export function HiringDemo() {
  const [tab, setTab] = useState<(typeof TABS)[number]["key"]>("story");
  return (
    <div className="flex h-full flex-col">
      <div className="mx-auto mb-3 flex w-fit rounded-full border border-line bg-white p-1" role="tablist" aria-label="Demo">
        {TABS.map((t) => (
          <button
            key={t.key}
            type="button"
            role="tab"
            aria-selected={tab === t.key}
            onClick={() => setTab(t.key)}
            className={`rounded-full px-4 py-2 text-sm font-semibold ${tab === t.key ? "bg-ink text-white" : "text-ink2 hover:text-ink"}`}
          >
            {t.label}
          </button>
        ))}
      </div>
      <div className="min-h-0 flex-1 overflow-hidden rounded-[22px] border border-line shadow-[0_30px_80px_rgba(10,18,32,0.25)]">
        <TaurusDemo key={tab} mode={tab} />
      </div>
    </div>
  );
}
