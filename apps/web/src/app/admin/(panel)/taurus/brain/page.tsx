"use client";

import { useEffect, useState } from "react";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
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

  if (error) return <p className="mx-auto max-w-3xl rounded-[10px] bg-warn/10 px-3 py-2 text-sm text-warn">{error}</p>;
  if (!ws) return <div className="shimmer mx-auto h-40 max-w-3xl rounded-[14px]" />;

  return (
    <QueryClientProvider client={client}>
      <BrainSettings
        base={workspaceBase(ws.id)}
        kicker={`Taurus AI · ${ws.name}`}
        heading="Brain & voice"
        intro="The keys behind your live agents. Only you can see this workspace; clients never use these keys. Keys are encrypted on the server and only the last four characters are shown."
      />
      <div className="mx-auto mt-14 max-w-3xl border-t border-line pt-10">
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
