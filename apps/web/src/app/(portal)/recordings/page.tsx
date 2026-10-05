"use client";

import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { ApiError, apiJson } from "@/lib/api";
import { EmptyState } from "@/components/ui/EmptyState";

type Recording = {
  /** null when this class has no recording — the row still renders. */
  id: number | null;
  session_id: number;
  has_recording: boolean;
  title: string;
  duration_seconds: number | null;
  class: string | null;
  recorded_on: string | null;
  batch_number: string | null;
  course_code: string | null;
  course_name: string | null;
};

// Everything runs on IST, same as the class schedule — a student should never
// have to convert a timezone to recognise the class they actually sat in.
const TZ = "Asia/Kolkata";

function part(iso: string, opts: Intl.DateTimeFormatOptions): string {
  return new Intl.DateTimeFormat("en-IN", { timeZone: TZ, ...opts }).format(new Date(iso));
}
const dateLabel = (iso: string) => part(iso, { month: "short", day: "2-digit" });
const weekdayLabel = (iso: string) => part(iso, { weekday: "long" });
const timeLabel = (iso: string) => part(iso, { hour: "numeric", minute: "2-digit", hour12: true });

function fmtDuration(s: number | null): string | null {
  if (!s) return null;
  const m = Math.round(s / 60);
  return m >= 60 ? `${Math.floor(m / 60)}h ${m % 60}m` : `${m} min`;
}

function ClockIcon() {
  return (
    <svg viewBox="0 0 16 16" aria-hidden="true" className="size-3.5 shrink-0" fill="none" stroke="currentColor" strokeWidth="1.5">
      <circle cx="8" cy="8" r="6.25" />
      <path d="M8 4.4V8l2.4 1.5" strokeLinecap="round" />
    </svg>
  );
}

function FilmIcon() {
  return (
    <svg viewBox="0 0 16 16" aria-hidden="true" className="size-3.5 shrink-0" fill="none" stroke="currentColor" strokeWidth="1.5">
      <rect x="1.75" y="3.25" width="8.5" height="9.5" rx="1.75" />
      <path d="M10.25 7l4-2.25v6.5L10.25 9" strokeLinejoin="round" />
    </svg>
  );
}

function PlayIcon() {
  return (
    <svg viewBox="0 0 16 16" aria-hidden="true" className="size-3.5 shrink-0" fill="currentColor">
      <path d="M5 3.4a.6.6 0 0 1 .92-.51l6.3 4.1a.6.6 0 0 1 0 1.02l-6.3 4.1A.6.6 0 0 1 5 12.6V3.4Z" />
    </svg>
  );
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
    if (!r.has_recording || r.id === null) return;

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

      // Zoom cloud recordings still carry a passcode, but the server now bakes it
      // into the link, so the student never sees or types it.
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
    <div className="mx-auto max-w-4xl">
      {player && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-ink/85 p-4 backdrop-blur-sm" onClick={() => setPlayer(null)}>
          <div className="w-full max-w-4xl" onClick={(e) => e.stopPropagation()}>
            <div className="flex items-center justify-between gap-3 pb-3">
              <p className="truncate text-sm font-semibold text-white">{player.title}</p>
              <button
                onClick={() => setPlayer(null)}
                className="shrink-0 rounded-full bg-white/10 px-3.5 py-1.5 text-sm font-semibold text-white transition hover:bg-white/20"
                aria-label="Close player"
              >
                Close
              </button>
            </div>
            <video src={player.url} controls autoPlay playsInline className="aspect-video w-full rounded-[14px] bg-black" />
          </div>
        </div>
      )}

      <p className="kicker text-trust">Recordings</p>
      <h1 className="display mt-2 text-3xl text-ink">Class recordings</h1>
      <p className="mt-2 text-sm text-muted">
        Every class your batch has already held, newest first. Recorded ones play here while your fees are clear.
      </p>

      {error && <p className="mt-4 rounded-[10px] bg-warn/10 px-3 py-2 text-sm text-warn">{error}</p>}
      {notice && <p className="mt-4 rounded-[10px] bg-sky px-3 py-2 text-sm text-deep">{notice}</p>}

      {isLoading ? (
        <div className="mt-8 space-y-3">{Array.from({ length: 3 }).map((_, i) => <div key={i} className="shimmer h-[92px] rounded-[14px]" />)}</div>
      ) : recordings.length === 0 ? (
        <div className="mt-8">
          <EmptyState title="Your classes will appear here" body="Once your batch has held its first live class it shows up here, with the recording when there is one." />
        </div>
      ) : (
        <div className="mt-8 space-y-9">
          {groups.map((g) => (
            <section key={g.key}>
              <div className="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                {g.number && <h2 className="display text-lg text-ink">{g.number}</h2>}
                {g.course && (
                  <span className="mono rounded-full bg-sky px-2.5 py-0.5 text-[10px] uppercase tracking-widest text-deep">{g.course}</span>
                )}
                <span className="mono text-xs text-muted">
                  {g.items.length} class{g.items.length === 1 ? "" : "es"} ·{" "}
                  {g.items.filter((i) => i.has_recording).length} recorded
                </span>
              </div>

              <div className="mt-3 space-y-3">
                {g.items.map((r) => {
                  const duration = fmtDuration(r.duration_seconds);

                  return (
                    <article
                      key={r.session_id}
                      className="flex flex-col overflow-hidden rounded-[14px] border border-line bg-surface transition hover:border-trust/50 hover:shadow-soft sm:flex-row"
                    >
                      {/* Date rail — the same at-a-glance anchor the class schedule uses. */}
                      <div className="flex shrink-0 items-center gap-2 border-b border-line bg-paper px-5 py-3 sm:w-[112px] sm:flex-col sm:items-start sm:justify-center sm:gap-1 sm:border-b-0 sm:py-6">
                        <span className="display text-lg leading-none text-ink">
                          {r.recorded_on ? dateLabel(r.recorded_on) : "—"}
                        </span>
                        <span className="mono text-[11px] leading-none text-muted">
                          {r.recorded_on ? weekdayLabel(r.recorded_on) : "Date unknown"}
                        </span>
                      </div>

                      <div
                        className={`flex flex-1 flex-wrap items-center gap-x-4 gap-y-3 border-l-[3px] px-5 py-4 ${
                          r.has_recording ? "border-trust" : "border-line"
                        }`}
                      >
                        <div className="min-w-48 flex-1">
                          <h3 className="font-semibold text-ink">{r.class ?? r.title}</h3>
                          <div className="mono mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted">
                            {r.recorded_on && (
                              <span className="inline-flex items-center gap-1.5">
                                <ClockIcon />{timeLabel(r.recorded_on)} IST
                              </span>
                            )}
                            {duration && (
                              <span className="inline-flex items-center gap-1.5">
                                <FilmIcon />{duration}
                              </span>
                            )}
                          </div>
                          {r.has_recording ? (
                            <span className="mt-2.5 inline-flex rounded-full bg-verify-bg px-2.5 py-1 text-[10px] font-semibold uppercase tracking-widest text-verify">
                              Recording ready
                            </span>
                          ) : (
                            <span className="mt-2.5 inline-flex rounded-full bg-paper px-2.5 py-1 text-[10px] font-semibold uppercase tracking-widest text-muted">
                              No recording
                            </span>
                          )}
                        </div>

                        {r.has_recording ? (
                          <button
                            onClick={() => open(r)}
                            disabled={opening === r.id}
                            className="inline-flex shrink-0 items-center gap-2 rounded-full bg-trust px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-deep disabled:opacity-50"
                          >
                            <PlayIcon />
                            {opening === r.id ? "Opening…" : "Play recording"}
                          </button>
                        ) : (
                          <p className="shrink-0 text-xs text-muted">This class was not recorded.</p>
                        )}
                      </div>
                    </article>
                  );
                })}
              </div>
            </section>
          ))}
        </div>
      )}
    </div>
  );
}
