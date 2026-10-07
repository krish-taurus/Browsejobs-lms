"use client";

import { useSearchParams } from "next/navigation";
import { HiringFloor } from "./HiringFloor";

/** Public demo. `?at=52` freezes the story on the showcase frame. */
export function MissionControlFloor() {
  const params = useSearchParams();
  const raw = params.get("at");
  const frozenAtMs = raw !== null && raw !== "" && Number.isFinite(Number(raw)) ? Number(raw) * 1000 : null;
  return <HiringFloor variant="full" frozenAtMs={frozenAtMs} />;
}
