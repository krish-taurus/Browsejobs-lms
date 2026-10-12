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
      <div className="tx-head">
        <div>
          <p className="tx-eyebrow">{ws.name}</p>
          <h1 className="tx-title">Command centre</h1>
        </div>
        <div className="tx-seg" role="group" aria-label="Floor">
          {(["ops", "recruitment"] as const).map((f) => (
            <button key={f} type="button" onClick={() => setFloor(f)} aria-pressed={floor === f}>
              {f === "ops" ? "Agent floor" : "Recruitment floor"}
            </button>
          ))}
        </div>
      </div>
      <div className="tx-floor tx-floor--portal">
        <TaurusConsole key={`${ws.id}-${floor}`} source={source} mode={floor} variant="full" title="TAURUS" subtitle={`${ws.name} · live`} />
      </div>
      <p className="tx-fine">Live data from your workspace&apos;s agents only. Spend shows only what each source reports.</p>
    </div>
  );
}
