"use client";

import Link from "next/link";
import { useEffect, useMemo, useState } from "react";
import { TaurusConsole } from "@/components/taurus/TaurusConsole";
import { apiJson, ApiError } from "@/lib/api";
import { listMyWorkspaces, workspaceBase, workspaceFloorSource, type TaurusWorkspace } from "@/lib/taurus-floor/live";

/**
 * The owner's live Taurus console. On first visit it makes sure the owner's
 * own workspace ("Taurus HQ") exists, then shows the floor of whichever
 * workspace the owner is a member of — never a client's, unless they were
 * invited into it.
 */
export default function TaurusConsolePage() {
  const [workspaces, setWorkspaces] = useState<TaurusWorkspace[] | null>(null);
  const [wsId, setWsId] = useState<number | null>(null);
  const [floor, setFloor] = useState<"ops" | "recruitment">("ops");
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;
    apiJson("/api/v1/admin/taurus/hq", { method: "POST", body: "{}" })
      .then(() => listMyWorkspaces())
      .then((list) => {
        if (cancelled) return;
        setWorkspaces(list);
        setWsId((list.find((w) => w.is_owner) ?? list[0])?.id ?? null);
      })
      .catch((e) => {
        if (!cancelled) setError(e instanceof ApiError && e.status === 403 ? "Taurus is available to the platform owner only." : "Couldn't load your Taurus workspaces.");
      });
    return () => {
      cancelled = true;
    };
  }, []);

  const source = useMemo(() => (wsId ? workspaceFloorSource(workspaceBase(wsId), floor) : null), [wsId, floor]);
  const ws = workspaces?.find((w) => w.id === wsId);

  return (
    <div className="-mx-1 md:-mx-3">
      <div className="mb-3 flex flex-wrap items-end justify-between gap-3">
        <div>
          <p className="kicker text-trust">Taurus AI</p>
          <h1 className="display mt-1 text-2xl text-ink">Command centre</h1>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          {workspaces && workspaces.length > 1 && (
            <select
              id="taurus-ws"
              value={wsId ?? ""}
              onChange={(e) => setWsId(Number(e.target.value))}
              className="rounded-full border border-line bg-white px-4 py-2 text-sm font-semibold text-ink"
              aria-label="Workspace"
            >
              {workspaces.map((w) => (
                <option key={w.id} value={w.id}>
                  {w.name}
                  {w.is_owner ? " (yours)" : ""}
                </option>
              ))}
            </select>
          )}
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
          <Link href={`/admin/taurus/brain${wsId ? `?ws=${wsId}` : ""}`} className="rounded-full border border-line bg-white px-4 py-2 text-sm font-semibold text-ink hover:border-trust">
            Brain &amp; voice
          </Link>
        </div>
      </div>
      {error ? (
        <p className="rounded-[10px] bg-warn/10 px-3 py-2 text-sm text-warn">{error}</p>
      ) : !source ? (
        <div className="shimmer h-[calc(100vh-11.5rem)] min-h-[620px] rounded-[22px]" />
      ) : (
        <div className="h-[calc(100vh-11.5rem)] min-h-[620px] overflow-hidden rounded-[22px] border border-line">
          <TaurusConsole
            key={`${wsId}-${floor}`}
            source={source}
            mode={floor}
            variant="full"
            title="TAURUS"
            subtitle={`${ws?.name ?? "Workspace"} · ${floor === "ops" ? "agent floor" : "recruitment bots"} · live`}
          />
        </div>
      )}
      <p className="mono mt-2 text-[11px] text-muted">
        Live data from this workspace&apos;s agents only. Approvals are recorded with your name. Spend shows only what each source reports.
      </p>
    </div>
  );
}
