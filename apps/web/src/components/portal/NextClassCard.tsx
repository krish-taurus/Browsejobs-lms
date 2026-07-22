"use client";

import { useState } from "react";
import Link from "next/link";
import { useQuery } from "@tanstack/react-query";
import { ApiError, apiJson } from "@/lib/api";

/**
 * Dashboard "next class" widget: the single soonest upcoming live class, with a
 * gated one-tap join. Shares the /me/classes query cache with the schedule page.
 * Loudest when a class is live now or starting within the hour. Hidden when the
 * student has no upcoming class.
 */

type LiveClass = {
  id: number;
  title: string;
  batch: string | null;
  topic: string | null;
  scheduled_start: string | null;
  status: string;
};

const TZ = "Asia/Kolkata";

function dayKey(iso: string): string {
  return new Intl.DateTimeFormat("en-CA", { timeZone: TZ, year: "numeric", month: "2-digit", day: "numeric" }).format(new Date(iso));
}
function timeLabel(iso: string): string {
  return new Intl.DateTimeFormat("en-IN", { timeZone: TZ, hour: "numeric", minute: "2-digit", hour12: true }).format(new Date(iso));
}
function whenLabel(iso: string): string {
  const key = dayKey(iso);
  const now = new Date();
  const todayKey = dayKey(now.toISOString());
  const tomorrowKey = dayKey(new Date(now.getTime() + 86_400_000).toISOString());
  const day =
    key === todayKey ? "Today"
    : key === tomorrowKey ? "Tomorrow"
    : new Intl.DateTimeFormat("en-IN", { timeZone: TZ, weekday: "short", day: "numeric", month: "short" }).format(new Date(iso));
  return `${day} · ${timeLabel(iso)}`;
}

export function NextClassCard() {
  const [joining, setJoining] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const { data } = useQuery({
    queryKey: ["me", "classes"],
    queryFn: () => apiJson<{ data: LiveClass[] }>("/api/v1/me/classes"),
  });

  const next = (data?.data ?? [])
    .filter((c) => (c.status === "scheduled" || c.status === "live") && c.scheduled_start)
    .sort((a, b) => (a.scheduled_start! < b.scheduled_start! ? -1 : 1))[0];

  if (!next) return null;

  const isLive = next.status === "live";
  const startsSoon =
    !isLive && new Date(next.scheduled_start!).getTime() - Date.now() <= 60 * 60 * 1000;
  const loud = isLive || startsSoon;

  async function join() {
    if (!next) return;
    setError(null);
    setJoining(true);
    try {
      const r = await apiJson<{ data: { join_url: string } }>(`/api/v1/me/classes/${next.id}/join`, { method: "POST" });
      window.open(r.data.join_url, "_blank", "noopener");
    } catch (err) {
      setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Could not join the class.");
    } finally {
      setJoining(false);
    }
  }

  return (
    <div
      className={`mt-6 rounded-[14px] border p-5 shadow-soft transition-colors ${
        loud ? "border-trust/50 bg-sky" : "border-line bg-white"
      }`}
    >
      <div className="flex items-center justify-between">
        <span className="mono text-[10px] font-semibold uppercase tracking-[0.18em] text-trust">
          {isLive ? "Live now" : startsSoon ? "Starting soon" : "Next class"}
        </span>
        {isLive && <span className="flex h-2.5 w-2.5 rounded-full bg-verify" aria-hidden />}
      </div>

      <div className="mt-2 flex flex-wrap items-end justify-between gap-3">
        <div className="min-w-0">
          <p className="font-semibold text-ink">{next.title}</p>
          <p className="mono mt-0.5 text-xs text-muted">
            {next.batch}{next.topic ? ` · ${next.topic}` : ""}
          </p>
          <p className="mono mt-1 text-sm font-semibold text-ink">{whenLabel(next.scheduled_start!)} IST</p>
        </div>

        <div className="flex items-center gap-3">
          <Link href="/classes" className="text-sm font-semibold text-trust hover:underline">
            Schedule →
          </Link>
          <button
            onClick={join}
            disabled={joining}
            className="rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white transition-colors hover:bg-deep disabled:opacity-50"
          >
            {joining ? "Opening…" : "Join"}
          </button>
        </div>
      </div>

      {error && <p className="mt-3 rounded-[10px] bg-warn/10 px-3 py-2 text-sm text-warn">{error}</p>}
    </div>
  );
}
