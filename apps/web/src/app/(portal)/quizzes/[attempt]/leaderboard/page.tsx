"use client";

import Link from "next/link";
import { use, useEffect, useState } from "react";
import { ApiError, apiJson } from "@/lib/api";
import { EmptyState } from "@/components/ui/EmptyState";

type Row = {
  rank: number;
  name: string;
  score_pct: number | null;
  correct_count: number | null;
  total_count: number | null;
  is_me: boolean;
};

type Board = {
  quiz: string | null;
  pass_pct: number | null;
  batch: string | null;
  participants: number;
  top: Row[];
  my_status: "pending" | "in_progress" | "submitted" | "expired";
  best_score: number | null;
  me: {
    rank: number;
    score_pct: number | null;
    passed: boolean;
    to_next: number;
    coach_line: string;
  } | null;
};

export default function QuizLeaderboardPage({ params }: { params: Promise<{ attempt: string }> }) {
  const { attempt } = use(params);
  const [board, setBoard] = useState<Board | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    apiJson<{ data: Board }>(`/api/v1/me/quizzes/${attempt}/leaderboard`)
      .then((r) => setBoard(r.data))
      .catch((err) =>
        setError(err instanceof ApiError ? (err.firstError ?? err.message) : "This leaderboard could not be loaded."),
      );
  }, [attempt]);

  if (error) {
    return (
      <div className="mx-auto max-w-2xl">
        <p className="text-sm text-muted">{error}</p>
        <Link href="/quizzes" className="mt-4 inline-block text-sm font-semibold text-trust">← Back to my quizzes</Link>
      </div>
    );
  }

  if (!board) return <div className="mx-auto max-w-2xl"><div className="shimmer h-64 rounded-[14px]" /></div>;

  const podium = board.top.slice(0, 3);
  const notSatYet = board.my_status === "pending" || board.my_status === "in_progress";

  return (
    <div className="mx-auto max-w-2xl">
      <Link href="/quizzes" className="text-sm font-semibold text-trust">← My quizzes</Link>
      <p className="kicker mt-4 text-trust">Batch leaderboard</p>
      <h1 className="display mt-1 text-2xl text-ink">{board.quiz ?? "Quiz"}</h1>
      <p className="mt-2 text-sm text-muted">
        {board.batch ? `Batch ${board.batch}` : "Your batch"} · {board.participants} finished
        {board.pass_pct !== null && ` · pass ${board.pass_pct}%`}
      </p>

      {/* Still to sit it: the board is the reason to go and do it. */}
      {notSatYet && (
        <div className="mt-5 rounded-2xl border border-trust/30 bg-sky/40 p-5">
          <p className="text-sm font-semibold text-ink">
            {board.best_score !== null
              ? `Your batch's best so far is ${board.best_score}%. Beat it.`
              : "Nobody in your batch has finished this yet — set the mark."}
          </p>
          <Link
            href={`/mcq/${attempt}`}
            className="mt-3 inline-block rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white transition-transform hover:-translate-y-0.5"
          >
            {board.my_status === "in_progress" ? "Resume quiz" : "Start quiz"}
          </Link>
        </div>
      )}

      {/* Sat it: one line on the gap that is actually closable. */}
      {board.me && (
        <div className="mt-5 rounded-2xl border border-line bg-white p-5">
          <div className="flex flex-wrap items-baseline gap-x-3">
            <span className="mono text-3xl font-bold text-ink">#{board.me.rank}</span>
            <span className={`mono text-sm font-semibold ${board.me.passed ? "text-verify" : "text-warn"}`}>
              {board.me.score_pct}%
            </span>
            <span className="text-xs text-muted">of {board.participants} finished</span>
          </div>
          <p className="mt-2 text-sm text-muted">{board.me.coach_line}</p>
        </div>
      )}

      {board.top.length === 0 ? (
        // The Beat-it card above already says this when the quiz is still open.
        notSatYet ? null : (
          <div className="mt-6">
            <EmptyState
              title="Nobody has finished yet"
              body="As your batch mates submit this quiz, their scores appear here. Only your own batch is ranked."
            />
          </div>
        )
      ) : (
        <>
          {podium.length > 1 && (
            <div className="mt-6 grid gap-3 sm:grid-cols-3">
              {podium.map((row) => (
                <div
                  key={row.rank}
                  className={`rounded-[14px] border p-4 ${row.is_me ? "border-trust bg-sky/40" : "border-line bg-white"}`}
                >
                  <span className="mono text-[10px] uppercase tracking-widest text-muted">#{row.rank}</span>
                  <p className="mt-1 truncate font-semibold text-ink">{row.name}</p>
                  <p className="mono mt-1 text-2xl font-bold text-ink">{row.score_pct}%</p>
                  {row.total_count !== null && (
                    <p className="text-xs text-muted">{row.correct_count}/{row.total_count} correct</p>
                  )}
                </div>
              ))}
            </div>
          )}

          <div className="mt-4 divide-y divide-line rounded-[14px] border border-line bg-white">
            {board.top.map((row) => (
              <div
                key={row.rank}
                className={`flex items-center gap-3 px-5 py-3 ${row.is_me ? "bg-sky/40" : ""}`}
              >
                <span className="mono w-6 text-sm text-muted">{row.rank}</span>
                <span className="flex-1 truncate text-sm text-ink">
                  {row.name}
                  {row.is_me && <span className="mono ml-2 text-[10px] uppercase tracking-widest text-trust">You</span>}
                </span>
                {row.total_count !== null && (
                  <span className="hidden text-xs text-muted sm:inline">{row.correct_count}/{row.total_count}</span>
                )}
                <span className="mono text-sm font-semibold text-ink">{row.score_pct}%</span>
              </div>
            ))}
          </div>
        </>
      )}

      {board.me === null && board.my_status === "expired" && (
        <p className="mt-4 text-sm text-muted">
          You did not submit this one, so you are not ranked. Watch the next quiz land on your
          dashboard and sit it early.
        </p>
      )}
    </div>
  );
}
