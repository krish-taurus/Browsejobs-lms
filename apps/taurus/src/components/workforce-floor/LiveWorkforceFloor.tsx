"use client";

/**
 * The dashboard's workforce floor: the organisation's real AI Employees on the
 * 3D floor, refreshed every few seconds (lib/workforce-floor/live.ts).
 */

import { useMemo } from "react";
import { WorkforceFloor } from "@/components/workforce-floor/WorkforceFloor";
import { workspaceFloorSource } from "@/lib/workforce-floor/live";

export function LiveWorkforceFloor({ organizationName }: { organizationName: string }) {
  const source = useMemo(() => workspaceFloorSource(), []);

  return (
    <div className="relative h-[calc(100svh-12rem)] min-h-[560px] overflow-hidden rounded-2xl border border-taurus-line bg-black">
      <WorkforceFloor source={source} mode="ops" variant="full" ask={false} title="WORKFORCE FLOOR" subtitle={organizationName} />
    </div>
  );
}
