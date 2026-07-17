"use client";

import { useCallback, useEffect, useState } from "react";
import { apiJson } from "@/lib/api";

type LeaderboardRow = { rank: number; name: string; points: number; is_me: boolean };
type Badge = { key: string; label: string; awarded_at: string };
type LeaderboardData = {
  batch: string | null;
  period: "weekly" | "alltime";
  top: LeaderboardRow[];
  me: { rank: number; points: number; to_next: number; coach_line: string } | null;
  badges: Badge[];
  opt_out: boolean;
  participants: number;
};

/**
 * Batch leaderboard (PRD §6.16). Top 10 named (opt-out respected); everyone
 * else sees their own rank + distance to the next — motivating, never
 * humiliating.
 */
export function LeaderboardCard() {
  const [period, setPeriod] = useState<"weekly" | "alltime">("weekly");
  const [data, setData] = useState<LeaderboardData | null>(null);
  const [loading, setLoading] = useState(true);

  const load = useCallback(async (p: "weekly" | "alltime") => {
    setLoading(true);
    try {
      const res = await apiJson<{ data: LeaderboardData }>(`/api/v1/me/leaderboard?period=${p}`);
      setData(res.data);
    } catch {
      setData(null);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load(period);
  }, [period, load]);

  async function toggleOptOut() {
    if (!data) return;
    const next = !data.opt_out;
    setData({ ...data, opt_out: next });
    await apiJson("/api/v1/me/leaderboard", {
      method: "PUT",
      body: JSON.stringify({ opt_out: next }),
    }).catch(() => setData({ ...data, opt_out: !next }));
    void load(period);
  }

  if (loading && !data) {
    return <div className="shimmer mt-6 h-48 rounded-2xl" />;
  }

  if (!data || data.batch === null) {
    return null; // No active batch yet — the card stays out of the way.
  }

  return (
    <section className="mt-6 rounded-2xl border border-line bg-white p-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <p className="kicker text-trust">Batch leaderboard</p>
          <p className="mono mt-1 text-xs text-muted">
            {data.batch} · {data.participants} on the board
          </p>
        </div>
        <div className="inline-flex rounded-full border border-line bg-paper p-1">
          {(["weekly", "alltime"] as const).map((p) => (
            <button
              key={p}
              onClick={() => setPeriod(p)}
              className={`rounded-full px-3.5 py-1 text-xs font-semibold transition-colors ${
                period === p ? "bg-ink text-white" : "text-muted"
              }`}
            >
              {p === "weekly" ? "This week" : "All-time"}
            </button>
          ))}
        </div>
      </div>

      {/* Coach line — the nudge that makes the number actionable. */}
      {data.me && (
        <div className="mt-4 rounded-[10px] bg-sky px-4 py-3">
          <p className="text-sm text-deep">
            <span className="mono font-semibold">#{data.me.rank}</span> ·{" "}
            {data.me.coach_line}
          </p>
        </div>
      )}

      <ol className="mt-4 divide-y divide-line">
        {data.top.map((row) => (
          <li
            key={row.rank}
            className={`flex items-center gap-3 py-2.5 ${row.is_me ? "font-semibold" : ""}`}
          >
            <span
              className={`mono w-8 text-sm ${row.rank <= 3 ? "text-amber" : "text-muted"}`}
            >
              #{row.rank}
            </span>
            <span className={`flex-1 text-sm ${row.is_me ? "text-trust" : "text-ink"}`}>
              {row.name}
              {row.is_me && <span className="mono ml-1.5 text-[10px] uppercase tracking-widest text-muted">you</span>}
            </span>
            <span className="mono text-sm text-ink">{row.points}</span>
          </li>
        ))}
        {data.top.length === 0 && (
          <li className="py-6 text-center text-sm text-muted">
            No points yet this period — attend a class or pass a quiz to open the board.
          </li>
        )}
      </ol>

      {data.me && data.me.rank > 10 && (
        <div className="mt-2 flex items-center gap-3 border-t border-line pt-2.5 font-semibold">
          <span className="mono w-8 text-sm text-muted">#{data.me.rank}</span>
          <span className="flex-1 text-sm text-trust">
            You
            <span className="mono ml-2 text-[10px] uppercase tracking-widest text-muted">
              {data.me.to_next} pts to next rank
            </span>
          </span>
          <span className="mono text-sm text-ink">{data.me.points}</span>
        </div>
      )}

      {data.badges.length > 0 && (
        <div className="mt-4 flex flex-wrap gap-2">
          {data.badges.map((b) => (
            <span
              key={b.key}
              className="mono rounded-full bg-verify-bg px-3 py-1 text-[11px] font-semibold text-verify"
              title={`Earned ${b.awarded_at}`}
            >
              ★ {b.label}
            </span>
          ))}
        </div>
      )}

      <label className="mt-4 flex items-center justify-between gap-3 border-t border-line pt-3 text-xs text-muted">
        <span>Show my name on the board</span>
        <input
          type="checkbox"
          checked={!data.opt_out}
          onChange={() => void toggleOptOut()}
          className="h-4 w-4 accent-[var(--bj-trust)]"
        />
      </label>
    </section>
  );
}
