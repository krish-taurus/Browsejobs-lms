"use client";

import Link from "next/link";
import { use, useCallback, useEffect, useRef, useState } from "react";
import { ApiError, apiJson } from "@/lib/api";
import { isBudgetError } from "@/lib/tutor";

type Turn = { id: number; role: "interviewer" | "candidate"; body: string };

type Scorecard = {
  overall: number;
  competencies: { name: string; score: number }[];
  strong_moments: string[];
  weak_moments: string[];
  model_answers: { question: string; answer: string }[];
  actions: string[];
};

type MockSession = {
  id: number;
  status: "in_progress" | "completed" | "abandoned";
  mode: "text" | "voice";
  join_url: string | null;
  duration_seconds: number | null;
  role_title: string | null;
  questions_asked: number;
  max_questions: number;
  min_answers: number;
  ready_to_finish: boolean;
  overall_score: number | null;
  scorecard: Scorecard | null;
  scorecard_source: string | null;
  turns: Turn[];
};

function barTone(score: number): string {
  if (score >= 70) return "bg-verify";
  if (score >= 40) return "bg-trust";
  return "bg-warn";
}

function ScorecardView({ session }: { session: MockSession }) {
  const card = session.scorecard;
  if (!card) return null;

  return (
    <div className="mt-8 rounded-2xl border border-line bg-white p-6">
      <div className="flex items-baseline justify-between">
        <h2 className="display text-xl text-ink">Your scorecard</h2>
        <span className="display text-3xl text-trust">{card.overall}<span className="text-base text-muted">/100</span></span>
      </div>

      {session.scorecard_source === "fallback" && (
        <p className="mt-3 rounded-[10px] bg-paper px-3 py-2 text-xs text-muted">
          Automated grading was unavailable, so this session is scored conservatively. Retake a mock
          for a full AI scorecard.
        </p>
      )}

      <div className="mt-5 space-y-2">
        {card.competencies.map((c) => (
          <div key={c.name}>
            <div className="flex justify-between text-xs text-muted"><span>{c.name}</span><span>{c.score}</span></div>
            <div className="mt-1 h-2 rounded-full bg-paper">
              <div className={`h-2 rounded-full ${barTone(c.score)}`} style={{ width: `${Math.max(4, c.score)}%` }} />
            </div>
          </div>
        ))}
      </div>

      {card.strong_moments.length > 0 && (
        <div className="mt-6">
          <h3 className="text-xs font-semibold uppercase tracking-widest text-verify">What landed well</h3>
          <ul className="mt-2 space-y-1 text-sm text-ink">
            {card.strong_moments.map((s, i) => <li key={i}>• {s}</li>)}
          </ul>
        </div>
      )}

      {card.weak_moments.length > 0 && (
        <div className="mt-5">
          <h3 className="text-xs font-semibold uppercase tracking-widest text-warn">Where you lost marks</h3>
          <ul className="mt-2 space-y-1 text-sm text-ink">
            {card.weak_moments.map((s, i) => <li key={i}>• {s}</li>)}
          </ul>
        </div>
      )}

      {card.model_answers.length > 0 && (
        <div className="mt-5">
          <h3 className="text-xs font-semibold uppercase tracking-widest text-muted">Model answers</h3>
          <div className="mt-2 space-y-3">
            {card.model_answers.map((m, i) => (
              <div key={i} className="rounded-[10px] bg-paper p-3">
                <p className="text-xs font-semibold text-muted">{m.question}</p>
                <p className="mt-1 text-sm text-ink">{m.answer}</p>
              </div>
            ))}
          </div>
        </div>
      )}

      <div className="mt-6 rounded-[14px] border border-trust/30 bg-sky/30 p-4">
        <h3 className="text-xs font-semibold uppercase tracking-widest text-deep">Do these 3 things this week</h3>
        <ol className="mt-2 space-y-1 text-sm text-ink">
          {card.actions.map((a, i) => <li key={i}>{i + 1}. {a}</li>)}
        </ol>
      </div>
    </div>
  );
}

export default function MockSessionPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const [session, setSession] = useState<MockSession | null>(null);
  const [loading, setLoading] = useState(true);
  const [answer, setAnswer] = useState("");
  const [busy, setBusy] = useState(false);
  const [finishing, setFinishing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [budgetHit, setBudgetHit] = useState(false);
  const bottomRef = useRef<HTMLDivElement | null>(null);

  const load = useCallback(() => {
    apiJson<{ data: MockSession }>(`/api/v1/me/mocks/${id}`)
      .then((r) => setSession(r.data))
      .catch(() => setSession(null))
      .finally(() => setLoading(false));
  }, [id]);

  useEffect(() => { load(); }, [load]);
  useEffect(() => { bottomRef.current?.scrollIntoView({ behavior: "smooth" }); }, [session?.turns.length]);

  async function send() {
    if (!answer.trim()) return;
    setError(null);
    setBusy(true);
    try {
      const r = await apiJson<{ data: MockSession }>(`/api/v1/me/mocks/${id}/answer`, {
        method: "POST",
        body: JSON.stringify({ answer }),
      });
      setSession(r.data);
      setAnswer("");
    } catch (err) {
      if (isBudgetError(err)) setBudgetHit(true);
      else setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Something went wrong.");
    } finally {
      setBusy(false);
    }
  }

  async function finish() {
    setError(null);
    setFinishing(true);
    try {
      const r = await apiJson<{ data: MockSession }>(`/api/v1/me/mocks/${id}/finish`, { method: "POST" });
      setSession(r.data);
    } catch (err) {
      setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Something went wrong.");
    } finally {
      setFinishing(false);
    }
  }

  if (loading) return <div className="mx-auto max-w-2xl"><div className="shimmer h-64 rounded-[14px]" /></div>;
  if (!session) return <div className="mx-auto max-w-2xl text-sm text-muted">Interview not found.</div>;

  const answered = session.turns.filter((t) => t.role === "candidate").length;
  const canFinish = session.status === "in_progress" && answered >= session.min_answers;
  const done = session.status === "completed";

  return (
    <div className="mx-auto max-w-2xl">
      <Link href="/mock" className="text-sm text-trust hover:underline">← Mock Interviews</Link>
      <div className="mt-3 flex items-baseline justify-between">
        <h1 className="display text-2xl text-ink">{session.role_title ?? "Mock interview"}</h1>
        {!done && (
          <span className="mono text-xs text-muted">
            Q {Math.min(session.questions_asked, session.max_questions)}/{session.max_questions}
          </span>
        )}
      </div>

      <div className="mt-6 space-y-3">
        {session.turns.map((t) => {
          const mine = t.role === "candidate";
          return (
            <div key={t.id} className={`rounded-[14px] border border-line p-4 ${mine ? "bg-sky/40" : "bg-white"}`}>
              <span className="mono text-[10px] uppercase tracking-widest text-muted">
                {mine ? "You" : "Interviewer"}
              </span>
              <p className="mt-2 whitespace-pre-wrap text-sm text-ink">{t.body}</p>
            </div>
          );
        })}
        <div ref={bottomRef} />
      </div>

      {done ? (
        <ScorecardView session={session} />
      ) : session.status === "abandoned" ? (
        <div className="mt-6 rounded-2xl border border-line bg-white p-6 text-center">
          <p className="text-sm text-ink">This call ended before there was enough to grade.</p>
          <p className="mt-1 text-sm text-muted">Your session credit was refunded — start a fresh one when you&apos;re ready.</p>
        </div>
      ) : session.mode === "voice" ? (
        <div className="mt-6 rounded-2xl border border-line bg-white p-6 text-center">
          <p className="text-sm text-ink">This is a live voice interview.</p>
          {session.join_url ? (
            <a
              href={session.join_url}
              target="_blank"
              rel="noopener noreferrer"
              className="mt-3 inline-block rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white"
            >
              Rejoin the call →
            </a>
          ) : (
            <p className="mt-1 text-sm text-muted">Your scorecard appears here once the call ends.</p>
          )}
        </div>
      ) : budgetHit ? (
        <div className="mt-6 rounded-2xl border border-line bg-white p-6 text-center">
          <p className="text-sm text-ink">You&apos;ve reached today&apos;s AI practice limit.</p>
          <p className="mt-1 text-sm text-muted">
            It resets tomorrow.{canFinish && " You can still finish now for a scorecard."}
          </p>
          {canFinish && (
            <button
              onClick={finish}
              disabled={finishing}
              className="mt-4 rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white disabled:opacity-50"
            >
              {finishing ? "Grading…" : "Finish & get scorecard"}
            </button>
          )}
        </div>
      ) : (
        <div className="mt-6">
          {error && <p className="mb-2 text-sm text-warn">{error}</p>}
          {session.ready_to_finish ? (
            <p className="mb-2 text-sm text-muted">That&apos;s the full round — finish up to get your scorecard.</p>
          ) : (
            <>
              <textarea
                value={answer}
                onChange={(e) => setAnswer(e.target.value)}
                rows={4}
                placeholder="Answer like it's the real thing — structure first, then detail…"
                className="w-full resize-none rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust"
              />
              <button
                onClick={send}
                disabled={busy || !answer.trim()}
                className="mt-2 rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white disabled:opacity-50"
              >
                {busy ? "Interviewer is thinking…" : "Send answer"}
              </button>
            </>
          )}
          {canFinish && (
            <button
              onClick={finish}
              disabled={finishing || busy}
              className={`mt-2 rounded-full px-5 py-2 text-sm font-semibold disabled:opacity-50 ${
                session.ready_to_finish ? "bg-trust text-white" : "ml-2 border border-line bg-white text-ink"
              }`}
            >
              {finishing ? "Grading your interview…" : "Finish & get scorecard"}
            </button>
          )}
        </div>
      )}
    </div>
  );
}
