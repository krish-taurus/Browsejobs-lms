"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import { ApiError, apiJson } from "@/lib/api";

type Mentor = { id: number; name: string | null; headline: string | null; expertise: string[]; rating: number | null };
type Slot = { mentor_profile_id: number; mentor: string; expertise: string[]; starts_at: string };
type Topup = { product_id: number; sku: string; name: string; price_paise: number; sessions: number };
type Session = {
  id: number;
  mentor: string | null;
  purpose: string;
  status: string;
  starts_at: string;
  join_url: string | null;
  feedback: { score: number; strengths: string; improvements: string } | null;
  student_rating: number | null;
  can_change: boolean;
};

type Payload = { mentors: Mentor[]; slots: Slot[]; credits: number; topups: Topup[] };

const IST: Intl.DateTimeFormatOptions = { timeZone: "Asia/Kolkata" };

function istDay(iso: string): string {
  return new Date(iso).toLocaleDateString("en-IN", { ...IST, weekday: "short", day: "numeric", month: "short" });
}

function istTime(iso: string): string {
  return new Date(iso).toLocaleTimeString("en-IN", { ...IST, hour: "numeric", minute: "2-digit" });
}

export default function MentorsPage() {
  const [data, setData] = useState<Payload | null>(null);
  const [sessions, setSessions] = useState<Session[]>([]);
  const [loading, setLoading] = useState(true);
  const [tag, setTag] = useState<string | null>(null);
  const [busySlot, setBusySlot] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  const load = useCallback(() => {
    const query = tag ? `?tag=${encodeURIComponent(tag)}` : "";
    Promise.all([
      apiJson<{ data: Payload }>(`/api/v1/me/mentors${query}`),
      apiJson<{ data: Session[] }>("/api/v1/me/mentor-sessions"),
    ])
      .then(([m, s]) => { setData(m.data); setSessions(s.data); })
      .catch(() => setData(null))
      .finally(() => setLoading(false));
  }, [tag]);

  useEffect(() => { load(); }, [load]);

  const tags = useMemo(
    () => Array.from(new Set((data?.mentors ?? []).flatMap((m) => m.expertise))).sort(),
    [data?.mentors],
  );

  // Day → time → the mentors free at that time, so parallel availability is
  // first-class: 10:00 with three mentors shows three bookable chips.
  const byDay = useMemo(() => {
    const days = new Map<string, Map<string, Slot[]>>();
    for (const slot of data?.slots ?? []) {
      const day = istDay(slot.starts_at);
      const time = istTime(slot.starts_at);
      const times = days.get(day) ?? new Map<string, Slot[]>();
      times.set(time, [...(times.get(time) ?? []), slot]);
      days.set(day, times);
    }
    return Array.from(days.entries()).slice(0, 7);
  }, [data?.slots]);

  async function book(slot: Slot) {
    setError(null);
    setNotice(null);
    setBusySlot(`${slot.mentor_profile_id}-${slot.starts_at}`);
    try {
      await apiJson("/api/v1/me/mentor-sessions", {
        method: "POST",
        body: JSON.stringify({ mentor_profile_id: slot.mentor_profile_id, starts_at: slot.starts_at }),
      });
      setNotice("Booked! Your mentor will connect with you directly at the scheduled time — see My sessions below.");
      load();
    } catch (err) {
      if (err instanceof ApiError && err.status === 402) {
        setError("You're out of mentor credits — top up below or finish more modules.");
      } else {
        setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Could not book that slot.");
      }
    } finally {
      setBusySlot(null);
    }
  }

  async function act(id: number, path: string, body?: object) {
    setError(null);
    try {
      await apiJson(`/api/v1/me/mentor-sessions/${id}/${path}`, {
        method: "POST",
        body: JSON.stringify(body ?? {}),
      });
      load();
    } catch (err) {
      setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Something went wrong.");
    }
  }

  async function buyTopup(t: Topup) {
    setError(null);
    try {
      await apiJson("/api/v1/me/purchases", { method: "POST", body: JSON.stringify({ product_id: t.product_id }) });
      setNotice("Order created — complete the payment from the Store page and your session lands instantly.");
    } catch (err) {
      setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Could not start the purchase.");
    }
  }

  if (loading) return <div className="mx-auto max-w-3xl"><div className="shimmer h-64 rounded-[14px]" /></div>;
  if (!data) return <div className="mx-auto max-w-3xl text-sm text-muted">Mentoring isn&apos;t available right now.</div>;

  const upcoming = sessions.filter((s) => s.status === "booked");
  const past = sessions.filter((s) => s.status !== "booked");

  return (
    <div className="mx-auto max-w-3xl">
      <div className="flex items-baseline justify-between">
        <h1 className="display text-2xl text-ink">Mentors</h1>
        <span className="mono text-xs text-muted">{data.credits} session{data.credits === 1 ? "" : "s"} left</span>
      </div>
      <p className="mt-1 text-sm text-muted">
        Book a 1:1 with a mentor — pick any open slot below (times in IST). Your mentor connects
        with you directly at the scheduled time. Finish a module to earn extra sessions.
      </p>

      {error && <p className="mt-3 text-sm text-warn">{error}</p>}
      {notice && <p className="mt-3 text-sm text-verify">{notice}</p>}

      {tags.length > 0 && (
        <div className="mt-4 flex flex-wrap gap-2">
          <button
            onClick={() => setTag(null)}
            className={`rounded-full px-3 py-1 text-xs font-semibold ${tag === null ? "bg-trust text-white" : "border border-line bg-white text-ink"}`}
          >
            All
          </button>
          {tags.map((t) => (
            <button
              key={t}
              onClick={() => setTag(t)}
              className={`rounded-full px-3 py-1 text-xs font-semibold ${tag === t ? "bg-trust text-white" : "border border-line bg-white text-ink"}`}
            >
              {t}
            </button>
          ))}
        </div>
      )}

      <div className="mt-4 grid gap-3 sm:grid-cols-2">
        {data.mentors.map((m) => (
          <div key={m.id} className="rounded-[14px] border border-line bg-white p-4">
            <div className="flex items-baseline justify-between">
              <p className="text-sm font-semibold text-ink">{m.name}</p>
              {m.rating !== null && <span className="mono text-xs text-amber">★ {m.rating}</span>}
            </div>
            {m.headline && <p className="mt-0.5 text-xs text-muted">{m.headline}</p>}
            <p className="mono mt-1 text-[10px] uppercase tracking-widest text-muted">{m.expertise.join(" · ")}</p>
          </div>
        ))}
      </div>

      <h2 className="mt-8 text-sm font-semibold uppercase tracking-widest text-muted">Open slots · IST</h2>
      {byDay.length === 0 ? (
        <p className="mt-3 text-sm text-muted">No open slots{tag ? ` for ${tag}` : ""} in the next two weeks.</p>
      ) : (
        <div className="mt-3 space-y-4">
          {byDay.map(([day, times]) => (
            <div key={day}>
              <p className="text-xs font-semibold text-ink">{day}</p>
              <div className="mt-1.5 space-y-1.5">
                {Array.from(times.entries()).map(([time, mentorSlots]) => (
                  <div key={time} className="flex flex-wrap items-center gap-2">
                    <span className="mono w-20 shrink-0 text-xs text-muted">{time}</span>
                    {mentorSlots.map((slot) => {
                      const key = `${slot.mentor_profile_id}-${slot.starts_at}`;
                      return (
                        <button
                          key={key}
                          onClick={() => book(slot)}
                          disabled={busySlot === key}
                          title={`Book ${slot.mentor} at ${time}`}
                          className="rounded-full border border-line bg-white px-3 py-1 text-xs text-ink hover:border-trust disabled:opacity-50"
                        >
                          {busySlot === key ? "Booking…" : slot.mentor}
                        </button>
                      );
                    })}
                  </div>
                ))}
              </div>
            </div>
          ))}
        </div>
      )}

      {data.credits === 0 && data.topups.length > 0 && (
        <div className="mt-6 rounded-[14px] border border-line bg-white p-4">
          <p className="text-sm text-ink">Out of sessions? Top up:</p>
          <div className="mt-2 flex flex-wrap gap-2">
            {data.topups.map((t) => (
              <button
                key={t.product_id}
                onClick={() => buyTopup(t)}
                className="rounded-full border border-line bg-white px-4 py-2 text-sm font-semibold text-ink hover:border-trust"
              >
                {t.sessions} session{t.sessions === 1 ? "" : "s"} · ₹{Math.round(t.price_paise / 100)}
              </button>
            ))}
          </div>
        </div>
      )}

      <h2 className="mt-8 text-sm font-semibold uppercase tracking-widest text-muted">My sessions</h2>
      {sessions.length === 0 ? (
        <p className="mt-3 text-sm text-muted">Nothing booked yet.</p>
      ) : (
        <div className="mt-3 space-y-2">
          {[...upcoming, ...past].map((s) => (
            <div key={s.id} className="rounded-[14px] border border-line bg-white p-4">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <div>
                  <p className="text-sm text-ink">
                    {s.mentor} <span className="mono text-[10px] uppercase tracking-widest text-muted">· {s.purpose.replace("_", " ")} · {s.status.replace("_", " ")}</span>
                  </p>
                  <p className="text-xs text-muted">{istDay(s.starts_at)} · {istTime(s.starts_at)} IST</p>
                </div>
                <div className="flex flex-wrap items-center gap-3">
                  {s.status === "booked" && s.join_url && (
                    <a href={s.join_url} target="_blank" rel="noopener noreferrer" className="rounded-full bg-trust px-4 py-1.5 text-xs font-semibold text-white">Join</a>
                  )}
                  {s.status === "booked" && !s.join_url && (
                    <span className="text-xs text-muted">Your mentor will contact you directly</span>
                  )}
                  {s.status === "booked" && (
                    <a href={`${process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000"}/api/v1/me/mentor-sessions/${s.id}/ics`} className="text-xs text-trust hover:underline">.ics</a>
                  )}
                  {s.can_change && (
                    <button onClick={() => act(s.id, "cancel")} className="text-xs text-warn hover:underline">Cancel</button>
                  )}
                  {s.status === "completed" && s.student_rating === null && (
                    <span className="flex items-center gap-1">
                      {[1, 2, 3, 4, 5].map((n) => (
                        <button key={n} onClick={() => act(s.id, "rate", { rating: n })} aria-label={`Rate ${n} stars`} className="text-sm text-amber hover:scale-110">★</button>
                      ))}
                    </span>
                  )}
                  {s.student_rating !== null && <span className="mono text-xs text-amber">★ {s.student_rating}</span>}
                </div>
              </div>
              {s.feedback && (
                <div className="mt-3 rounded-[10px] bg-paper p-3 text-xs">
                  <p className="text-ink"><span className="font-semibold">Mentor feedback · {s.feedback.score}/100.</span> {s.feedback.strengths}</p>
                  <p className="mt-1 text-muted">Work on: {s.feedback.improvements}</p>
                </div>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
