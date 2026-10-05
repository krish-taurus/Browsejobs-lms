"use client";

import { useEffect, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ApiError, apiJson } from "@/lib/api";

type PanelMember = { id: number; name: string | null; headline: string | null };

type RoundSession = {
  id: number;
  starts_at: string | null;
  requested_starts_at: string | null;
  duration_minutes: number;
  approval_status: "pending" | "approved" | "declined" | null;
  outcome: "cleared" | "not_cleared" | null;
  review_note: string | null;
  interviewer: string | null;
  join_url: string | null;
  ends_at: string | null;
  /** The slot passed and no verdict was recorded — it no longer holds the round. */
  lapsed: boolean;
};

type Round = {
  round: 1 | 2;
  label: string;
  stage: string;
  unlocked: boolean;
  locked_reason: string | null;
  session: RoundSession | null;
};

type Board = {
  cleared_round_one: boolean;
  rounds: Round[];
  panel: Record<string, PanelMember[]>;
  duration_minutes: number;
  min_notice_hours: number;
};

const TZ = "Asia/Kolkata";

function slotLabel(iso: string | null): string {
  if (!iso) return "—";
  return new Intl.DateTimeFormat("en-IN", {
    timeZone: TZ, weekday: "short", day: "numeric", month: "short",
    hour: "numeric", minute: "2-digit", hour12: true,
  }).format(new Date(iso));
}

/** The door opens a minute before the slot — early enough to be ready, not early enough to wander in. */
const JOIN_LEAD_MS = 60_000;

/**
 * And it shuts half an hour after the slot ends. Interviews run over, and a
 * dropped call needs rejoining — but a live Join button on an interview that
 * finished last week is worse than no button at all. The API withholds the URL
 * on the same rule, so this only decides what is drawn.
 */
const JOIN_GRACE_MS = 30 * 60_000;

function joinWindow(s: RoundSession, now: number): "early" | "open" | "closed" {
  const start = s.starts_at ? new Date(s.starts_at).getTime() : 0;
  const end = s.ends_at ? new Date(s.ends_at).getTime() : start + s.duration_minutes * 60_000;

  if (now < start - JOIN_LEAD_MS) return "early";

  return now <= end + JOIN_GRACE_MS ? "open" : "closed";
}

function joinOpensAt(iso: string | null): string {
  if (!iso) return "";
  return new Intl.DateTimeFormat("en-IN", {
    timeZone: TZ, hour: "numeric", minute: "2-digit", hour12: true,
  }).format(new Date(new Date(iso).getTime() - JOIN_LEAD_MS));
}

/** Earliest datetime the student may pick, as a value <input type="datetime-local"> accepts. */
function earliest(noticeHours: number): string {
  const t = new Date(Date.now() + noticeHours * 3600_000);
  const pad = (n: number) => String(n).padStart(2, "0");
  return `${t.getFullYear()}-${pad(t.getMonth() + 1)}-${pad(t.getDate())}T${pad(t.getHours())}:${pad(t.getMinutes())}`;
}

function ClockIcon() {
  return (
    <svg viewBox="0 0 16 16" aria-hidden="true" className="size-3.5 shrink-0" fill="none" stroke="currentColor" strokeWidth="1.5">
      <circle cx="8" cy="8" r="6.25" />
      <path d="M8 4.4V8l2.4 1.5" strokeLinecap="round" />
    </svg>
  );
}

function LockIcon() {
  return (
    <svg viewBox="0 0 16 16" aria-hidden="true" className="size-4 shrink-0" fill="none" stroke="currentColor" strokeWidth="1.5">
      <rect x="3.25" y="7" width="9.5" height="6.75" rx="1.5" />
      <path d="M5.75 7V4.9a2.25 2.25 0 0 1 4.5 0V7" />
    </svg>
  );
}

/** What the student sees for a round, in one place so both cards read the same. */
function statusOf(r: Round): { pill: string; tone: string; accent: string; line: string } {
  const s = r.session;

  if (!r.unlocked) {
    return { pill: "Locked", tone: "bg-paper text-muted", accent: "border-line", line: r.locked_reason ?? "" };
  }
  if (!s) {
    return { pill: "Not applied", tone: "bg-sky text-deep", accent: "border-trust", line: "Pick an interviewer and a time that suits you." };
  }
  if (s.outcome === "cleared") {
    return { pill: "Cleared", tone: "bg-verify-bg text-verify", accent: "border-verify", line: s.review_note || "You passed this round." };
  }
  if (s.outcome === "not_cleared") {
    return { pill: "Not cleared", tone: "bg-warn/15 text-warn", accent: "border-warn", line: s.review_note || "This round was not cleared." };
  }
  if (s.approval_status === "declined") {
    return { pill: "Declined", tone: "bg-warn/15 text-warn", accent: "border-warn", line: s.review_note || "Your request was declined." };
  }
  if (s.lapsed) {
    return {
      pill: "Slot passed",
      tone: "bg-paper text-muted",
      accent: "border-line",
      line:
        s.approval_status === "approved"
          ? "This slot has gone by and no result was recorded. Book another time below."
          : "This request was never confirmed and the time has gone by. Book another time below.",
    };
  }
  if (s.approval_status === "approved") {
    return { pill: "Confirmed", tone: "bg-verify-bg text-verify", accent: "border-verify", line: s.review_note || "Your slot is confirmed. Be ready five minutes early." };
  }
  return { pill: "Awaiting confirmation", tone: "bg-amber/15 text-ink2", accent: "border-amber", line: "The panel will confirm or move this slot — you'll get a WhatsApp either way." };
}

export default function InterviewsPage() {
  const qc = useQueryClient();
  const [openRound, setOpenRound] = useState<1 | 2 | null>(null);
  const [mentorId, setMentorId] = useState<string>("");
  const [when, setWhen] = useState<string>("");
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  // The join window opens while the page is already sitting open, so the page
  // has to notice the clock passing — nobody should have to refresh to get in.
  const [now, setNow] = useState(() => Date.now());
  useEffect(() => {
    const t = setInterval(() => setNow(Date.now()), 15_000);
    return () => clearInterval(t);
  }, []);

  const { data, isLoading } = useQuery({
    queryKey: ["me", "interviews"],
    queryFn: () => apiJson<{ data: Board }>("/api/v1/me/interviews"),
  });

  const board = data?.data;

  const apply = useMutation({
    mutationFn: (body: { round: number; mentor_profile_id: number; starts_at: string }) =>
      apiJson<{ message: string }>("/api/v1/me/interviews", { method: "POST", body: JSON.stringify(body) }),
    onSuccess: (r) => {
      setNotice(r.message);
      setOpenRound(null);
      setMentorId("");
      setWhen("");
      qc.invalidateQueries({ queryKey: ["me", "interviews"] });
    },
    onError: (e) => setError(e instanceof ApiError ? (e.firstError ?? e.message) : "Could not send your request."),
  });

  function submit() {
    setError(null);
    if (!openRound) return;
    if (!mentorId) { setError("Pick an interviewer."); return; }
    if (!when) { setError("Pick a date and time."); return; }
    apply.mutate({ round: openRound, mentor_profile_id: Number(mentorId), starts_at: new Date(when).toISOString() });
  }

  return (
    <div className="mx-auto max-w-4xl">
      <p className="kicker text-trust">Career</p>
      <h1 className="display mt-2 text-3xl text-ink">Interviews</h1>
      <p className="mt-2 text-sm text-muted">
        Two rounds with our team. A Tech Mentor screens you first; clear that and the final round with the Tech Manager opens.
        Pick a time that suits you — the panel confirms it, or moves it and tells you why.
      </p>

      {error && <p className="mt-4 rounded-[10px] bg-warn/10 px-3 py-2 text-sm text-warn">{error}</p>}
      {notice && <p className="mt-4 rounded-[10px] bg-sky px-3 py-2 text-sm text-deep">{notice}</p>}

      {isLoading || !board ? (
        <div className="mt-8 space-y-3">{Array.from({ length: 2 }).map((_, i) => <div key={i} className="shimmer h-[132px] rounded-[14px]" />)}</div>
      ) : (
        <div className="mt-8 space-y-4">
          {board.rounds.map((r) => {
            const st = statusOf(r);
            const s = r.session;
            const panel = board.panel[String(r.round)] ?? [];
            const canApply =
              r.unlocked && (!s || s.lapsed || s.approval_status === "declined" || s.outcome === "not_cleared");

            return (
              <section key={r.round} className="flex flex-col overflow-hidden rounded-[14px] border border-line bg-surface sm:flex-row">
                <div className="flex shrink-0 items-center gap-2 border-b border-line bg-paper px-5 py-3 sm:w-[124px] sm:flex-col sm:items-start sm:justify-center sm:gap-1 sm:border-b-0 sm:py-6">
                  <span className="display text-lg leading-none text-ink">{r.label}</span>
                  <span className="mono text-[11px] uppercase leading-none text-muted">
                    {r.round === 1 ? "Tech Mentor" : "Tech Manager"}
                  </span>
                </div>

                <div className={`flex-1 border-l-[3px] ${st.accent} px-5 py-4`}>
                  <div className="flex flex-wrap items-center gap-2">
                    <h2 className="font-semibold text-ink">{r.stage}</h2>
                    <span className={`mono rounded-full px-2.5 py-1 text-[10px] font-semibold uppercase tracking-widest ${st.tone}`}>
                      {!r.unlocked && <LockIcon />}
                      {st.pill}
                    </span>
                  </div>

                  {s && (
                    <div className="mono mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted">
                      <span className="inline-flex items-center gap-1.5"><ClockIcon />{slotLabel(s.starts_at)} IST</span>
                      <span>{s.duration_minutes} min</span>
                      {s.interviewer && <span>with {s.interviewer}</span>}
                    </div>
                  )}

                  {st.line && <p className="mt-2 text-sm text-muted">{st.line}</p>}

                  <div className="mt-3 flex flex-wrap items-center gap-3">
                    {/* Only once the interview is actually about to start, and
                        only while it is still ahead of you — a live Join button
                        on an interview that already happened is just confusing. */}
                    {r.unlocked && s?.approval_status === "approved" && !s.outcome && !s.lapsed && (
                      joinWindow(s, now) === "open" && s.join_url ? (
                        <a
                          href={s.join_url}
                          target="_blank"
                          rel="noopener"
                          className="rounded-full bg-trust px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-deep"
                        >
                          Join interview
                        </a>
                      ) : joinWindow(s, now) === "early" ? (
                        <span className="inline-flex items-center gap-1.5 rounded-full border border-line px-4 py-2.5 text-xs text-muted">
                          <ClockIcon />Join opens at {joinOpensAt(s.starts_at)} IST
                        </span>
                      ) : (
                        <span className="inline-flex items-center gap-1.5 rounded-full border border-line px-4 py-2.5 text-xs text-muted">
                          <ClockIcon />This interview has ended
                        </span>
                      )
                    )}

                    {canApply && panel.length > 0 && (
                      <button
                        onClick={() => { setOpenRound(r.round); setError(null); setNotice(null); }}
                        className="rounded-full bg-trust px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-deep"
                      >
                        {s ? "Apply again" : `Apply for ${r.label}`}
                      </button>
                    )}

                    {canApply && panel.length === 0 && (
                      <span className="mono text-xs text-muted">No interviewer is set up for this round yet.</span>
                    )}
                  </div>

                  {/* Apply form — interviewer plus a time you can actually make. */}
                  {openRound === r.round && (
                    <div className="mt-4 rounded-[10px] border border-line bg-paper p-4">
                      <div className="grid gap-3 sm:grid-cols-2">
                        <label className="block">
                          <span className="mono text-[11px] uppercase tracking-widest text-muted">Interviewer</span>
                          <select
                            value={mentorId}
                            onChange={(e) => setMentorId(e.target.value)}
                            className="mt-1.5 w-full rounded-[10px] border border-line bg-surface px-3 py-2 text-sm text-ink"
                          >
                            <option value="">Choose…</option>
                            {panel.map((m) => (
                              <option key={m.id} value={m.id}>{m.name}</option>
                            ))}
                          </select>
                        </label>

                        <label className="block">
                          <span className="mono text-[11px] uppercase tracking-widest text-muted">Date &amp; time (IST)</span>
                          <input
                            type="datetime-local"
                            value={when}
                            min={earliest(board.min_notice_hours)}
                            onChange={(e) => setWhen(e.target.value)}
                            className="mt-1.5 w-full rounded-[10px] border border-line bg-surface px-3 py-2 text-sm text-ink"
                          />
                        </label>
                      </div>

                      <p className="mono mt-2 text-[11px] text-muted">
                        At least {board.min_notice_hours} hours from now · {board.duration_minutes} minutes long
                      </p>

                      <div className="mt-3 flex items-center gap-3">
                        <button
                          onClick={submit}
                          disabled={apply.isPending}
                          className="rounded-full bg-trust px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-deep disabled:opacity-50"
                        >
                          {apply.isPending ? "Sending…" : "Send request"}
                        </button>
                        <button
                          onClick={() => { setOpenRound(null); setError(null); }}
                          className="text-sm font-semibold text-muted hover:text-ink"
                        >
                          Cancel
                        </button>
                      </div>
                    </div>
                  )}
                </div>
              </section>
            );
          })}
        </div>
      )}
    </div>
  );
}
