"use client";

import Link from "next/link";
import { use, useCallback, useEffect, useRef, useState } from "react";
import { ApiError, apiJson } from "@/lib/api";
import { Countdown } from "@/components/ui/Countdown";
import { Disclaimer } from "@/components/brand/Disclaimer";

type Question = { id: number; prompt: string; options: string[] };
type Quiz = {
  attempt_id: number;
  status: "pending" | "in_progress" | "submitted" | "expired";
  title: string;
  instructions: string | null;
  pass_pct: number;
  deadline_at: string | null;
  questions: Question[];
};
type ReviewRow = { id: number; correct_index: number; chosen: number | null; is_correct: boolean; explanation: string | null };
type Result = { status: string; score_pct: number; correct_count: number; total_count: number; passed: boolean; review: ReviewRow[] };

export default function McqPage({ params }: { params: Promise<{ attempt: string }> }) {
  const { attempt } = use(params);
  const [quiz, setQuiz] = useState<Quiz | null>(null);
  const [answers, setAnswers] = useState<Record<number, number>>({});
  const [result, setResult] = useState<Result | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const integrity = useRef({ tab_blurs: 0, paste_count: 0, started: Date.now() });
  const submitted = useRef(false);

  useEffect(() => {
    apiJson<{ data: Quiz }>(`/api/v1/me/mcq/${attempt}`)
      .then((r) => setQuiz(r.data))
      .catch(() => setError("This quiz could not be loaded."));
  }, [attempt]);

  // Advisory integrity signals — recorded for trainer review only.
  useEffect(() => {
    const onBlur = () => { integrity.current.tab_blurs += 1; };
    const onPaste = () => { integrity.current.paste_count += 1; };
    window.addEventListener("blur", onBlur);
    window.addEventListener("paste", onPaste);
    return () => { window.removeEventListener("blur", onBlur); window.removeEventListener("paste", onPaste); };
  }, []);

  const submit = useCallback(async () => {
    if (submitted.current) return;
    submitted.current = true;
    setBusy(true);
    setError(null);
    try {
      const r = await apiJson<{ data: Result }>(`/api/v1/me/mcq/${attempt}/submit`, {
        method: "POST",
        body: JSON.stringify({
          answers,
          integrity: {
            tab_blurs: integrity.current.tab_blurs,
            paste_count: integrity.current.paste_count,
            duration_sec: Math.round((Date.now() - integrity.current.started) / 1000),
          },
        }),
      });
      setResult(r.data);
    } catch (err) {
      submitted.current = false;
      setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Could not submit.");
    } finally {
      setBusy(false);
    }
  }, [answers, attempt]);

  if (error && !quiz) return <div className="mx-auto max-w-2xl text-sm text-muted">{error}</div>;
  if (!quiz) return <div className="mx-auto max-w-2xl"><div className="shimmer h-72 rounded-[14px]" /></div>;

  // Result screen.
  if (result) {
    return (
      <div className="mx-auto max-w-2xl">
        <p className="kicker text-trust">Quiz complete</p>
        <div className={`mt-4 rounded-2xl border p-6 ${result.passed ? "border-verify/30 bg-verify-bg" : "border-warn/30 bg-warn/10"}`}>
          <div className="mono text-4xl font-bold text-ink">{result.score_pct}%</div>
          <p className={`mt-1 text-sm font-semibold ${result.passed ? "text-verify" : "text-warn"}`}>
            {result.correct_count}/{result.total_count} correct — {result.passed ? "passed" : "keep practising"}
            {result.status === "expired" && " (submitted after the timer)"}
          </p>
          <Disclaimer />
        </div>
        <div className="mt-6 space-y-3">
          {result.review.map((row, i) => (
            <div key={row.id} className="rounded-[14px] border border-line bg-white p-4">
              <div className="flex items-center justify-between">
                <span className="text-sm font-semibold text-ink">Question {i + 1}</span>
                <span className={`mono text-xs font-semibold ${row.is_correct ? "text-verify" : "text-warn"}`}>
                  {row.is_correct ? "Correct" : "Incorrect"}
                </span>
              </div>
              {row.explanation && <p className="mt-2 text-sm text-muted">{row.explanation}</p>}
            </div>
          ))}
        </div>
        <Link href="/dashboard" className="mt-6 inline-block rounded-full bg-trust px-5 py-2.5 text-sm font-semibold text-white">
          Back to dashboard
        </Link>
      </div>
    );
  }

  // Already completed (opened after submit/expiry).
  if (quiz.status === "submitted" || quiz.status === "expired") {
    return (
      <div className="mx-auto max-w-2xl">
        <h1 className="display text-2xl text-ink">{quiz.title}</h1>
        <p className="mt-3 text-sm text-muted">You&apos;ve already completed this quiz. Your score is on your dashboard.</p>
        <Link href="/dashboard" className="mt-5 inline-block rounded-full bg-trust px-5 py-2.5 text-sm font-semibold text-white">Back to dashboard</Link>
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-2xl">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <p className="kicker text-trust">Module quiz</p>
          <h1 className="display mt-1 text-2xl text-ink">{quiz.title}</h1>
        </div>
        {quiz.deadline_at && <Countdown deadline={quiz.deadline_at} onExpire={submit} />}
      </div>
      {quiz.instructions && <p className="mt-3 rounded-[10px] border-l-2 border-trust bg-sky/40 px-3 py-2 text-sm text-ink">{quiz.instructions}</p>}

      <div className="mt-6 space-y-4">
        {quiz.questions.map((q, i) => (
          <div key={q.id} className="rounded-[14px] border border-line bg-white p-5">
            <p className="text-sm font-semibold text-ink">{i + 1}. {q.prompt}</p>
            <div className="mt-3 space-y-2">
              {q.options.map((opt, oi) => (
                <label key={oi} className={`flex cursor-pointer items-center gap-3 rounded-[10px] border px-3 py-2 text-sm ${answers[q.id] === oi ? "border-trust bg-sky/40 text-ink" : "border-line text-muted hover:bg-paper"}`}>
                  <input
                    type="radio"
                    name={`q-${q.id}`}
                    checked={answers[q.id] === oi}
                    onChange={() => setAnswers((a) => ({ ...a, [q.id]: oi }))}
                    className="accent-trust"
                  />
                  {opt}
                </label>
              ))}
            </div>
          </div>
        ))}
      </div>

      {error && <p className="mt-4 text-sm text-warn">{error}</p>}
      <button
        onClick={submit}
        disabled={busy}
        className="mt-6 rounded-full bg-trust px-6 py-2.5 text-sm font-semibold text-white transition-transform hover:-translate-y-0.5 disabled:opacity-50"
      >
        {busy ? "Submitting…" : "Submit quiz"}
      </button>
    </div>
  );
}
