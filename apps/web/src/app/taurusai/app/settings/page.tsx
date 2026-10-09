"use client";

import { BrainSettings } from "@/components/taurus/BrainSettings";
import { usePortal } from "@/components/taurus/PortalShell";
import { workspaceBase } from "@/lib/taurus-floor/live";

/** A client workspace's own keys, voice and agent token. Visible to its owners and admins only. */
export default function TaurusPortalSettings() {
  const { ws } = usePortal();
  if (ws.role === "viewer") {
    return <p className="mx-auto max-w-3xl rounded-[10px] bg-warn/10 px-3 py-2 text-sm text-warn">Only the workspace owner or an admin can manage keys.</p>;
  }
  return (
    <div className="py-4">
      <BrainSettings key={ws.id} base={workspaceBase(ws.id)} kicker={ws.name} />
    </div>
  );
}
