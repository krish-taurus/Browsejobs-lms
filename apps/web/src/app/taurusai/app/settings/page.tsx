"use client";

import { BrainSettings } from "@/components/taurus/BrainSettings";
import { usePortal } from "@/components/taurus/PortalShell";
import { workspaceBase } from "@/lib/taurus-floor/live";

/** A client workspace's own keys, voice and agent token. Visible to its owners and admins only. */
export default function TaurusPortalSettings() {
  const { ws } = usePortal();
  if (ws.role === "viewer") {
    return <p className="tx-note tx-note--warn tx-narrow">Only the workspace owner or an admin can manage keys.</p>;
  }
  return <BrainSettings key={ws.id} base={workspaceBase(ws.id)} kicker={ws.name} />;
}
