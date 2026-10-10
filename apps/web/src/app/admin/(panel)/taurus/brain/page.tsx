"use client";

import { useEffect, useState } from "react";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import "@/components/ap/pages/taurus-ui.css";
import { taurusDisplayFont } from "@/components/ap/pages/taurus-font";
import { BrainSettings } from "@/components/taurus/BrainSettings";
import { ApiError, apiJson } from "@/lib/api";
import { listMyWorkspaces, workspaceBase, type TaurusWorkspace } from "@/lib/taurus-floor/live";

/**
 * Owner-only Taurus keys:
 *  1. the owner's own workspace (Taurus HQ, or ?ws= another workspace the
 *     owner belongs to) — the keys behind the owner's live agents;
 *  2. the BrowseJobs recruitment brain the employer hiring floor uses.
 * Clients manage their own keys in /taurusai/app/settings and never see these.
 */
export default function TaurusBrainPage() {
  const [ws, setWs] = useState<TaurusWorkspace | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [client] = useState(() => new QueryClient({ defaultOptions: { queries: { retry: 1, refetchOnWindowFocus: false } } }));

  useEffect(() => {
    const wanted = Number(new URLSearchParams(window.location.search).get("ws")) || null;
    apiJson("/api/v1/admin/taurus/hq", { method: "POST", body: "{}" })
      .then(() => listMyWorkspaces())
      .then((list) => setWs(list.find((w) => w.id === wanted) ?? list.find((w) => w.is_owner) ?? list[0] ?? null))
      .catch((e) => setError(e instanceof ApiError && e.status === 403 ? "Taurus keys are available to the platform owner only." : "Couldn't load your Taurus workspace."));
  }, []);

  const root = `tx-ap tx-ap--card ${taurusDisplayFont.variable}`;
  if (error)
    return (
      <div className={root}>
        <p className="tx-note tx-note--warn tx-narrow">{error}</p>
      </div>
    );
  if (!ws)
    return (
      <div className={root}>
        <div className="shimmer tx-skel tx-narrow" style={{ height: 160 }} />
      </div>
    );

  return (
    <QueryClientProvider client={client}>
      <div className={root}>
        <BrainSettings
          base={workspaceBase(ws.id)}
          kicker={`Taurus AI · ${ws.name}`}
          heading="Brain & voice"
          intro="The keys behind your live agents. Only you can see this workspace; clients never use these keys. Keys are encrypted on the server and only the last four characters are shown."
        />
        <hr className="tx-divider tx-narrow" />
        <BrainSettings
          base="/api/v1/admin/taurus/platform"
          variant="platform"
          kicker="BrowseJobs recruitment"
          heading="Hiring floor brain"
          intro="The model and voice that answer employers on their Hiring floor. “Platform default” uses the provider set in Settings → AI."
        />
      </div>
    </QueryClientProvider>
  );
}
