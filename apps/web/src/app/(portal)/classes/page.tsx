"use client";

import Link from "next/link";
import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { ApiError, apiJson } from "@/lib/api";
import { EmptyState } from "@/components/ui/EmptyState";

type LiveClass = {
  id: number;
  title: string;
  kind: string;
  batch: string | null;
  topic: string | null;
  scheduled_start: string | null;
  scheduled_end: string | null;
  status: string;
  has_recording: boolean;
  recording_id: number | null;
  join_opens_at: string | null;
  can_join: boolean;
  blocked_reason: "fees" | "too_early" | "ended" | "not_ready" | null;
};

type GoodNews = {
  id: number;
  display: string;
  role_title: string;
  company: string | null;
  photo_url: string | null;
  video_url: string | null;
  published_at: string | null;
};

type TopVideo = { id: number; title: string; url: string; views: number };

// The platform runs on IST — show every class time in Asia/Kolkata so a student
// never has to convert, matching the trainer's "My teaching" board.
const TZ = "Asia/Kolkata";
const WEEKDAY_ORDER = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"];

// The weekly-rhythm card already says "Mon–Fri, 9pm" — listing every single
// occurrence of that out to whenever the batch happens to end (months away)
// just turns the page into a long, near-identical scroll. Two weeks is
// enough to actually plan around; the rhythm card covers the rest.
const UPCOMING_DAYS_WINDOW = 14;

// The full two-week window can still be a dozen-plus cards, and "past
// classes" only grows every week — both used to render in full, turning the
// page into one long scroll. Show a short head by default; "Show all" is one
// click away, so nothing is actually hidden, just collapsed.
const UPCOMING_COLLAPSED_COUNT = 4;
const PAST_COLLAPSED_COUNT = 3;

function part(iso: string, opts: Intl.DateTimeFormatOptions): string {
  return new Intl.DateTimeFormat("en-IN", { timeZone: TZ, ...opts }).format(new Date(iso));
}
function dayKey(iso: string): string {
  return new Intl.DateTimeFormat("en-CA", { timeZone: TZ, year: "numeric", month: "2-digit", day: "numeric" }).format(new Date(iso));
}
function weekday(iso: string): string {
  return new Intl.DateTimeFormat("en-US", { timeZone: TZ, weekday: "short" }).format(new Date(iso));
}
function timeLabel(iso: string): string {
  return part(iso, { hour: "numeric", minute: "2-digit", hour12: true });
}
const dateLabel = (iso: string) => part(iso, { month: "short", day: "2-digit" });
const weekdayLong = (iso: string) => part(iso, { weekday: "long" });

/** Minutes past midnight IST — sorts "9:00 am" before "7:00 pm", which a string compare does not. */
function minutesOfDay(iso: string): number {
  const [h, m] = new Intl.DateTimeFormat("en-GB", { timeZone: TZ, hour: "2-digit", minute: "2-digit", hour12: false })
    .format(new Date(iso)).split(":");
  return Number(h) * 60 + Number(m);
}

function dayHeading(iso: string): string {
  const key = dayKey(iso);
  const now = new Date();
  const todayKey = dayKey(now.toISOString());
  const tomorrowKey = dayKey(new Date(now.getTime() + 86_400_000).toISOString());
  if (key === todayKey) return "Today";
  if (key === tomorrowKey) return "Tomorrow";
  return part(iso, { weekday: "short", day: "numeric", month: "short" });
}

/**
 * Distinct time slots per batch, with the days each one runs. Grouped by TIME
 * rather than by day+time pair: a batch that meets daily at 7pm is one line,
 * not seven near-identical chips the eye has to diff.
 *
 * @return list<{batch: string, slots: list<{time: string, mins: int, days: list<string>}>}>
 */
function weeklyRhythm(upcoming: LiveClass[]): { batch: string; slots: { time: string; mins: number; days: string[] }[] }[] {
  const byBatch = new Map<string, Map<string, { mins: number; days: Set<string> }>>();
  for (const c of upcoming) {
    if (!c.scheduled_start) continue;
    const batch = c.batch ?? "Your batch";
    const time = timeLabel(c.scheduled_start);
    if (!byBatch.has(batch)) byBatch.set(batch, new Map());
    const times = byBatch.get(batch)!;
    if (!times.has(time)) times.set(time, { mins: minutesOfDay(c.scheduled_start), days: new Set() });
    times.get(time)!.days.add(weekday(c.scheduled_start));
  }
  return [...byBatch.entries()].map(([batch, times]) => ({
    batch,
    slots: [...times.entries()]
      .map(([time, { mins, days }]) => ({
        time,
        mins,
        days: [...days].sort((a, b) => WEEKDAY_ORDER.indexOf(a) - WEEKDAY_ORDER.indexOf(b)),
      }))
      .sort((a, b) => a.mins - b.mins),
  }));
}

/** "Every day" / "Mon–Fri" / "Weekends", falling back to a plain list. */
function daysLabel(days: string[]): string {
  if (days.length === 7) return "Every day";
  if (days.length === 5 && WEEKDAY_ORDER.slice(0, 5).every((d) => days.includes(d))) return "Mon–Fri";
  if (days.length === 2 && days.includes("Sat") && days.includes("Sun")) return "Weekends";
  return days.join(", ");
}

/** A youtu.be / youtube.com URL's video id, or null for anything else (Drive link, Vimeo, etc). */
function youtubeId(url: string): string | null {
  try {
    const u = new URL(url);
    if (u.hostname === "youtu.be") return u.pathname.slice(1) || null;
    if (u.hostname.endsWith("youtube.com")) {
      if (u.pathname === "/watch") return u.searchParams.get("v");
      if (u.pathname.startsWith("/embed/")) return u.pathname.split("/")[2] ?? null;
      if (u.pathname.startsWith("/shorts/")) return u.pathname.split("/")[2] ?? null;
    }
    return null;
  } catch {
    return null;
  }
}

function viewsLabel(n: number): string {
  if (n >= 1_000_000) return `${(n / 1_000_000).toFixed(1)}M views`;
  if (n >= 1_000) return `${(n / 1_000).toFixed(1)}k views`;
  return `${n} view${n === 1 ? "" : "s"}`;
}

function ClockIcon() {
  return (
    <svg viewBox="0 0 16 16" aria-hidden="true" className="size-3.5 shrink-0" fill="none" stroke="currentColor" strokeWidth="1.5">
      <circle cx="8" cy="8" r="6.25" />
      <path d="M8 4.4V8l2.4 1.5" strokeLinecap="round" />
    </svg>
  );
}

function PlayIcon({ className = "size-3.5" }: { className?: string }) {
  return (
    <svg viewBox="0 0 16 16" aria-hidden="true" className={`shrink-0 ${className}`} fill="currentColor">
      <path d="M5 3.4a.6.6 0 0 1 .92-.51l6.3 4.1a.6.6 0 0 1 0 1.02l-6.3 4.1A.6.6 0 0 1 5 12.6V3.4Z" />
    </svg>
  );
}

const ACCENTS: Record<string, { border: string; wash: string; rail: string }> = {
  live: { border: "border-verify", wash: "bg-verify/[0.04]", rail: "bg-verify/10 text-verify" },
  fees: { border: "border-warn", wash: "bg-warn/[0.04]", rail: "bg-warn/10 text-warn" },
  open: { border: "border-trust", wash: "bg-trust/[0.04]", rail: "bg-trust/10 text-trust" },
  waiting: { border: "border-line", wash: "bg-transparent", rail: "bg-paper text-ink" },
};

/** Shared card shell: a state-tinted time badge, an accent rail, content. */
function ClassCard({ rail, sub, state, children }: {
  rail: string;
  sub: string;
  state: keyof typeof ACCENTS;
  children: React.ReactNode;
}) {
  const a = ACCENTS[state];
  return (
    <article
      className={`flex flex-col overflow-hidden rounded-2xl border ${a.border}/40 ${a.wash} shadow-[0_1px_2px_rgba(16,24,40,0.03)] transition hover:shadow-[0_4px_16px_-6px_rgba(16,24,40,0.12)] sm:flex-row`}
    >
      <div className={`flex shrink-0 items-center gap-2 px-5 py-3 sm:w-[104px] sm:flex-col sm:items-start sm:justify-center sm:gap-0.5 sm:py-6 ${a.rail}`}>
        <span className="display text-xl font-semibold leading-none">{rail}</span>
        <span className="mono text-[10px] uppercase leading-none tracking-wider opacity-80">{sub}</span>
      </div>
      <div className="flex flex-1 flex-wrap items-center gap-x-4 gap-y-3 border-t border-line/60 bg-surface px-5 py-4 sm:border-l sm:border-t-0">
        {children}
      </div>
    </article>
  );
}

/** "Someone got an offer" — a real, consented placement story with a face and (when the
 *  student shared one) a short video, so the next student sees proof, not just a promise. */
function GoodNewsCard({ news }: { news: GoodNews }) {
  const ytId = news.video_url ? youtubeId(news.video_url) : null;
  return (
    <div className="overflow-hidden rounded-2xl border border-verify/30 bg-verify/[0.04] shadow-[0_1px_2px_rgba(16,24,40,0.03)]">
      <div className="flex items-center gap-2 border-b border-verify/20 bg-verify/10 px-4 py-2.5">
        <span aria-hidden className="text-sm">🎉</span>
        <span className="mono text-[10px] font-semibold uppercase tracking-widest text-verify">Good news</span>
      </div>
      {news.photo_url && (
        // A forced aspect ratio + object-cover cropped whatever didn't fit
        // — for a graphic with text near the edges (a "before/after" card,
        // say) that cuts off words. Natural height instead: the whole image
        // always shows, whatever shape it actually is.
        // eslint-disable-next-line @next/next/no-img-element -- signed S3 URL, not an optimizable static asset
        <img src={news.photo_url} alt={news.display} className="block h-auto w-full" />
      )}
      <div className="p-4">
        <div className="flex items-center gap-3">
          {!news.photo_url && (
            <span className="flex size-12 shrink-0 items-center justify-center rounded-full bg-verify/15 font-semibold text-verify">
              {news.display.charAt(0).toUpperCase()}
            </span>
          )}
          <div className="min-w-0">
            <p className="truncate text-sm font-semibold text-ink">{news.display}</p>
            <p className="truncate text-xs text-muted">
              {news.role_title}{news.company ? ` · ${news.company}` : ""}
            </p>
          </div>
        </div>

        {ytId ? (
          <div className="mt-3 overflow-hidden rounded-xl border border-line/60">
            <iframe
              className="aspect-video w-full"
              src={`https://www.youtube-nocookie.com/embed/${ytId}`}
              title={`${news.display}'s story`}
              allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
              allowFullScreen
            />
          </div>
        ) : news.video_url ? (
          <a
            href={news.video_url}
            target="_blank"
            rel="noopener noreferrer"
            className="mt-3 inline-flex items-center gap-2 rounded-full bg-verify px-3.5 py-2 text-xs font-semibold text-white transition hover:opacity-90"
          >
            <PlayIcon />Watch their story
          </a>
        ) : null}
      </div>
    </div>
  );
}

/** Our best-watched Content Hub video — "best-watched" measured on this platform's own
 *  telemetry (how many students actually opened it), not a number pulled from YouTube. */
function TopVideoCard({ video }: { video: TopVideo }) {
  const ytId = youtubeId(video.url);
  return (
    <div className="overflow-hidden rounded-2xl border border-line bg-surface shadow-[0_1px_2px_rgba(16,24,40,0.03)]">
      <div className="flex items-center gap-2 border-b border-line/60 bg-paper px-4 py-2.5">
        <PlayIcon className="size-3.5 text-deep" />
        <span className="mono text-[10px] font-semibold uppercase tracking-widest text-muted">Most-watched on BrowseJobs</span>
      </div>
      <div className="p-4">
        {ytId ? (
          <a
            href={video.url}
            target="_blank"
            rel="noopener noreferrer"
            className="block overflow-hidden rounded-xl border border-line/60"
          >
            {/* eslint-disable-next-line @next/next/no-img-element -- external thumbnail host, not a static asset */}
            <img
              src={`https://i.ytimg.com/vi/${ytId}/hqdefault.jpg`}
              alt={video.title}
              className="aspect-video w-full object-cover"
            />
          </a>
        ) : null}
        <a
          href={video.url}
          target="_blank"
          rel="noopener noreferrer"
          className="mt-3 block text-sm font-semibold text-ink hover:underline"
        >
          {video.title}
        </a>
        {/* A just-added video with nobody having watched it yet reads as
            broken with "0 views" printed on day one — say nothing until
            there's a real number to show. */}
        {video.views > 0 && (
          <p className="mono mt-1 text-[11px] text-muted">{viewsLabel(video.views)} on BrowseJobs</p>
        )}
      </div>
    </div>
  );
}

function Sidebar() {
  const { data } = useQuery({
    queryKey: ["me", "classes", "sidebar"],
    queryFn: () => apiJson<{ data: { good_news: GoodNews | null; top_video: TopVideo | null } }>("/api/v1/me/classes/sidebar"),
  });

  const goodNews = data?.data.good_news ?? null;
  const topVideo = data?.data.top_video ?? null;

  if (!goodNews && !topVideo) return null;

  return (
    <aside className="space-y-4 lg:sticky lg:top-6">
      {goodNews && <GoodNewsCard news={goodNews} />}
      {topVideo && <TopVideoCard video={topVideo} />}
    </aside>
  );
}

export default function ClassesPage() {
  const [joining, setJoining] = useState<number | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [showAllUpcoming, setShowAllUpcoming] = useState(false);
  const [showAllPast, setShowAllPast] = useState(false);

  const { data, isLoading } = useQuery({
    queryKey: ["me", "classes"],
    queryFn: () => apiJson<{ data: LiveClass[] }>("/api/v1/me/classes"),
  });

  const classes = data?.data ?? [];
  const allUpcoming = classes
    .filter((c) => (c.status === "scheduled" || c.status === "live") && c.scheduled_start)
    .sort((a, b) => (a.scheduled_start! < b.scheduled_start! ? -1 : 1)); // soonest first
  // Most recent first — "last 3" should mean the 3 classes that just
  // happened, not whatever order the API happened to return.
  const past = classes
    .filter((c) => c.status === "ended")
    .sort((a, b) => (b.scheduled_start ?? "").localeCompare(a.scheduled_start ?? ""));
  const rhythm = weeklyRhythm(allUpcoming);

  // The rhythm card above already answers "when do my classes run" for the
  // whole batch — this list only needs to answer "what's coming up soon,"
  // so it stops at a two-week horizon instead of scrolling for months.
  const windowEnd = Date.now() + UPCOMING_DAYS_WINDOW * 86_400_000;
  const upcomingWindow = allUpcoming.filter((c) => new Date(c.scheduled_start!).getTime() <= windowEnd);
  const hiddenCount = allUpcoming.length - upcomingWindow.length;

  const upcoming = showAllUpcoming ? upcomingWindow : upcomingWindow.slice(0, UPCOMING_COLLAPSED_COUNT);
  const upcomingMoreCount = upcomingWindow.length - upcoming.length;

  const pastVisible = showAllPast ? past : past.slice(0, PAST_COLLAPSED_COUNT);
  const pastMoreCount = past.length - pastVisible.length;

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
    <div className="mx-auto max-w-6xl lg:grid lg:grid-cols-[minmax(0,1fr)_296px] lg:items-start lg:gap-8">
      <div className="min-w-0 max-w-2xl">
        <p className="kicker text-trust">My Classes</p>
        <h1 className="display mt-2 text-3xl text-ink">Your class schedule</h1>
        <p className="mt-2 text-sm text-muted">All times shown in IST. Join opens just before each class, once your fees are clear.</p>

        {error && <p className="mt-4 rounded-[10px] bg-warn/10 px-3 py-2 text-sm text-warn">{error}</p>}

        {isLoading ? (
          <div className="mt-8 space-y-3">{Array.from({ length: 3 }).map((_, i) => <div key={i} className="shimmer h-[92px] rounded-2xl" />)}</div>
        ) : classes.length === 0 ? (
          <div className="mt-8">
            <EmptyState title="No classes scheduled yet" body="Once you're enrolled in a batch, your weekly schedule and one-tap join links appear here." />
          </div>
        ) : (
          <div className="mt-8 space-y-9">
            {rhythm.length > 0 && rhythm.some((r) => r.slots.length > 0) && (
              <section>
                <p className="mono text-[11px] uppercase tracking-widest text-muted">Your weekly rhythm</p>
                <div className="mt-3 grid gap-3 sm:grid-cols-2">
                  {rhythm.map((r) => (
                    <div key={r.batch} className="rounded-2xl border border-line bg-surface p-4 shadow-[0_1px_2px_rgba(16,24,40,0.03)]">
                      <span className="mono text-xs font-semibold text-ink">{r.batch}</span>
                      <div className="mt-3 space-y-2">
                        {r.slots.map((s) => (
                          <div key={s.time} className="flex flex-wrap items-center gap-x-2.5 gap-y-1">
                            <span className="mono rounded-full bg-sky px-3 py-1 text-[12px] font-semibold text-deep">{s.time}</span>
                            <span className="text-xs text-muted">{daysLabel(s.days)}</span>
                          </div>
                        ))}
                      </div>
                    </div>
                  ))}
                </div>
              </section>
            )}

            <section>
              <div className="flex items-baseline justify-between">
                <p className="mono text-[11px] uppercase tracking-widest text-muted">Upcoming</p>
                {hiddenCount > 0 && (
                  <p className="text-[11px] text-muted">
                    Next {UPCOMING_DAYS_WINDOW} days shown — {hiddenCount} more follow the pattern above.
                  </p>
                )}
              </div>
              {days.length === 0 ? (
                <p className="mt-2 text-sm text-muted">No upcoming classes right now.</p>
              ) : (
                <div className="mt-3 space-y-6">
                  {days.map((d) => (
                    <div key={d.key}>
                      <p className="mono mb-2 text-xs font-semibold text-ink">{d.heading}</p>
                      <div className="space-y-3">
                        {d.items.map((c) => {
                          // The topic is often just the class title again — showing
                          // "Python basics · Python basics" reads like a bug.
                          const topic = c.topic && c.topic !== c.title ? c.topic : null;
                          const [hm, ap] = timeLabel(c.scheduled_start!).split(" ");
                          const state: keyof typeof ACCENTS = c.status === "live" ? "live"
                            : c.blocked_reason === "fees" ? "fees"
                            : c.can_join ? "open"
                            : "waiting";

                          return (
                            <ClassCard key={c.id} rail={hm} sub={ap ?? ""} state={state}>
                              <div className="min-w-48 flex-1">
                                <div className="flex flex-wrap items-center gap-2">
                                  <h3 className="font-semibold text-ink">{c.title}</h3>
                                  {c.kind === "mentoring" && (
                                    <span className="mono rounded-full bg-sky px-2 py-0.5 text-[10px] uppercase tracking-widest text-deep">Mentoring</span>
                                  )}
                                  {c.status === "live" && (
                                    <span className="inline-flex items-center gap-1.5 rounded-full bg-verify/10 px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-widest text-verify">
                                      <span className="size-1.5 rounded-full bg-verify" />Live now
                                    </span>
                                  )}
                                </div>
                                <div className="mono mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted">
                                  {c.batch && <span>{c.batch}</span>}
                                  {topic && <span>· {topic}</span>}
                                </div>
                              </div>

                              {/* The server decides: unpaid dues come first, then the
                                  join window, then whether the room exists. Showing a
                                  Join button that only fails on click helps nobody. */}
                              {c.blocked_reason === "fees" ? (
                                <span
                                  title="Your fee dues are past the grace period, so live classes are locked."
                                  className="shrink-0 rounded-full bg-warn px-4 py-2.5 text-xs font-semibold text-white"
                                >
                                  Pay fees to unlock
                                </span>
                              ) : c.can_join ? (
                                <button
                                  onClick={() => join(c)}
                                  disabled={joining === c.id}
                                  className="shrink-0 rounded-full bg-trust px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-deep disabled:opacity-50"
                                >
                                  {joining === c.id ? "Opening…" : "Join class"}
                                </button>
                              ) : c.blocked_reason === "ended" ? (
                                // A finished class is not "not ready" — it is over. Point
                                // at the recording when there is one, so the student has
                                // somewhere to go instead of a dead badge.
                                c.has_recording ? (
                                  <Link
                                    href="/recordings"
                                    className="inline-flex shrink-0 items-center gap-2 rounded-full bg-sky px-4 py-2.5 text-xs font-semibold text-deep transition hover:bg-line"
                                  >
                                    <PlayIcon />Watch recording
                                  </Link>
                                ) : (
                                  <span className="shrink-0 rounded-full border border-line px-4 py-2.5 text-xs text-muted">Class ended</span>
                                )
                              ) : (
                                <span className="inline-flex shrink-0 items-center gap-1.5 rounded-full border border-line px-4 py-2.5 text-xs text-muted">
                                  <ClockIcon />
                                  {c.blocked_reason === "too_early" && c.join_opens_at
                                    ? `Opens ${timeLabel(c.join_opens_at)}`
                                    : "Not ready yet"}
                                </span>
                              )}
                            </ClassCard>
                          );
                        })}
                      </div>
                    </div>
                  ))}
                </div>
              )}
              {upcomingMoreCount > 0 && (
                <button
                  onClick={() => setShowAllUpcoming(true)}
                  className="mt-4 text-xs font-semibold text-trust hover:underline"
                >
                  Show {upcomingMoreCount} more upcoming class{upcomingMoreCount === 1 ? "" : "es"} →
                </button>
              )}
              {showAllUpcoming && upcomingWindow.length > UPCOMING_COLLAPSED_COUNT && (
                <button
                  onClick={() => setShowAllUpcoming(false)}
                  className="mt-4 block text-xs font-semibold text-muted hover:underline"
                >
                  Show less
                </button>
              )}
            </section>

            {past.length > 0 && (
              <section>
                <p className="mono text-[11px] uppercase tracking-widest text-muted">Past classes</p>
                <div className="mt-3 space-y-3">
                  {pastVisible.map((c) => {
                    const topic = c.topic && c.topic !== c.title ? c.topic : null;

                    return (
                      <ClassCard
                        key={c.id}
                        rail={c.scheduled_start ? dateLabel(c.scheduled_start) : "—"}
                        sub={c.scheduled_start ? weekdayLong(c.scheduled_start) : ""}
                        state="waiting"
                      >
                        <div className="min-w-48 flex-1">
                          <h3 className="font-semibold text-ink">{c.title}</h3>
                          <div className="mono mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted">
                            {c.batch && <span>{c.batch}</span>}
                            {topic && <span>· {topic}</span>}
                          </div>
                        </div>
                        {c.has_recording ? (
                          <Link
                            href="/recordings"
                            className="inline-flex shrink-0 items-center gap-2 rounded-full bg-sky px-4 py-2.5 text-xs font-semibold text-deep transition hover:bg-line"
                          >
                            <PlayIcon />Watch recording
                          </Link>
                        ) : (
                          <span className="shrink-0 rounded-full border border-line px-4 py-2.5 text-xs text-muted">No recording</span>
                        )}
                      </ClassCard>
                    );
                  })}
                </div>
                {pastMoreCount > 0 && (
                  <button
                    onClick={() => setShowAllPast(true)}
                    className="mt-4 text-xs font-semibold text-trust hover:underline"
                  >
                    Show {pastMoreCount} more past class{pastMoreCount === 1 ? "" : "es"} →
                  </button>
                )}
                {showAllPast && past.length > PAST_COLLAPSED_COUNT && (
                  <button
                    onClick={() => setShowAllPast(false)}
                    className="mt-4 block text-xs font-semibold text-muted hover:underline"
                  >
                    Show less
                  </button>
                )}
              </section>
            )}
          </div>
        )}
      </div>

      <div className="mt-9 lg:mt-[76px]">
        <Sidebar />
      </div>
    </div>
  );
}
