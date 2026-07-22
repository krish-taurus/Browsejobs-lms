"use client";

import Link from "next/link";
import { useQuery } from "@tanstack/react-query";
import { apiJson } from "@/lib/api";

/**
 * "My teaching" — a trainer/mentor's own board: every batch they are involved in
 * (as lead trainer, a per-module trainer, or a mentor), the modules they teach,
 * and their upcoming classes across all batches. Self-scoped by the API.
 */

type TeachingBatch = {
  id: number;
  number: string;
  type: string;
  status: string;
  course: { code: string; name: string } | null;
  roles: string[];
  modules: string[];
  starts_on: string | null;
  ends_on: string | null;
};

type UpcomingClass = {
  id: number;
  title: string;
  batch_number: string | null;
  course_code: string | null;
  topic: string | null;
  scheduled_start: string;
  scheduled_end: string | null;
  you_teach: boolean;
  join_url: string | null;
};

type Board = { batches: TeachingBatch[]; upcoming: UpcomingClass[] };

function ist(iso: string): string {
  return new Date(iso).toLocaleString("en-IN", {
    timeZone: "Asia/Kolkata", weekday: "short", day: "numeric", month: "short", hour: "numeric", minute: "2-digit",
  });
}

const rolePill: Record<string, string> = {
  "Lead trainer": "bg-trust/10 text-trust",
  "Module trainer": "bg-deep/10 text-deep",
  Mentor: "bg-amber/10 text-amber",
};

export default function TeachingPage() {
  const { data, isLoading } = useQuery({
    queryKey: ["admin", "my-teaching"],
    queryFn: () => apiJson<{ data: Board }>("/api/v1/admin/my-teaching"),
  });

  if (isLoading) {
    return <div className="shimmer h-40 w-full rounded-[14px]" />;
  }

  const board = data?.data ?? { batches: [], upcoming: [] };

  return (
    <div className="mx-auto max-w-5xl">
      <p className="mono text-[11px] uppercase tracking-widest text-trust">My teaching</p>
      <h1 className="display mt-1 text-2xl text-ink">Your batches &amp; classes</h1>
      <p className="mt-1 text-sm text-muted">
        Everything you are allocated to — as a lead trainer, a module trainer, or a mentor — in one place.
        Scheduling changes are handled by an admin; you are notified of any change automatically.
      </p>

      {board.batches.length === 0 ? (
        <div className="mt-8 rounded-[14px] border border-dashed border-line bg-paper p-8 text-center">
          <p className="text-sm text-muted">
            You have no batch allocations yet. An admin allocates trainers and mentors to batches
            under <span className="font-medium text-ink">Batches → Trainers &amp; mentors</span>.
          </p>
        </div>
      ) : (
        <>
          <section className="mt-8">
            <h2 className="display text-lg text-ink">Batches</h2>
            <div className="mt-3 grid gap-3 sm:grid-cols-2">
              {board.batches.map((b) => (
                <Link
                  key={b.id}
                  href={`/admin/batches/${b.id}`}
                  className="block rounded-[14px] border border-line bg-white p-5 transition-shadow hover:shadow-[0_10px_40px_rgba(10,18,32,.10)]"
                >
                  <div className="flex items-center justify-between gap-2">
                    <span className="mono text-sm font-semibold text-ink">{b.number}</span>
                    <span className="mono text-[10px] uppercase tracking-widest text-muted">{b.type}</span>
                  </div>
                  <p className="mt-1 text-sm text-muted">{b.course?.name ?? "—"}</p>
                  <div className="mt-3 flex flex-wrap gap-1.5">
                    {b.roles.map((r) => (
                      <span key={r} className={`rounded-full px-2.5 py-0.5 text-[11px] font-semibold ${rolePill[r] ?? "bg-line/40 text-ink"}`}>
                        {r}
                      </span>
                    ))}
                  </div>
                  {b.modules.length > 0 && (
                    <p className="mt-2 text-xs text-muted">
                      Modules you teach: <span className="text-ink">{b.modules.join(", ")}</span>
                    </p>
                  )}
                </Link>
              ))}
            </div>
          </section>

          <section className="mt-10">
            <h2 className="display text-lg text-ink">Upcoming classes</h2>
            {board.upcoming.length === 0 ? (
              <p className="mt-2 text-sm text-muted">No scheduled classes coming up in your batches.</p>
            ) : (
              <div className="mt-3 divide-y divide-line rounded-[14px] border border-line bg-white">
                {board.upcoming.map((c) => (
                  <div key={c.id} className="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                    <div className="min-w-0">
                      <div className="flex items-center gap-2">
                        <span className="text-sm font-medium text-ink">{c.title}</span>
                        {c.you_teach ? (
                          <span className="rounded-full bg-verify/10 px-2 py-0.5 text-[10px] font-semibold text-verify">You teach</span>
                        ) : (
                          <span className="rounded-full bg-line/40 px-2 py-0.5 text-[10px] font-semibold text-muted">Cover / mentor</span>
                        )}
                      </div>
                      <p className="mono mt-0.5 text-xs text-muted">
                        {c.batch_number}{c.topic ? ` · ${c.topic}` : ""} · {ist(c.scheduled_start)} IST
                      </p>
                    </div>
                    {c.join_url && (
                      <a
                        href={c.join_url}
                        target="_blank"
                        rel="noreferrer"
                        className="rounded-full border border-line px-4 py-1.5 text-xs font-semibold text-ink transition-colors hover:bg-paper"
                      >
                        Join
                      </a>
                    )}
                  </div>
                ))}
              </div>
            )}
          </section>
        </>
      )}
    </div>
  );
}
