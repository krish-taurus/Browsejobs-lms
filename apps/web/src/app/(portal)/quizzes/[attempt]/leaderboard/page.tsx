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

const clamp = (n: number) => Math.max(0, Math.min(100, n));

/** Did this row clear the bar? The board only carries `passed` for the viewer. */
const cleared = (score: number | null, pass: number | null) =>
  score === null || pass === null ? null : score >= pass;

const initial = (name: string) => (name.trim()[0] ?? "?").toUpperCase();

/** Rank chip. The top three read as a podium tier; everyone else stays quiet. */
function Medal({ rank }: { rank: number }) {
  const tone =
    rank === 1 ? "bg-amber text-ink"
      : rank <= 3 ? "bg-amber/25 text-ink2"
        : "text-muted";

  return (
    <span className={`mono flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-bold ${tone}`}>
      {rank}
    </span>
  );
}

/** Marks / Percentage / Result — the three numbers a student compares on. */
function ResultTag({ ok }: { ok: boolean | null }) {
  if (ok === null) return <span className="mono text-sm font-bold text-muted">—</span>;

  return (
    <span className={`mono text-sm font-bold ${ok ? "text-verify" : "text-warn"}`}>
      {ok ? "Pass" : "Fail"}
    </span>
  );
}

function Stat({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div>
      <p className="mono text-[10px] uppercase tracking-widest text-muted">{label}</p>
      <p className="mono mt-1 text-sm font-bold text-ink">{children}</p>
    </div>
  );
}

/** Header meta chip. */
function Meta({ label }: { label: string }) {
  return <span className="mono rounded-full border border-line px-2.5 py-1 text-[11px] text-muted">{label}</span>;
}

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
      <div className="mx-auto max-w-4xl">
        <p className="text-sm text-muted">{error}</p>
        <Link href="/quizzes" className="mt-4 inline-block text-sm font-semibold text-trust">← Back to my quizzes</Link>
      </div>
    );
  }

  if (!board) return <div className="mx-auto max-w-4xl"><div className="shimmer h-64 rounded-[14px]" /></div>;

  const podium = board.top.slice(0, 3);
  const notSatYet = board.my_status === "pending" || board.my_status === "in_progress";
  const myRow = board.top.find((r) => r.is_me) ?? null;
  // Grid tracks follow how many actually finished — one lonely card in a
  // three-column grid reads as two cards failing to load.
  const podiumCols = podium.length >= 3 ? "sm:grid-cols-3" : podium.length === 2 ? "sm:grid-cols-2" : "";

  return (
    <div className="mx-auto max-w-4xl">
      <Link href="/quizzes" className="text-sm font-semibold text-trust hover:underline">← My quizzes</Link>
      <p className="kicker mt-4 text-trust">Batch leaderboard</p>
      <h1 className="display mt-1 text-2xl text-ink">{board.quiz ?? "Quiz"}</h1>
      <div className="mt-3 flex flex-wrap items-center gap-2">
        <Meta label={board.batch ? `Batch ${board.batch}` : "Your batch"} />
        <Meta label={`${board.participants} finished`} />
        {board.pass_pct !== null && <Meta label={`Pass mark ${board.pass_pct}%`} />}
      </div>

      {/* Still to sit it: the board is the reason to go and do it. */}
      {notSatYet && (
        <div className="mt-6 rounded-[14px] border border-trust/30 bg-sky/40 p-5">
          <p className="text-sm font-semibold text-ink">
            {board.best_score !== null
              ? `Your batch's best so far is ${board.best_score}%. Beat it.`
              : "Nobody in your batch has finished this yet — set the mark."}
          </p>
          <Link
            href={`/mcq/${attempt}`}
            className="mt-3 inline-block rounded-full bg-trust px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-deep"
          >
            {board.my_status === "in_progress" ? "Resume quiz" : "Start quiz"}
          </Link>
        </div>
      )}

      {podium.length > 0 && (
        <div className={`mt-6 grid gap-3 ${podiumCols}`}>
          {podium.map((row) => (
            <div
              key={row.rank}
              className={`rounded-[14px] border bg-gradient-to-br p-5 ${
                row.is_me
                  ? "border-trust/40 from-sky via-sky/50 to-surface"
                  : "border-amber/30 from-amber/25 via-amber/10 to-surface"
              }`}
            >
              <div className="flex items-center gap-3">
                <Medal rank={row.rank} />
                <p className="truncate font-semibold text-ink">
                  {row.name}
                  {row.is_me && <span className="mono ml-2 text-[10px] uppercase tracking-widest text-trust">You</span>}
                </p>
              </div>
              <div className="mt-4 grid grid-cols-3 gap-2">
                <Stat label="Marks">
                  {row.total_count !== null ? <>{row.correct_count}<span className="text-muted">/{row.total_count}</span></> : "—"}
                </Stat>
                <Stat label="Percentage">{row.score_pct}%</Stat>
                <div>
                  <p className="mono text-[10px] uppercase tracking-widest text-muted">Result</p>
                  <p className="mt-1"><ResultTag ok={cleared(row.score_pct, board.pass_pct)} /></p>
                </div>
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Your own line, pinned — in a big batch it would otherwise be far down
          the list, which is exactly when a student most wants to find it. */}
      {board.me && (
        <div className="mt-4 rounded-[14px] border border-trust/30 bg-gradient-to-r from-sky via-sky/60 to-surface p-5 shadow-soft">
          <div className="flex flex-wrap items-center gap-x-4 gap-y-3">
            <span className="mono flex size-9 shrink-0 items-center justify-center rounded-full bg-trust text-sm font-bold text-white">
              {initial(myRow?.name ?? "You")}
            </span>
            <div className="min-w-32 flex-1">
              <p className="font-semibold text-ink">
                {myRow?.name ?? "You"}
                <span className="mono ml-2 text-[10px] uppercase tracking-widest text-trust">You</span>
              </p>
              <p className="mono text-xs text-muted">Rank #{board.me.rank} of {board.participants}</p>
            </div>
            <div className="grid shrink-0 grid-cols-3 gap-6">
              <Stat label="Marks">
                {myRow?.total_count != null ? <>{myRow.correct_count}<span className="text-muted">/{myRow.total_count}</span></> : "—"}
              </Stat>
              <Stat label="Percentage">{board.me.score_pct}%</Stat>
              <div>
                <p className="mono text-[10px] uppercase tracking-widest text-muted">Result</p>
                <p className="mt-1"><ResultTag ok={board.me.passed} /></p>
              </div>
            </div>
          </div>

          {/* The gap to the pass mark, drawn — a bare percentage does not show
              whether the student was one question short or nowhere near. */}
          {board.pass_pct !== null && board.me.score_pct !== null && (
            <div className="relative mt-4 h-1.5 w-full rounded-full bg-line">
              <div
                className={`absolute inset-y-0 left-0 rounded-full ${board.me.passed ? "bg-verify" : "bg-warn"}`}
                style={{ width: `${clamp(board.me.score_pct)}%` }}
              />
              <span
                title={`Pass mark ${board.pass_pct}%`}
                className="absolute top-1/2 h-3.5 w-0.5 -translate-x-1/2 -translate-y-1/2 rounded-full bg-ink/50"
                style={{ left: `${clamp(board.pass_pct)}%` }}
              />
            </div>
          )}

          <p className="mt-3 text-sm text-ink2">{board.me.coach_line}</p>
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
        <section className="mt-8">
          <p className="mono text-[11px] uppercase tracking-widest text-muted">Full ranking</p>

          <div className="mt-3 overflow-hidden rounded-[14px] border border-line bg-surface">
            <div className="hidden items-center gap-3 border-b border-line bg-paper px-5 py-2.5 sm:flex">
              <span className="w-7 shrink-0" />
              <span className="mono flex-1 text-[10px] uppercase tracking-widest text-muted">Student</span>
              <span className="mono w-16 text-right text-[10px] uppercase tracking-widest text-muted">Marks</span>
              <span className="mono w-16 text-right text-[10px] uppercase tracking-widest text-muted">Percent</span>
              <span className="mono w-14 text-right text-[10px] uppercase tracking-widest text-muted">Result</span>
            </div>

            <div className="divide-y divide-line">
              {board.top.map((row) => (
                <div
                  key={`${row.rank}-${row.name}`}
                  className={`flex items-center gap-3 border-l-[3px] px-5 py-3.5 ${row.is_me ? "border-trust bg-sky/40" : "border-transparent"}`}
                >
                  <Medal rank={row.rank} />
                  <span className="flex-1 truncate text-sm font-medium text-ink">
                    {row.name}
                    {row.is_me && <span className="mono ml-2 text-[10px] uppercase tracking-widest text-trust">You</span>}
                  </span>
                  <span className="mono w-16 text-right text-sm text-ink">
                    {row.total_count !== null ? <>{row.correct_count}<span className="text-muted">/{row.total_count}</span></> : "—"}
                  </span>
                  <span className="mono w-16 text-right text-sm font-semibold text-ink">{row.score_pct}%</span>
                  <span className="w-14 text-right"><ResultTag ok={cleared(row.score_pct, board.pass_pct)} /></span>
                </div>
              ))}
            </div>
          </div>
        </section>
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
