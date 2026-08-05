"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { apiJson } from "@/lib/api";
import { EmptyState } from "@/components/ui/EmptyState";

type QuizRow = {
  attempt_id: number;
  title: string;
  course: string | null;
  module: string | null;
  questions: number;
  minutes: number;
  pass_pct: number;
  status: "pending" | "in_progress" | "submitted" | "expired";
  due_at: string | null;
  deadline_at: string | null;
  score_pct: number | null;
  passed: boolean | null;
  submitted_at: string | null;
};

const dueLabel = (iso: string | null) => {
  if (!iso) return null;
  const due = new Date(iso);
  const days = Math.ceil((due.getTime() - Date.now()) / 86_400_000);
  if (days < 0) return "Overdue";
  if (days === 0) return "Due today";
  if (days === 1) return "Due tomorrow";
  return `Due ${due.toLocaleDateString(undefined, { day: "numeric", month: "short" })}`;
};

export default function QuizzesPage() {
  const [quizzes, setQuizzes] = useState<QuizRow[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    apiJson<{ data: QuizRow[] }>("/api/v1/me/quizzes")
      .then((r) => setQuizzes(r.data))
      .catch(() => setQuizzes([]))
      .finally(() => setLoading(false));
  }, []);

  const open = quizzes.filter((q) => q.status === "pending" || q.status === "in_progress");
  const done = quizzes.filter((q) => q.status === "submitted" || q.status === "expired");

  return (
    <div className="mx-auto max-w-2xl">
      <p className="kicker text-trust">Assessments</p>
      <h1 className="display mt-2 text-3xl text-ink">My quizzes</h1>
      <p className="mt-2 text-sm text-muted">
        Tests your trainer has opened for your batch. Each one is timed from the moment you start, so sit it in one go.
      </p>

      {loading ? (
        <div className="mt-6 space-y-3">
          {Array.from({ length: 2 }).map((_, i) => <div key={i} className="shimmer h-20 rounded-[14px]" />)}
        </div>
      ) : quizzes.length === 0 ? (
        <div className="mt-6">
          <EmptyState
            title="No quizzes yet"
            body="A quiz appears here as soon as your trainer opens one for your batch — usually at the end of a module."
          />
        </div>
      ) : (
        <>
          {open.length > 0 && (
            <div className="mt-6 space-y-3">
              {open.map((q) => {
                const due = dueLabel(q.due_at);
                const overdue = due === "Overdue";
                return (
                  <Link
                    key={q.attempt_id}
                    href={`/mcq/${q.attempt_id}`}
                    className="block rounded-[14px] border border-trust/30 bg-white p-5 transition-colors hover:bg-paper"
                  >
                    <div className="flex flex-wrap items-start justify-between gap-2">
                      <div>
                        <p className="font-semibold text-ink">{q.title}</p>
                        <p className="mt-0.5 text-xs text-muted">
                          {[q.module, q.course].filter(Boolean).join(" · ") || "Assessment"}
                        </p>
                      </div>
                      {due && (
                        <span className={`mono rounded-full px-2.5 py-0.5 text-[10px] uppercase tracking-widest ${overdue ? "bg-warn/15 text-warn" : "bg-sky text-deep"}`}>
                          {due}
                        </span>
                      )}
                    </div>
                    <div className="mt-3 flex items-center justify-between gap-3">
                      <span className="text-xs text-muted">
                        {q.questions} question{q.questions === 1 ? "" : "s"} · {q.minutes} min · pass {q.pass_pct}%
                      </span>
                      <span className="text-sm font-semibold text-trust">
                        {q.status === "in_progress" ? "Resume →" : "Start →"}
                      </span>
                    </div>
                  </Link>
                );
              })}
            </div>
          )}

          {done.length > 0 && (
            <>
              <p className="mono mt-8 text-[10px] uppercase tracking-widest text-muted">Completed</p>
              <div className="mt-2 divide-y divide-line rounded-[14px] border border-line bg-white">
                {done.map((q) => (
                  <Link
                    key={q.attempt_id}
                    href={`/quizzes/${q.attempt_id}/leaderboard`}
                    className="flex items-center justify-between gap-3 px-5 py-3 hover:bg-paper"
                  >
                    <div className="min-w-0">
                      <p className="truncate text-sm text-ink">{q.title}</p>
                      <p className="text-xs text-muted">
                        {q.module ?? q.course ?? "Assessment"} · see how your batch did
                      </p>
                    </div>
                    {q.status === "expired" && q.score_pct === null ? (
                      <span className="mono text-xs text-muted">Missed</span>
                    ) : (
                      <span className={`mono text-sm font-semibold ${q.passed ? "text-verify" : "text-warn"}`}>
                        {q.score_pct}%
                      </span>
                    )}
                  </Link>
                ))}
              </div>
            </>
          )}
        </>
      )}
    </div>
  );
}
