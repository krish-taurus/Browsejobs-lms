"use client";

import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ApiError, apiJson } from "@/lib/api";

type HubSession = {
  id: number;
  student: string | null;
  purpose: string;
  status: string;
  no_show_side: string | null;
  starts_at: string;
  join_url: string | null;
  feedback: { score: number; strengths: string; improvements: string } | null;
  student_rating: number | null;
};

type Window = { id?: number; weekday: number; start_minute: number; end_minute: number };
type Exception = { id?: number; date: string; start_minute: number | null; end_minute: number | null };

type Hub = {
  profile: { id: number; headline: string | null; expertise: string[]; rating: number | null };
  upcoming: HubSession[];
  needs_action: HubSession[];
  past: HubSession[];
};

const DAYS = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];

function ist(iso: string): string {
  return new Date(iso).toLocaleString("en-IN", {
    timeZone: "Asia/Kolkata", weekday: "short", day: "numeric", month: "short", hour: "numeric", minute: "2-digit",
  });
}

function toTime(minute: number): string {
  return `${String(Math.floor(minute / 60)).padStart(2, "0")}:${String(minute % 60).padStart(2, "0")}`;
}

function toMinute(time: string): number {
  const [h, m] = time.split(":").map(Number);
  return h * 60 + m;
}

function FeedbackForm({ session, onDone }: { session: HubSession; onDone: () => void }) {
  const [score, setScore] = useState(70);
  const [strengths, setStrengths] = useState("");
  const [improvements, setImprovements] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  async function submit() {
    setBusy(true);
    setError(null);
    try {
      await apiJson(`/api/v1/me/mentorhub/${session.id}/feedback`, {
        method: "POST",
        body: JSON.stringify({ score, strengths, improvements }),
      });
      onDone();
    } catch (err) {
      setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Could not save feedback.");
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="mt-3 rounded-[10px] bg-paper p-3">
      {error && <p className="mb-2 text-xs text-warn">{error}</p>}
      <label className="flex items-center gap-2 text-xs text-ink">
        Readiness score
        <input type="number" min={0} max={100} value={score} onChange={(e) => setScore(Number(e.target.value))}
          className="w-20 rounded-[8px] border border-line bg-white px-2 py-1 text-sm" />
        /100
      </label>
      <textarea value={strengths} onChange={(e) => setStrengths(e.target.value)} rows={2} placeholder="Strengths…"
        className="mt-2 w-full rounded-[8px] border border-line bg-white px-2 py-1.5 text-xs" />
      <textarea value={improvements} onChange={(e) => setImprovements(e.target.value)} rows={2} placeholder="What to improve…"
        className="mt-2 w-full rounded-[8px] border border-line bg-white px-2 py-1.5 text-xs" />
      <button onClick={submit} disabled={busy || !strengths.trim() || !improvements.trim()}
        className="mt-2 rounded-full bg-trust px-4 py-1.5 text-xs font-semibold text-white disabled:opacity-50">
        {busy ? "Saving…" : "Submit feedback"}
      </button>
    </div>
  );
}

export default function MentoringHubPage() {
  const qc = useQueryClient();
  const [error, setError] = useState<string | null>(null);
  const [windows, setWindows] = useState<Window[] | null>(null);
  const [exceptions, setExceptions] = useState<Exception[] | null>(null);

  const hub = useQuery({
    queryKey: ["mentorhub"],
    queryFn: () => apiJson<{ data: Hub }>("/api/v1/me/mentorhub"),
    retry: false,
  });
  const availability = useQuery({
    queryKey: ["mentorhub", "availability"],
    queryFn: () => apiJson<{ data: { windows: Window[]; exceptions: Exception[] } }>("/api/v1/me/mentorhub/availability"),
    enabled: hub.isSuccess,
  });

  const refresh = () => qc.invalidateQueries({ queryKey: ["mentorhub"] });
  const onError = (err: unknown) =>
    setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Something went wrong.");

  const noShow = useMutation({
    mutationFn: ({ id, side }: { id: number; side: string }) =>
      apiJson(`/api/v1/me/mentorhub/${id}/no-show`, { method: "POST", body: JSON.stringify({ side }) }),
    onSuccess: refresh,
    onError,
  });

  const saveAvailability = useMutation({
    mutationFn: () =>
      apiJson("/api/v1/me/mentorhub/availability", {
        method: "PUT",
        body: JSON.stringify({
          windows: (windows ?? availability.data?.data.windows ?? []).map(({ weekday, start_minute, end_minute }) => ({ weekday, start_minute, end_minute })),
          exceptions: (exceptions ?? availability.data?.data.exceptions ?? []).map(({ date, start_minute, end_minute }) => ({ date: date.slice(0, 10), start_minute, end_minute })),
        }),
      }),
    onSuccess: () => { setWindows(null); setExceptions(null); setError(null); qc.invalidateQueries({ queryKey: ["mentorhub", "availability"] }); },
    onError,
  });

  if (hub.isLoading) return <div className="mx-auto max-w-3xl"><div className="shimmer h-64 rounded-[14px]" /></div>;
  if (hub.isError) {
    return (
      <div className="mx-auto max-w-3xl">
        <h1 className="display text-3xl text-ink">Mentoring</h1>
        <p className="mt-4 text-sm text-muted">You don&apos;t have a mentor profile. Ask an admin to add you on the Mentors page.</p>
      </div>
    );
  }

  const data = hub.data!.data;
  const w = windows ?? availability.data?.data.windows ?? [];
  const ex = exceptions ?? availability.data?.data.exceptions ?? [];

  const sessionRow = (s: HubSession, actions: boolean) => (
    <div key={s.id} className="rounded-[14px] border border-line bg-white p-4">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div>
          <p className="text-sm text-ink">
            {s.student}
            <span className="mono ml-2 text-[10px] uppercase tracking-widest text-muted">
              {s.purpose.replace("_", " ")} · {s.status.replace("_", " ")}{s.no_show_side && ` (${s.no_show_side})`}
            </span>
          </p>
          <p className="text-xs text-muted">{ist(s.starts_at)} IST</p>
        </div>
        <div className="flex items-center gap-3">
          {s.join_url && s.status === "booked" && (
            <a href={s.join_url} target="_blank" rel="noopener noreferrer" className="rounded-full bg-trust px-4 py-1.5 text-xs font-semibold text-white">Start</a>
          )}
          {actions && (
            <>
              <button onClick={() => noShow.mutate({ id: s.id, side: "student" })} className="text-xs text-warn hover:underline">Student no-show</button>
              <button onClick={() => noShow.mutate({ id: s.id, side: "mentor" })} className="text-xs text-muted hover:underline">I missed it</button>
            </>
          )}
        </div>
      </div>
      {actions && !s.feedback && <FeedbackForm session={s} onDone={refresh} />}
      {s.feedback && <p className="mt-2 text-xs text-muted">Feedback sent · {s.feedback.score}/100</p>}
    </div>
  );

  return (
    <div className="mx-auto max-w-3xl">
      <p className="kicker text-trust">Mentor workspace</p>
      <h1 className="display mt-2 text-3xl text-ink">Mentoring</h1>
      <p className="mt-1 text-sm text-muted">
        {data.profile.headline ?? "Your sessions and availability."}
        {data.profile.rating !== null && <span className="text-amber"> · ★ {data.profile.rating}</span>}
      </p>
      {error && <p className="mt-3 text-sm text-warn">{error}</p>}

      <h2 className="mt-8 text-sm font-semibold uppercase tracking-widest text-muted">
        Needs your action{data.needs_action.length > 0 && ` · ${data.needs_action.length}`}
      </h2>
      {data.needs_action.length === 0 ? (
        <p className="mt-3 text-sm text-muted">Nothing waiting — feedback forms appear here after each session.</p>
      ) : (
        <div className="mt-3 space-y-2">{data.needs_action.map((s) => sessionRow(s, true))}</div>
      )}

      <h2 className="mt-8 text-sm font-semibold uppercase tracking-widest text-muted">Upcoming</h2>
      {data.upcoming.length === 0 ? (
        <p className="mt-3 text-sm text-muted">No upcoming sessions.</p>
      ) : (
        <div className="mt-3 space-y-2">{data.upcoming.map((s) => sessionRow(s, false))}</div>
      )}

      <h2 className="mt-8 text-sm font-semibold uppercase tracking-widest text-muted">Weekly availability · IST</h2>
      <div className="mt-3 rounded-[14px] border border-line bg-white p-4">
        {w.length === 0 && <p className="text-sm text-muted">No windows yet — add one so students can book you.</p>}
        {w.map((win, i) => (
          <div key={i} className="mt-2 flex flex-wrap items-center gap-2 first:mt-0">
            <select value={win.weekday} onChange={(e) => setWindows(w.map((x, j) => j === i ? { ...x, weekday: Number(e.target.value) } : x))}
              className="rounded-[8px] border border-line bg-white px-2 py-1 text-xs">
              {DAYS.map((d, di) => <option key={di} value={di}>{d}</option>)}
            </select>
            <input type="time" value={toTime(win.start_minute)}
              onChange={(e) => setWindows(w.map((x, j) => j === i ? { ...x, start_minute: toMinute(e.target.value) } : x))}
              className="rounded-[8px] border border-line bg-white px-2 py-1 text-xs" />
            <span className="text-xs text-muted">to</span>
            <input type="time" value={toTime(win.end_minute)}
              onChange={(e) => setWindows(w.map((x, j) => j === i ? { ...x, end_minute: toMinute(e.target.value) } : x))}
              className="rounded-[8px] border border-line bg-white px-2 py-1 text-xs" />
            <button onClick={() => setWindows(w.filter((_, j) => j !== i))} className="text-xs text-warn hover:underline">Remove</button>
          </div>
        ))}
        <div className="mt-3 flex gap-2">
          <button onClick={() => setWindows([...w, { weekday: 1, start_minute: 600, end_minute: 720 }])}
            className="rounded-full border border-line bg-white px-4 py-1.5 text-xs font-semibold text-ink">
            + Add window
          </button>
          <button onClick={() => saveAvailability.mutate()} disabled={saveAvailability.isPending}
            className="rounded-full bg-trust px-4 py-1.5 text-xs font-semibold text-white disabled:opacity-50">
            {saveAvailability.isPending ? "Saving…" : "Save availability"}
          </button>
        </div>

        <p className="mt-4 text-xs font-semibold uppercase tracking-widest text-muted">Days off</p>
        {ex.map((e, i) => (
          <div key={i} className="mt-2 flex items-center gap-2">
            <input type="date" value={e.date.slice(0, 10)}
              onChange={(ev) => setExceptions(ex.map((x, j) => j === i ? { ...x, date: ev.target.value } : x))}
              className="rounded-[8px] border border-line bg-white px-2 py-1 text-xs" />
            <span className="text-xs text-muted">full day off</span>
            <button onClick={() => setExceptions(ex.filter((_, j) => j !== i))} className="text-xs text-warn hover:underline">Remove</button>
          </div>
        ))}
        <button onClick={() => setExceptions([...ex, { date: new Date().toISOString().slice(0, 10), start_minute: null, end_minute: null }])}
          className="mt-2 rounded-full border border-line bg-white px-4 py-1.5 text-xs font-semibold text-ink">
          + Add day off
        </button>
      </div>

      <h2 className="mt-8 text-sm font-semibold uppercase tracking-widest text-muted">Past</h2>
      {data.past.length === 0 ? (
        <p className="mt-3 text-sm text-muted">No past sessions yet.</p>
      ) : (
        <div className="mt-3 space-y-2">{data.past.map((s) => sessionRow(s, false))}</div>
      )}
    </div>
  );
}
