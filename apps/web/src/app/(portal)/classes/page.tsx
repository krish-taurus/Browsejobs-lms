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
  topic: string | null;
  scheduled_start: string | null;
  scheduled_end: string | null;
  status: string;
  has_recording: boolean;
  recording_id: number | null;
};

// The platform runs on IST — show every class time in Asia/Kolkata so a student
// never has to convert, matching the trainer's "My teaching" board.
const TZ = "Asia/Kolkata";
const WEEKDAY_ORDER = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"];

function dayKey(iso: string): string {
  return new Intl.DateTimeFormat("en-CA", { timeZone: TZ, year: "numeric", month: "2-digit", day: "numeric" }).format(new Date(iso));
}
function weekday(iso: string): string {
  return new Intl.DateTimeFormat("en-US", { timeZone: TZ, weekday: "short" }).format(new Date(iso));
}
function timeLabel(iso: string): string {
  return new Intl.DateTimeFormat("en-IN", { timeZone: TZ, hour: "numeric", minute: "2-digit", hour12: true }).format(new Date(iso));
}
function dayHeading(iso: string): string {
  const key = dayKey(iso);
  const now = new Date();
  const todayKey = dayKey(now.toISOString());
  const tomorrowKey = dayKey(new Date(now.getTime() + 86_400_000).toISOString());
  if (key === todayKey) return "Today";
  if (key === tomorrowKey) return "Tomorrow";
  return new Intl.DateTimeFormat("en-IN", { timeZone: TZ, weekday: "short", day: "numeric", month: "short" }).format(new Date(iso));
}

/** Distinct weekday + time slots per batch, derived from the upcoming schedule. */
function weeklyRhythm(upcoming: LiveClass[]): { batch: string; slots: { day: string; time: string }[] }[] {
  const byBatch = new Map<string, Map<string, { day: string; time: string }>>();
  for (const c of upcoming) {
    if (!c.scheduled_start) continue;
    const batch = c.batch ?? "Your batch";
    const day = weekday(c.scheduled_start);
    const time = timeLabel(c.scheduled_start);
    const slotKey = `${day} ${time}`;
    if (!byBatch.has(batch)) byBatch.set(batch, new Map());
    byBatch.get(batch)!.set(slotKey, { day, time });
  }
  return [...byBatch.entries()].map(([batch, slots]) => ({
    batch,
    slots: [...slots.values()].sort((a, b) =>
      WEEKDAY_ORDER.indexOf(a.day) - WEEKDAY_ORDER.indexOf(b.day) || a.time.localeCompare(b.time)),
  }));
}

export default function ClassesPage() {
  const [joining, setJoining] = useState<number | null>(null);
  const [error, setError] = useState<string | null>(null);

  const { data, isLoading } = useQuery({
    queryKey: ["me", "classes"],
    queryFn: () => apiJson<{ data: LiveClass[] }>("/api/v1/me/classes"),
  });

  const classes = data?.data ?? [];
  const upcoming = classes
    .filter((c) => (c.status === "scheduled" || c.status === "live") && c.scheduled_start)
    .sort((a, b) => (a.scheduled_start! < b.scheduled_start! ? -1 : 1)); // soonest first
  const past = classes.filter((c) => c.status === "ended");
  const rhythm = weeklyRhythm(upcoming);

  // Group the upcoming list under day headings (Today / Tomorrow / date).
  const days: { key: string; heading: string; items: LiveClass[] }[] = [];
  for (const c of upcoming) {
    const key = dayKey(c.scheduled_start!);
    let group = days.find((d) => d.key === key);
    if (!group) { group = { key, heading: dayHeading(c.scheduled_start!), items: [] }; days.push(group); }
    group.items.push(c);
  }

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
      <h1 className="display mt-2 text-3xl text-ink">Your class schedule</h1>
      <p className="mt-1 text-sm text-muted">All times shown in IST. Join opens once your enrolment and fees are clear.</p>

      {error && <p className="mt-4 rounded-[10px] bg-warn/10 px-3 py-2 text-sm text-warn">{error}</p>}

      {isLoading ? (
        <div className="mt-8 space-y-3">{Array.from({ length: 3 }).map((_, i) => <div key={i} className="shimmer h-16 rounded-[14px]" />)}</div>
      ) : classes.length === 0 ? (
        <div className="mt-8">
          <EmptyState title="No classes scheduled yet" body="Once you're enrolled in a batch, your weekly schedule and one-tap join links appear here." />
        </div>
      ) : (
        <div className="mt-8 space-y-8">
          {rhythm.length > 0 && rhythm.some((r) => r.slots.length > 0) && (
            <section>
              <p className="mono text-[11px] uppercase tracking-widest text-muted">Your weekly rhythm</p>
              <div className="mt-3 space-y-3">
                {rhythm.map((r) => (
                  <div key={r.batch} className="rounded-[14px] border border-line bg-white p-4">
                    <span className="mono text-xs font-semibold text-ink">{r.batch}</span>
                    <div className="mt-2 flex flex-wrap gap-1.5">
                      {r.slots.map((s) => (
                        <span key={`${s.day}-${s.time}`} className="rounded-full bg-sky px-3 py-1 text-[12px] font-semibold text-deep">
                          {s.day} · {s.time}
                        </span>
                      ))}
                    </div>
                  </div>
                ))}
              </div>
            </section>
          )}

          <section>
            <p className="mono text-[11px] uppercase tracking-widest text-muted">Upcoming</p>
            {days.length === 0 ? (
              <p className="mt-2 text-sm text-muted">No upcoming classes right now.</p>
            ) : (
              <div className="mt-3 space-y-5">
                {days.map((d) => (
                  <div key={d.key}>
                    <p className="mono mb-1.5 text-xs font-semibold text-ink">{d.heading}</p>
                    <div className="divide-y divide-line rounded-[14px] border border-line bg-white">
                      {d.items.map((c) => (
                        <div key={c.id} className="flex flex-wrap items-center gap-3 px-5 py-4">
                          <span className="mono w-20 text-sm font-semibold text-trust">{timeLabel(c.scheduled_start!)}</span>
                          <span className="min-w-40 flex-1">
                            <span className="flex items-center gap-2">
                              <span className="font-semibold text-ink">{c.title}</span>
                              {c.status === "live" && (
                                <span className="rounded-full bg-verify/10 px-2 py-0.5 text-[10px] font-semibold text-verify">Live now</span>
                              )}
                            </span>
                            <span className="mono block text-xs text-muted">
                              {c.batch}{c.topic ? ` · ${c.topic}` : ""}
                            </span>
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
                      <span className="mono block text-xs text-muted">
                        {c.scheduled_start ? `${dayHeading(c.scheduled_start)} · ` : ""}{c.batch}{c.topic ? ` · ${c.topic}` : ""}
                      </span>
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
