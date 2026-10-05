"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { apiJson } from "@/lib/api";

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

const satOn = (iso: string | null) =>
  iso ? new Date(iso).toLocaleDateString("en-IN", { day: "numeric", month: "short", year: "numeric" }) : null;

function ClockIcon() {
  return (
    <svg viewBox="0 0 16 16" aria-hidden="true" className="size-3.5 shrink-0" fill="none" stroke="currentColor" strokeWidth="1.5">
      <circle cx="8" cy="8" r="6.25" />
      <path d="M8 4.4V8l2.4 1.5" strokeLinecap="round" />
    </svg>
  );
}

function ListIcon() {
  return (
    <svg viewBox="0 0 16 16" aria-hidden="true" className="size-3.5 shrink-0" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round">
      <path d="M6 4.5h7.5M6 8h7.5M6 11.5h7.5M2.75 4.5h.01M2.75 8h.01M2.75 11.5h.01" />
    </svg>
  );
}

/** Shared card shell: tinted rail on the left, state-coloured accent, content. */
function QuizCard({ rail, railTone, sub, accent, children }: {
  rail: string;
  railTone: string;
  sub: string;
  accent: string;
  children: React.ReactNode;
}) {
  return (
    <div className="flex flex-col overflow-hidden rounded-[14px] border border-line bg-surface transition hover:border-trust/50 hover:shadow-soft sm:flex-row">
      <div className="flex shrink-0 items-center gap-2 border-b border-line bg-paper px-5 py-3 sm:w-[112px] sm:flex-col sm:items-start sm:justify-center sm:gap-1 sm:border-b-0 sm:py-6">
        <span className={`display text-lg leading-none ${railTone}`}>{rail}</span>
        <span className="mono text-[11px] uppercase leading-none text-muted">{sub}</span>
      </div>
      <div className={`flex flex-1 flex-wrap items-center gap-x-4 gap-y-3 border-l-[3px] ${accent} px-5 py-4`}>
        {children}
      </div>
    </div>
  );
}

function CheckIcon() {
  return (
    <svg viewBox="0 0 16 16" aria-hidden="true" className="size-3.5" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <path d="M3.5 8.5l3 3 6-7" />
    </svg>
  );
}

function HomeIcon() {
  return (
    <svg viewBox="0 0 16 16" aria-hidden="true" className="size-4" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round">
      <path d="M2 7.5 8 2l6 5.5" />
      <path d="M3.5 6.5V13a1 1 0 0 0 1 1H6.5v-4h3v4H11.5a1 1 0 0 0 1-1V6.5" />
    </svg>
  );
}

function PracticeIcon() {
  return (
    <svg viewBox="0 0 16 16" aria-hidden="true" className="size-4" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round">
      <path d="M5.5 4 2 8l3.5 4M10.5 4 14 8l-3.5 4M9 3l-2 10" />
    </svg>
  );
}

function BulbIcon() {
  return (
    <svg viewBox="0 0 16 16" aria-hidden="true" className="size-4 shrink-0" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
      <path d="M8 1.5A4.5 4.5 0 0 0 5.5 9.7c.4.35.6.85.6 1.37V11.5h3.8v-.43c0-.52.2-1.02.6-1.37A4.5 4.5 0 0 0 8 1.5Z" />
      <path d="M6.2 13.5h3.6M6.7 14.5h2.6" />
    </svg>
  );
}

/** Rich empty state for the quizzes page specifically — the plain shared
 *  EmptyState reads as "nothing here"; this one reads as "you're ahead". */
function QuizzesEmptyState() {
  return (
    <div className="relative mt-8 overflow-hidden rounded-2xl border border-line bg-white px-6 py-14">
      {/* Faint dotted texture in two corners — decorative only. */}
      <div
        aria-hidden
        className="pointer-events-none absolute -left-2 -top-2 h-24 w-24 opacity-40"
        style={{ backgroundImage: "radial-gradient(var(--bj-line,#e4e6eb) 1.4px, transparent 1.4px)", backgroundSize: "14px 14px" }}
      />
      <div
        aria-hidden
        className="pointer-events-none absolute -bottom-2 -right-2 h-24 w-24 opacity-40"
        style={{ backgroundImage: "radial-gradient(var(--bj-line,#e4e6eb) 1.4px, transparent 1.4px)", backgroundSize: "14px 14px" }}
      />
      <div aria-hidden className="pointer-events-none absolute -bottom-10 -left-10 size-40 rounded-full bg-sky/60 blur-2xl" />
      <div aria-hidden className="pointer-events-none absolute -right-8 top-1/3 size-28 rounded-full bg-trust/10 blur-2xl" />

      <div className="relative mx-auto flex max-w-sm flex-col items-center text-center">
        {/* eslint-disable-next-line @next/next/no-img-element -- static illustration, not an optimizable content photo */}
        <img src="/art/mathematics-bro.png" alt="" className="h-40 w-auto" />

        <span className="mono mt-2 inline-flex items-center gap-1.5 rounded-full bg-verify/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-widest text-verify">
          <CheckIcon />You&apos;re all caught up
        </span>

        <h3 className="display mt-4 text-xl text-ink">No quizzes yet</h3>
        <p className="mt-2 text-sm text-muted">
          A quiz will appear here as soon as your trainer opens one for your batch — usually at the end of a module.
        </p>

        <div className="mt-6 flex flex-wrap items-center justify-center gap-3">
          <Link
            href="/dashboard"
            className="inline-flex items-center gap-2 rounded-full bg-trust px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-deep"
          >
            <HomeIcon />Go to dashboard
          </Link>
          <Link
            href="/labs"
            className="inline-flex items-center gap-2 rounded-full border border-line px-5 py-2.5 text-sm font-semibold text-ink transition hover:bg-paper"
          >
            <PracticeIcon />Practice while you wait
          </Link>
        </div>

        <div className="mt-7 w-full border-t border-line pt-4">
          <p className="flex items-start gap-2 text-left text-xs text-muted">
            <BulbIcon />
            <span>
              <span className="font-semibold text-ink">Tip:</span> Keep an eye on this page after completing a
              module. Quizzes are usually released by your trainer at the end of each module.
            </span>
          </p>
        </div>

        {/* Storyset's free-use license requires this credit to stay visible
            wherever the illustration is used. */}
        <a
          href="https://storyset.com/work"
          target="_blank"
          rel="noopener noreferrer"
          className="mt-4 text-[10px] text-muted/70 hover:text-muted hover:underline"
        >
          Illustration by Storyset
        </a>
      </div>
    </div>
  );
}

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
    <div className="mx-auto max-w-4xl">
      <p className="kicker text-trust">Assessments</p>
      <h1 className="display mt-2 text-3xl text-ink">My quizzes</h1>
      <p className="mt-2 text-sm text-muted">
        Tests your trainer has opened for your batch. Each one is timed from the moment you start, so sit it in one go.
      </p>

      {loading ? (
        <div className="mt-8 space-y-3">
          {Array.from({ length: 2 }).map((_, i) => <div key={i} className="shimmer h-[104px] rounded-[14px]" />)}
        </div>
      ) : quizzes.length === 0 ? (
        <QuizzesEmptyState />
      ) : (
        <div className="mt-8 space-y-9">
          {open.length > 0 && (
            <section>
              <div className="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <h2 className="display text-lg text-ink">Open now</h2>
                <span className="mono text-xs text-muted">{open.length} to sit</span>
              </div>

              <div className="mt-3 space-y-3">
                {open.map((q) => {
                  const due = dueLabel(q.due_at);
                  const overdue = due === "Overdue";

                  return (
                    <QuizCard
                      key={q.attempt_id}
                      rail={String(q.minutes)}
                      railTone="text-ink"
                      sub="min"
                      accent={overdue ? "border-warn" : "border-trust"}
                    >
                      <div className="min-w-48 flex-1">
                        <div className="flex flex-wrap items-center gap-2">
                          <h3 className="font-semibold text-ink">{q.title}</h3>
                          {due && (
                            <span className={`mono rounded-full px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-widest ${overdue ? "bg-warn/15 text-warn" : "bg-sky text-deep"}`}>
                              {due}
                            </span>
                          )}
                          {q.status === "in_progress" && (
                            <span className="mono rounded-full bg-amber/15 px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-widest text-ink2">In progress</span>
                          )}
                        </div>
                        <div className="mono mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted">
                          <span>{[q.module, q.course].filter(Boolean).join(" · ") || "Assessment"}</span>
                        </div>
                        <div className="mono mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted">
                          <span className="inline-flex items-center gap-1.5">
                            <ListIcon />{q.questions} question{q.questions === 1 ? "" : "s"}
                          </span>
                          <span className="inline-flex items-center gap-1.5">
                            <ClockIcon />{q.minutes} min
                          </span>
                          <span>Pass mark {q.pass_pct}%</span>
                        </div>
                      </div>

                      <div className="flex shrink-0 flex-col items-stretch gap-2 sm:items-end">
                        <Link
                          href={`/mcq/${q.attempt_id}`}
                          className="rounded-full bg-trust px-5 py-2.5 text-center text-sm font-semibold text-white transition hover:bg-deep"
                        >
                          {q.status === "in_progress" ? "Resume quiz" : "Start quiz"}
                        </Link>
                        {/* Seeing the mark to beat before you sit it is the point. */}
                        <Link
                          href={`/quizzes/${q.attempt_id}/leaderboard`}
                          className="text-center text-xs font-semibold text-trust hover:underline"
                        >
                          Batch leaderboard →
                        </Link>
                      </div>
                    </QuizCard>
                  );
                })}
              </div>
            </section>
          )}

          {done.length > 0 && (
            <section>
              <div className="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <h2 className="display text-lg text-ink">Completed</h2>
                <span className="mono text-xs text-muted">
                  {done.length} quiz{done.length === 1 ? "" : "zes"}
                </span>
              </div>

              <div className="mt-3 space-y-3">
                {done.map((q) => {
                  const missed = q.status === "expired" && q.score_pct === null;
                  const submitted = satOn(q.submitted_at);

                  return (
                    <QuizCard
                      key={q.attempt_id}
                      rail={missed ? "—" : `${q.score_pct}%`}
                      railTone={missed ? "text-muted" : q.passed ? "text-verify" : "text-warn"}
                      sub={missed ? "missed" : q.passed ? "passed" : "not passed"}
                      accent={missed ? "border-line" : q.passed ? "border-verify" : "border-warn"}
                    >
                      <div className="min-w-48 flex-1">
                        <h3 className="font-semibold text-ink">{q.title}</h3>
                        <div className="mono mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted">
                          <span>{q.module ?? q.course ?? "Assessment"}</span>
                          {/* A bare score means nothing without the bar it had to clear. */}
                          <span>· Pass mark {q.pass_pct}%</span>
                          {submitted && <span>· Sat {submitted}</span>}
                        </div>
                      </div>

                      <Link
                        href={`/quizzes/${q.attempt_id}/leaderboard`}
                        className="shrink-0 rounded-full bg-sky px-4 py-2.5 text-xs font-semibold text-deep transition hover:bg-line"
                      >
                        Batch leaderboard →
                      </Link>
                    </QuizCard>
                  );
                })}
              </div>
            </section>
          )}
        </div>
      )}
    </div>
  );
}
