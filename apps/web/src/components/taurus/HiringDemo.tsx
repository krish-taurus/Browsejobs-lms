"use client";

import { useState } from "react";
import { TaurusDemo } from "./TaurusDemo";

const TABS = [
  { key: "story", label: "One hire, start to finish" },
  { key: "recruitment", label: "The live hiring floor" },
] as const;

/**
 * The /taurusai/recruitment demo: the scripted WhatsApp story, or the
 * free-running floor. Styled by components/ap/pages/taurus.css (.tx-demo).
 */
export function HiringDemo() {
  const [tab, setTab] = useState<(typeof TABS)[number]["key"]>("story");
  return (
    <div className="tx-demo">
      <div className="tx-seg tx-seg--dark" role="tablist" aria-label="Demo">
        {TABS.map((t) => (
          <button key={t.key} type="button" role="tab" aria-selected={tab === t.key} onClick={() => setTab(t.key)}>
            {t.label}
          </button>
        ))}
      </div>
      <div className="tx-demo-stage">
        <TaurusDemo key={tab} mode={tab} />
      </div>
    </div>
  );
}
