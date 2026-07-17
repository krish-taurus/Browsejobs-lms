"use client";

import { useCallback, useEffect, useState } from "react";
import { ApiError, apiJson } from "@/lib/api";

type Pulse = { id: number; milestone: string };
type Pause = { id: number; status: string; reason: string; paused_until: string | null };

type Payload = {
  pending_pulses: Pulse[];
  answered: { milestone: string; score: number }[];
  pauses: Pause[];
  can_request_pause: boolean;
};

const MILESTONE_LABEL: Record<string, string> = {
  week1: "your first week",
  mid: "the midpoint of your course",
  pre_placement: "the run-up to placement",
};

export default function CheckinPage() {
  const [data, setData] = useState<Payload | null>(null);
  const [loading, setLoading] = useState(true);
  const [score, setScore] = useState<number | null>(null);
  const [comment, setComment] = useState("");
  const [reviewUrl, setReviewUrl] = useState<string | null>(null);
  const [routed, setRouted] = useState<string | null>(null);
  const [showPause, setShowPause] = useState(false);
  const [pauseReason, setPauseReason] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  const load = useCallback(() => {
    apiJson<{ data: Payload }>("/api/v1/me/checkin")
      .then((r) => setData(r.data))
      .catch(() => setData(null))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => { load(); }, [load]);

  async function submit(pulse: Pulse) {
    if (score === null) return;
    setError(null);
    try {
      const r = await apiJson<{ data: { routed: string; review_url: string | null } }>(`/api/v1/me/nps/${pulse.id}`, {
        method: "POST",
        body: JSON.stringify({ score, comment: comment.trim() || null }),
      });
      setRouted(r.data.routed);
      setReviewUrl(r.data.review_url);
      setScore(null);
      setComment("");
      load();
    } catch (err) {
      setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Could not save that.");
    }
  }

  async function requestPause() {
    setError(null);
    try {
      await apiJson("/api/v1/me/pause", { method: "POST", body: JSON.stringify({ reason: pauseReason }) });
      setShowPause(false);
      setPauseReason("");
      setNotice("Request sent — your counselor will call to sort out the details. Your seat stays safe.");
      load();
    } catch (err) {
      setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Could not send the request.");
    }
  }

  if (loading) return <div className="mx-auto max-w-2xl"><div className="shimmer h-64 rounded-[14px]" /></div>;
  if (!data) return <div className="mx-auto max-w-2xl text-sm text-muted">Check-in isn&apos;t available right now.</div>;

  const pulse = data.pending_pulses[0];

  return (
    <div className="mx-auto max-w-2xl">
      <h1 className="display text-2xl text-ink">Check-in</h1>
      <p className="mt-1 text-sm text-muted">Quick pulse checks at key moments — and a pause option if life gets in the way.</p>

      {error && <p className="mt-3 text-sm text-warn">{error}</p>}
      {notice && <p className="mt-3 text-sm text-verify">{notice}</p>}

      {/* Post-submit thank-you states */}
      {routed === "google_review" && reviewUrl && (
        <div className="mt-5 rounded-2xl border border-verify/30 bg-verify-bg p-5">
          <p className="text-sm font-semibold text-verify">Thank you — that means a lot.</p>
          <p className="mt-1 text-sm text-ink">
            If you have two more minutes, a public review helps other students find us. Completely
            optional, no strings attached.
          </p>
          <a href={reviewUrl} target="_blank" rel="noopener noreferrer"
            className="mt-3 inline-block rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white">
            Leave a Google review →
          </a>
        </div>
      )}
      {routed === "rescue" && (
        <div className="mt-5 rounded-2xl border border-line bg-white p-5">
          <p className="text-sm font-semibold text-ink">Thank you for the honesty.</p>
          <p className="mt-1 text-sm text-muted">A counselor has been alerted and will call you today. This gets fixed.</p>
        </div>
      )}
      {routed === "none" && (
        <p className="mt-5 text-sm text-verify">Noted — thank you!</p>
      )}

      {/* Pending pulse */}
      {pulse && (
        <div className="mt-5 rounded-2xl border border-line bg-white p-6">
          <p className="text-sm text-ink">
            How likely are you to recommend BrowseJobs to a friend, based on {MILESTONE_LABEL[pulse.milestone] ?? "your experience"}?
          </p>
          <div className="mt-4 flex flex-wrap gap-1.5">
            {Array.from({ length: 11 }, (_, n) => (
              <button key={n} onClick={() => setScore(n)}
                className={`h-9 w-9 rounded-[10px] text-sm font-semibold ${score === n ? "bg-trust text-white" : "border border-line bg-white text-ink hover:border-trust"}`}>
                {n}
              </button>
            ))}
          </div>
          <div className="mt-1 flex justify-between text-[10px] uppercase tracking-widest text-muted">
            <span>Not likely</span><span>Extremely likely</span>
          </div>
          <textarea value={comment} onChange={(e) => setComment(e.target.value)} rows={2}
            placeholder="Anything you want us to know? (optional)"
            className="mt-3 w-full resize-none rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust" />
          <button onClick={() => submit(pulse)} disabled={score === null}
            className="mt-3 rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white disabled:opacity-50">
            Send
          </button>
        </div>
      )}

      {!pulse && data.answered.length > 0 && routed === null && (
        <p className="mt-5 text-sm text-muted">All caught up — thanks for the feedback so far.</p>
      )}

      {/* Pause / defer */}
      <div className="mt-8 rounded-2xl border border-line bg-white p-6">
        <h2 className="text-xs font-semibold uppercase tracking-widest text-muted">Need a break?</h2>
        <p className="mt-2 text-sm text-ink">
          Life happens. You can pause your enrolment and rejoin a later batch — your seat and your
          progress stay safe. No penalties, no drama.
        </p>

        {data.pauses.length > 0 && (
          <div className="mt-3 space-y-1">
            {data.pauses.map((p) => (
              <p key={p.id} className="mono text-[11px] uppercase tracking-widest text-muted">
                {p.status}{p.paused_until && ` until ${p.paused_until}`} — {p.reason.slice(0, 60)}
              </p>
            ))}
          </div>
        )}

        {data.can_request_pause && !showPause && (
          <button onClick={() => setShowPause(true)}
            className="mt-3 rounded-full border border-line bg-white px-5 py-2 text-sm font-semibold text-ink hover:border-trust">
            Request a pause
          </button>
        )}
        {showPause && (
          <div className="mt-3">
            <textarea value={pauseReason} onChange={(e) => setPauseReason(e.target.value)} rows={3}
              placeholder="Tell us what's going on — this goes straight to your counselor…"
              className="w-full resize-none rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust" />
            <div className="mt-2 flex gap-2">
              <button onClick={requestPause} disabled={pauseReason.trim().length < 10}
                className="rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white disabled:opacity-50">
                Send request
              </button>
              <button onClick={() => setShowPause(false)} className="rounded-full border border-line bg-white px-5 py-2 text-sm text-ink">
                Cancel
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
