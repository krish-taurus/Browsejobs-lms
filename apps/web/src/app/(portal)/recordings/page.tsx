"use client";

import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { ApiError, apiJson } from "@/lib/api";
import { EmptyState } from "@/components/ui/EmptyState";

type Recording = {
  id: number;
  title: string;
  duration_seconds: number | null;
  class: string | null;
  recorded_on: string | null;
};

function fmtDate(iso: string | null): string {
  if (!iso) return "";
  return new Date(iso).toLocaleDateString(undefined, { day: "numeric", month: "short", year: "numeric" });
}

function fmtDuration(s: number | null): string {
  if (!s) return "";
  const m = Math.round(s / 60);
  return m >= 60 ? `${Math.floor(m / 60)}h ${m % 60}m` : `${m}m`;
}

export default function RecordingsPage() {
  const [opening, setOpening] = useState<number | null>(null);
  const [error, setError] = useState<string | null>(null);

  const { data, isLoading } = useQuery({
    queryKey: ["me", "recordings"],
    queryFn: () => apiJson<{ data: Recording[] }>("/api/v1/me/recordings"),
  });

  async function open(r: Recording) {
    setError(null);
    setOpening(r.id);
    try {
      const res = await apiJson<{ data: { download_url: string | null } }>(`/api/v1/me/recordings/${r.id}/download`);
      if (res.data.download_url) {
        window.open(res.data.download_url, "_blank", "noopener");
      } else {
        setError("This recording is still being prepared — check back shortly.");
      }
    } catch (err) {
      setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Could not open the recording.");
    } finally {
      setOpening(null);
    }
  }

  const recordings = data?.data ?? [];

  return (
    <div className="mx-auto max-w-3xl">
      <p className="kicker text-trust">Recordings</p>
      <h1 className="display mt-2 text-3xl text-ink">Class recordings</h1>
      <p className="mt-2 text-sm text-muted">Every class you attend is recorded here — available while your fees are clear.</p>

      {error && <p className="mt-4 rounded-[10px] bg-warn/10 px-3 py-2 text-sm text-warn">{error}</p>}

      {isLoading ? (
        <div className="mt-8 space-y-3">{Array.from({ length: 3 }).map((_, i) => <div key={i} className="shimmer h-16 rounded-[14px]" />)}</div>
      ) : recordings.length === 0 ? (
        <div className="mt-8">
          <EmptyState title="Your recordings will appear here" body="After your first live class, its recording lands here — yours to revisit any time." />
        </div>
      ) : (
        <div className="mt-8 divide-y divide-line rounded-[14px] border border-line bg-white">
          {recordings.map((r) => (
            <div key={r.id} className="flex flex-wrap items-center gap-3 px-5 py-4">
              <span className="min-w-40 flex-1">
                <span className="block font-semibold text-ink">{r.class ?? r.title}</span>
                <span className="mono block text-xs text-muted">
                  {fmtDate(r.recorded_on)}{r.duration_seconds ? ` · ${fmtDuration(r.duration_seconds)}` : ""}
                </span>
              </span>
              <button
                onClick={() => open(r)}
                disabled={opening === r.id}
                className="rounded-full border border-line px-5 py-2 text-sm font-semibold text-ink hover:border-trust disabled:opacity-50"
              >
                {opening === r.id ? "Opening…" : "Watch"}
              </button>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
