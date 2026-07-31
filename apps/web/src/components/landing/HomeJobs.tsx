"use client";

import Link from "next/link";
import { useEffect, useState } from "react";

/**
 * Live openings on the home page (PRD-E F10).
 *
 * Both segments are shown to signed-out visitors, because the whole point
 * of the board is that one of them behaves differently: roles hiring
 * through BrowseJobs are applied to here by sitting that job's mock, and
 * market roles are handed off. Every card routes to registration, which is
 * where the free mocks and CV credits are granted.
 *
 * Fetched client-side and rendered only when it has something to show: an
 * empty jobs strip on the landing page is worse than no strip at all.
 */

type Internal = {
  id: number;
  title: string;
  company: string | null;
  locations: string[];
  remote: boolean;
  skills: string[];
};

type External = {
  id: number;
  title: string;
  company: string;
  location: string | null;
  skills: string[];
};

type Board = { internal: Internal[]; external: External[]; counts: { internal: number; external: number } };

export function HomeJobs() {
  const [board, setBoard] = useState<Board | null>(null);

  useEffect(() => {
    const base = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000";
    fetch(`${base}/api/v1/job-board`)
      .then((r) => (r.ok ? r.json() : null))
      .then((b) => setBoard(b?.data ?? null))
      .catch(() => setBoard(null));
  }, []);

  if (!board || (board.internal.length === 0 && board.external.length === 0)) {
    return null;
  }

  const internal = board.internal.slice(0, 3);
  const external = board.external.slice(0, 3);

  return (
    <section id="jobs" className="mx-auto max-w-6xl px-6 py-32">
      <p className="mono text-[11px] uppercase tracking-[0.3em] text-trust">Open roles</p>
      <h2 className="display mt-3 max-w-3xl text-4xl text-ink md:text-5xl">
        Two kinds of opening.
      </h2>
      <p className="mt-4 max-w-2xl text-muted">
        Companies hiring <span className="text-ink">directly through BrowseJobs</span> let you apply
        by taking that job&apos;s mock interview — they see a scored interview instead of a CV.
        Everything else is aggregated from the wider market, and we prepare you for it.
      </p>

      {internal.length > 0 && (
        <>
          <div className="mt-12 flex flex-wrap items-baseline justify-between gap-2">
            <h3 className="display text-xl text-ink">Hiring on BrowseJobs</h3>
            <span className="mono text-[11px] uppercase tracking-widest text-verify">
              Apply with an interview
            </span>
          </div>
          <div className="mt-4 grid gap-4 md:grid-cols-3">
            {internal.map((job) => (
              <div
                key={job.id}
                className="flex flex-col rounded-[14px] border border-trust/25 bg-sky/40 p-5"
              >
                <p className="font-semibold text-ink">{job.title}</p>
                <p className="mono mt-1 text-[11px] uppercase tracking-widest text-muted">
                  {[job.company, job.remote ? "Remote" : job.locations[0]].filter(Boolean).join(" · ")}
                </p>
                {job.skills.length > 0 && (
                  <div className="mt-3 flex flex-wrap gap-1.5">
                    {job.skills.slice(0, 4).map((s) => (
                      <span key={s} className="mono rounded-full bg-white px-2 py-0.5 text-[10px] text-ink-2">
                        {s}
                      </span>
                    ))}
                  </div>
                )}
                <div className="mt-auto pt-4">
                  <Link
                    href="/register?intent=apply"
                    className="inline-block rounded-full bg-trust px-4 py-2 text-xs font-semibold text-white transition-colors hover:bg-deep"
                  >
                    Apply with a mock →
                  </Link>
                </div>
              </div>
            ))}
          </div>
        </>
      )}

      {external.length > 0 && (
        <>
          <div className="mt-12 flex flex-wrap items-baseline justify-between gap-2">
            <h3 className="display text-xl text-ink">From the wider market</h3>
            <span className="mono text-[11px] uppercase tracking-widest text-muted">
              You apply on their site
            </span>
          </div>
          <div className="mt-4 grid gap-4 md:grid-cols-3">
            {external.map((job) => (
              <div key={job.id} className="flex flex-col rounded-[14px] border border-line bg-white p-5">
                <p className="font-semibold text-ink">{job.title}</p>
                <p className="mono mt-1 text-[11px] uppercase tracking-widest text-muted">
                  {[job.company, job.location].filter(Boolean).join(" · ")}
                </p>
                {job.skills.length > 0 && (
                  <div className="mt-3 flex flex-wrap gap-1.5">
                    {job.skills.slice(0, 4).map((s) => (
                      <span key={s} className="mono rounded-full bg-paper px-2 py-0.5 text-[10px] text-ink-2">
                        {s}
                      </span>
                    ))}
                  </div>
                )}
                <div className="mt-auto pt-4">
                  <Link
                    href="/register?intent=prep"
                    className="inline-block rounded-full border border-line px-4 py-2 text-xs font-semibold text-trust transition-colors hover:border-trust"
                  >
                    See my match &amp; prepare →
                  </Link>
                </div>
              </div>
            ))}
          </div>
        </>
      )}

      <div className="mt-10 flex flex-wrap items-center gap-4">
        <Link
          href="/jobs"
          className="rounded-full bg-ink px-6 py-3 text-sm font-semibold text-white transition-opacity hover:opacity-90"
        >
          See all {board.counts.internal + board.counts.external} open roles
        </Link>
        <span className="text-sm text-muted">
          A free account includes your first mocks and a JD-tailored CV.
        </span>
      </div>
    </section>
  );
}
