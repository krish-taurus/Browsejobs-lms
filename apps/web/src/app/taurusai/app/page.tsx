"use client";

import { useMemo, useState } from "react";
import { usePortal } from "@/components/taurus/PortalShell";
import { TaurusConsole } from "@/components/taurus/TaurusConsole";
import { workspaceBase, workspaceFloorSource } from "@/lib/taurus-floor/live";

/** A client's own Taurus floor: only this workspace's agents, approvals and spend. */
export default function TaurusPortalConsole() {
  const { ws } = usePortal();
  const [floor, setFloor] = useState<"ops" | "recruitment">("ops");
  const source = useMemo(() => workspaceFloorSource(workspaceBase(ws.id), floor), [ws.id, floor]);

  return (
    <div>
      <div className="mb-3 flex flex-wrap items-center justify-between gap-3">
        <h1 className="display text-2xl text-ink">Command centre</h1>
        <div className="flex rounded-full border border-line bg-white p-1" role="group" aria-label="Floor">
          {(["ops", "recruitment"] as const).map((f) => (
            <button
              key={f}
              type="button"
              onClick={() => setFloor(f)}
              aria-pressed={floor === f}
              className={`rounded-full px-4 py-1.5 text-sm font-semibold ${floor === f ? "bg-ink text-white" : "text-ink2 hover:text-ink"}`}
            >
              {f === "ops" ? "Agent floor" : "Recruitment floor"}
            </button>
          ))}
        </div>
      </div>
      <div className="h-[calc(100vh-9.5rem)] min-h-[600px] overflow-hidden rounded-[22px] border border-line">
        <TaurusConsole key={`${ws.id}-${floor}`} source={source} mode={floor} variant="full" title="TAURUS" subtitle={`${ws.name} · live`} />
      </div>
    </div>
  );
}
