"use client";

import Link from "next/link";
import { useEffect, useMemo, useState } from "react";
import "@/components/ap/pages/taurus-ui.css";
import { taurusDisplayFont } from "@/components/ap/pages/taurus-font";
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
    <div className={`tx-ap tx-ap--card ${taurusDisplayFont.variable}`}>
      <div className="tx-head">
        <div>
          <p className="tx-eyebrow">Taurus AI</p>
          <h1 className="tx-title">Command centre</h1>
          <p className="tx-sub">Your agents on one live floor. Approve what&apos;s waiting, or ask Taurus what&apos;s happening.</p>
        </div>
        <div className="tx-tools">
          {workspaces && workspaces.length > 1 && (
            <select id="taurus-ws" value={wsId ?? ""} onChange={(e) => setWsId(Number(e.target.value))} className="tx-select" aria-label="Workspace">
              {workspaces.map((w) => (
                <option key={w.id} value={w.id}>
                  {w.name}
                  {w.is_owner ? " (yours)" : ""}
                </option>
              ))}
            </select>
          )}
          <div className="tx-seg" role="group" aria-label="Floor">
            {(["ops", "recruitment"] as const).map((f) => (
              <button key={f} type="button" onClick={() => setFloor(f)} aria-pressed={floor === f}>
                {f === "ops" ? "Agent floor" : "Recruitment floor"}
              </button>
            ))}
          </div>
          <Link href={`/admin/taurus/brain${wsId ? `?ws=${wsId}` : ""}`} className="tx-btn tx-btn-outline">
            Brain &amp; voice
          </Link>
        </div>
      </div>
      {error ? (
        <p className="tx-note tx-note--warn">{error}</p>
      ) : !source ? (
        <div className="shimmer tx-skel tx-floor--admin" />
      ) : (
        <div className="tx-floor tx-floor--admin">
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
      <p className="tx-fine">
        Live data from this workspace&apos;s agents only. Approvals are recorded with your name. Spend shows only what each source reports.
      </p>
    </div>
  );
}
