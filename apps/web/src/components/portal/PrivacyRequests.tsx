"use client";

import { useCallback, useEffect, useState } from "react";
import { ApiError, apiJson } from "@/lib/api";

type Req = {
  id: number;
  type: "access" | "deletion";
  status: "pending" | "completed" | "rejected";
  has_export: boolean;
  requested_at: string | null;
};

export function PrivacyRequests() {
  const [requests, setRequests] = useState<Req[] | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState<string | null>(null);

  const load = useCallback(() => {
    apiJson<{ data: Req[] }>("/api/v1/me/data-requests").then((r) => setRequests(r.data)).catch(() => setRequests([]));
  }, []);
  useEffect(() => { load(); }, [load]);

  async function raise(type: "access" | "deletion") {
    if (type === "deletion" && !window.confirm("Request deletion of your personal data? Your account will be anonymised once a grievance officer processes it.")) return;
    setBusy(type);
    setError(null);
    try {
      await apiJson("/api/v1/me/data-requests", { method: "POST", body: JSON.stringify({ type }) });
      load();
    } catch (err) {
      setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Could not raise the request.");
    } finally {
      setBusy(null);
    }
  }

  async function download(id: number) {
    try {
      const r = await apiJson<{ data: { export: unknown } }>(`/api/v1/me/data-requests/${id}`);
      const blob = new Blob([JSON.stringify(r.data.export, null, 2)], { type: "application/json" });
      const url = URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = `my-data-${id}.json`;
      a.click();
      URL.revokeObjectURL(url);
    } catch {
      setError("Could not download the export.");
    }
  }

  const hasPending = (type: string) => requests?.some((r) => r.type === type && r.status === "pending");

  return (
    <div className="mt-6 rounded-[22px] border border-line bg-white p-6">
      <p className="kicker text-trust">Privacy & your data</p>
      <h2 className="display mt-1 text-lg text-ink">Your data rights (DPDP)</h2>
      <p className="mt-1 text-sm text-muted">
        Request a copy of the personal data we hold about you, or ask us to erase it. A grievance officer
        reviews every request.
      </p>

      {error && <p className="mt-3 text-sm text-warn">{error}</p>}

      <div className="mt-4 flex flex-wrap gap-2">
        <button onClick={() => raise("access")} disabled={busy === "access" || hasPending("access")}
          className="rounded-full border border-line bg-white px-5 py-2 text-sm font-semibold text-ink hover:border-trust disabled:opacity-50">
          {hasPending("access") ? "Access requested" : "Request my data"}
        </button>
        <button onClick={() => raise("deletion")} disabled={busy === "deletion" || hasPending("deletion")}
          className="rounded-full border border-line bg-white px-5 py-2 text-sm font-semibold text-warn hover:border-warn disabled:opacity-50">
          {hasPending("deletion") ? "Deletion requested" : "Request deletion"}
        </button>
      </div>

      {requests && requests.length > 0 && (
        <div className="mt-4 space-y-1.5">
          {requests.map((r) => (
            <div key={r.id} className="flex items-center justify-between text-xs">
              <span className="mono uppercase tracking-widest text-muted">
                {r.type} · {r.status}
              </span>
              {r.type === "access" && r.status === "completed" && r.has_export && (
                <button onClick={() => download(r.id)} className="font-semibold text-trust hover:underline">Download</button>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
