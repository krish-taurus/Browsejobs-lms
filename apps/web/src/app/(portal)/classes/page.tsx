"use client";

import Link from "next/link";
import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { ApiError, apiJson } from "@/lib/api";
import { EmptyState } from "@/components/ui/EmptyState";

type LiveClass = {
  id: number;
  title: string;
  batch: string | null;
  scheduled_start: string | null;
  scheduled_end: string | null;
  status: string;
  has_recording: boolean;
  recording_id: number | null;
};

function fmt(iso: string | null): string {
  if (!iso) return "—";
  return new Date(iso).toLocaleString(undefined, {
    weekday: "short", day: "numeric", month: "short", hour: "2-digit", minute: "2-digit",
  });
}

export default function ClassesPage() {
  const [joining, setJoining] = useState<number | null>(null);
  const [error, setError] = useState<string | null>(null);

  const { data, isLoading } = useQuery({
    queryKey: ["me", "classes"],
    queryFn: () => apiJson<{ data: LiveClass[] }>("/api/v1/me/classes"),
  });

  const classes = data?.data ?? [];
  const upcoming = classes.filter((c) => c.status === "scheduled" || c.status === "live");
  const past = classes.filter((c) => c.status === "ended");

  async function join(c: LiveClass) {
    setError(null);
    setJoining(c.id);
    try {
      const r = await apiJson<{ data: { join_url: string } }>(`/api/v1/me/classes/${c.id}/join`, { method: "POST" });
      window.open(r.data.join_url, "_blank", "noopener");
    } catch (err) {
      setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Could not join the class.");
    } finally {
      setJoining(null);
    }
  }

  return (
    <div className="mx-auto max-w-3xl">
      <p className="kicker text-trust">My Classes</p>
      <h1 className="display mt-2 text-3xl text-ink">Live classes</h1>

      {error && <p className="mt-4 rounded-[10px] bg-warn/10 px-3 py-2 text-sm text-warn">{error}</p>}

      {isLoading ? (
        <div className="mt-8 space-y-3">{Array.from({ length: 3 }).map((_, i) => <div key={i} className="shimmer h-16 rounded-[14px]" />)}</div>
      ) : classes.length === 0 ? (
        <div className="mt-8">
          <EmptyState title="No classes scheduled yet" body="Once you're enrolled in a batch, your upcoming live classes appear here with one-tap join links." />
        </div>
      ) : (
        <div className="mt-8 space-y-8">
          <section>
            <p className="mono text-[11px] uppercase tracking-widest text-muted">Upcoming</p>
            {upcoming.length === 0 ? (
              <p className="mt-2 text-sm text-muted">No upcoming classes right now.</p>
            ) : (
              <div className="mt-3 divide-y divide-line rounded-[14px] border border-line bg-white">
                {upcoming.map((c) => (
                  <div key={c.id} className="flex flex-wrap items-center gap-3 px-5 py-4">
                    <span className="min-w-40 flex-1">
                      <span className="block font-semibold text-ink">{c.title}</span>
                      <span className="mono block text-xs text-muted">{fmt(c.scheduled_start)} · {c.batch}</span>
                    </span>
                    <button
                      onClick={() => join(c)}
                      disabled={joining === c.id}
                      className="rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white hover:bg-deep disabled:opacity-50"
                    >
                      {joining === c.id ? "Opening…" : "Join"}
                    </button>
                  </div>
                ))}
              </div>
            )}
          </section>

          {past.length > 0 && (
            <section>
              <p className="mono text-[11px] uppercase tracking-widest text-muted">Past classes</p>
              <div className="mt-3 divide-y divide-line rounded-[14px] border border-line bg-white">
                {past.map((c) => (
                  <div key={c.id} className="flex flex-wrap items-center gap-3 px-5 py-4">
                    <span className="min-w-40 flex-1">
                      <span className="block font-semibold text-ink">{c.title}</span>
                      <span className="mono block text-xs text-muted">{fmt(c.scheduled_start)} · {c.batch}</span>
                    </span>
                    {c.has_recording ? (
                      <Link href="/recordings" className="text-sm font-semibold text-trust hover:underline">Recording ready →</Link>
                    ) : (
                      <span className="text-xs text-muted">No recording</span>
                    )}
                  </div>
                ))}
              </div>
            </section>
          )}
        </div>
      )}
    </div>
  );
}
