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
  batch_number: string | null;
  course_code: string | null;
  course_name: string | null;
};

// Date + time in IST, matching the class schedule and the admin recordings view.
function fmtDate(iso: string | null): string {
  if (!iso) return "";
  return new Date(iso).toLocaleString("en-IN", {
    timeZone: "Asia/Kolkata", day: "numeric", month: "short", year: "numeric", hour: "numeric", minute: "2-digit",
  });
}

function fmtDuration(s: number | null): string {
  if (!s) return "";
  const m = Math.round(s / 60);
  return m >= 60 ? `${Math.floor(m / 60)}h ${m % 60}m` : `${m}m`;
}

export default function RecordingsPage() {
  const [opening, setOpening] = useState<number | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [player, setPlayer] = useState<{ title: string; url: string } | null>(null);

  const { data, isLoading } = useQuery({
    queryKey: ["me", "recordings"],
    queryFn: () => apiJson<{ data: Recording[] }>("/api/v1/me/recordings"),
  });

  async function open(r: Recording) {
    setError(null);
    setNotice(null);
    setOpening(r.id);
    try {
      const res = await apiJson<{ data: { watch_url: string | null; passcode: string | null; embedded?: boolean } }>(`/api/v1/me/recordings/${r.id}/download`);
      if (!res.data.watch_url) {
        setError("This recording is still being prepared — check back shortly.");
        return;
      }

      // Self-hosted copies play right here in the portal.
      if (res.data.embedded || /\.mp4($|\?)/.test(res.data.watch_url)) {
        setPlayer({ title: r.class ?? r.title, url: res.data.watch_url });
        return;
      }

      // Zoom cloud recordings can be passcode-protected — surface it so the student can paste it.
      if (res.data.passcode) {
        await navigator.clipboard?.writeText(res.data.passcode).catch(() => {});
        setNotice(`Passcode ${res.data.passcode} (copied to clipboard) — paste it on the Zoom page if it asks.`);
      }
      window.open(res.data.watch_url, "_blank", "noopener");
    } catch (err) {
      setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Could not open the recording.");
    } finally {
      setOpening(null);
    }
  }

  const recordings = data?.data ?? [];

  // Group by batch (a student is usually in one, but bootcamp + paid can overlap).
  const groups: { key: string; number: string | null; course: string | null; items: Recording[] }[] = [];
  for (const r of recordings) {
    const key = r.batch_number ?? "—";
    let g = groups.find((x) => x.key === key);
    if (!g) { g = { key, number: r.batch_number, course: r.course_code, items: [] }; groups.push(g); }
    g.items.push(r);
  }

  return (
    <div className="mx-auto max-w-3xl">
      {player && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4" onClick={() => setPlayer(null)}>
          <div className="w-full max-w-4xl" onClick={(e) => e.stopPropagation()}>
            <div className="flex items-center justify-between gap-3 pb-2">
              <p className="truncate text-sm font-semibold text-white">{player.title}</p>
              <button
                onClick={() => setPlayer(null)}
                className="rounded-full bg-white/10 px-3 py-1 text-sm font-semibold text-white hover:bg-white/20"
                aria-label="Close player"
              >
                ✕ Close
              </button>
            </div>
            <video src={player.url} controls autoPlay playsInline className="aspect-video w-full rounded-xl bg-black" />
          </div>
        </div>
      )}

      <p className="kicker text-trust">Recordings</p>
      <h1 className="display mt-2 text-3xl text-ink">Class recordings</h1>
      <p className="mt-2 text-sm text-muted">Every class you attend is recorded here — available while your fees are clear.</p>

      {error && <p className="mt-4 rounded-[10px] bg-warn/10 px-3 py-2 text-sm text-warn">{error}</p>}
      {notice && <p className="mt-4 rounded-[10px] bg-sky px-3 py-2 text-sm text-deep">{notice}</p>}

      {isLoading ? (
        <div className="mt-8 space-y-3">{Array.from({ length: 3 }).map((_, i) => <div key={i} className="shimmer h-16 rounded-[14px]" />)}</div>
      ) : recordings.length === 0 ? (
        <div className="mt-8">
          <EmptyState title="Your recordings will appear here" body="After your first live class, its recording lands here — yours to revisit any time." />
        </div>
      ) : (
        <div className="mt-8 space-y-8">
          {groups.map((g) => (
            <section key={g.key}>
              {g.number && (
                <div className="flex items-baseline gap-2">
                  <h2 className="display text-lg text-ink">{g.number}</h2>
                  {g.course && <span className="mono text-xs uppercase tracking-widest text-muted">{g.course}</span>}
                </div>
              )}
              <div className="mt-3 divide-y divide-line rounded-[14px] border border-line bg-white">
                {g.items.map((r) => (
                  <div key={r.id} className="flex flex-wrap items-center gap-3 px-5 py-4">
                    <span className="min-w-40 flex-1">
                      <span className="block font-semibold text-ink">{r.class ?? r.title}</span>
                      <span className="mono block text-xs text-muted">
                        {fmtDate(r.recorded_on)}{r.duration_seconds ? ` · ${fmtDuration(r.duration_seconds)}` : ""} IST
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
            </section>
          ))}
        </div>
      )}
    </div>
  );
}
