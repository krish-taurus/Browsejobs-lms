"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { motion, useReducedMotion } from "framer-motion";
import { durations, ease, staggerContainer, revealVariants } from "@/lib/motion";
import { MonoCounter } from "@/components/motion/MonoCounter";
import { useWorkspace } from "@/components/employer/EmployerShell";
import { employerApi, STAGE_ORDER, STAGE_LABELS, type DashboardData } from "@/lib/employer";

/** Blue-scale for pipeline segments: deep → trust → sky (spec §5.3). */
const SEGMENT_CLASSES = [
  "bg-deep", "bg-trust", "bg-trust/80", "bg-trust/60", "bg-trust/45", "bg-sky", "bg-verify/70", "bg-verify",
];

export default function EmployerDashboardPage() {
  const { workspace } = useWorkspace();
  const reduce = useReducedMotion();
  const [data, setData] = useState<DashboardData | null>(null);
  const [error, setError] = useState(false);

  useEffect(() => {
    setData(null);
    setError(false);
    employerApi.dashboard(workspace.id)
      .then((res) => setData(res.data))
      .catch(() => setError(true));
  }, [workspace.id]);

  if (error) {
    return (
      <p className="rounded-card border border-line bg-white p-6 text-sm text-muted shadow-soft">
        The dashboard could not load. Refresh to try again.
      </p>
    );
  }

  if (!data) {
    return (
      <div className="space-y-4">
        <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
          {[0, 1, 2, 3].map((i) => <div key={i} className="shimmer h-24 rounded-card" />)}
        </div>
        <div className="shimmer h-32 rounded-card" />
      </div>
    );
  }

  const topJob = [...data.pipeline].sort((a, b) => b.awaiting_review - a.awaiting_review)[0];

  return (
    <motion.div variants={staggerContainer} initial={reduce ? false : "hidden"} animate="show" className="space-y-6">
      <motion.div variants={revealVariants}>
        <p className="mono text-[11px] uppercase tracking-widest text-trust">Command centre</p>
        <h1 className="display mt-1 text-2xl text-ink">{workspace.name}</h1>
      </motion.div>

      {/* 4-up mono stat band (spec §5.2) */}
      <motion.div variants={revealVariants} className="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <Stat label="Active JDs" value={data.active_jobs} />
        <Stat label="Graded applicants · 7d" value={data.graded_last_7d} />
        <Stat label="Awaiting your review" value={data.awaiting_review} accent />
        <Stat label="Offers open" value={data.offers_open} />
      </motion.div>

      {/* Next Best Action — the loudest element (spec §5.2) */}
      <motion.div variants={revealVariants}>
        {topJob && topJob.awaiting_review > 0 ? (
          <div className="flex flex-col justify-between gap-4 rounded-panel bg-ink p-6 text-white shadow-soft sm:flex-row sm:items-center">
            <div>
              <p className="mono text-[10px] uppercase tracking-widest text-sky/60">Next best action</p>
              <p className="mt-1 text-lg font-semibold">
                <span className="mono">{topJob.awaiting_review}</span> graded applicant{topJob.awaiting_review === 1 ? "" : "s"} waiting on{" "}
                <span className="text-sky">{topJob.title}</span>
              </p>
            </div>
            <Link
              href={`/employer/jobs/${topJob.id}`}
              className="rounded-input bg-trust px-5 py-2.5 text-center text-sm font-semibold text-white"
            >
              Review now
            </Link>
          </div>
        ) : (
          <div className="rounded-panel border border-line bg-white p-6 shadow-soft">
            <p className="mono text-[10px] uppercase tracking-widest text-muted">Next best action</p>
            <p className="mt-1 text-sm text-ink">
              {data.active_jobs === 0
                ? "Post your first JD — a JD-specific mock interview is generated for it automatically."
                : "All caught up. New graded applicants will appear here the moment they finish their mock."}
            </p>
            {data.active_jobs === 0 && (
              <Link href="/employer/jobs/new" className="mt-3 inline-block rounded-input bg-trust px-5 py-2.5 text-sm font-semibold text-white">
                Post a JD
              </Link>
            )}
          </div>
        )}
      </motion.div>

      {/* Pipeline pulse per active JD (spec §5.2) */}
      <motion.div variants={revealVariants} className="rounded-panel border border-line bg-white p-6 shadow-soft">
        <div className="flex items-baseline justify-between">
          <h2 className="display text-lg text-ink">Pipeline pulse</h2>
          <p className="mono text-[10px] uppercase tracking-widest text-muted">
            {data.total_applications} applications · {data.graded_applications} graded
          </p>
        </div>

        {data.pipeline.length === 0 ? (
          <p className="mt-4 text-sm text-muted">
            No published JDs yet.{" "}
            <Link className="font-medium text-trust" href="/employer/jobs/new">Post one</Link>{" "}
            and pre-interviewed applicants will start flowing in.
          </p>
        ) : (
          <ul className="mt-4 space-y-4">
            {data.pipeline.map((job) => {
              const total = Object.values(job.stage_counts).reduce((a, b) => a + b, 0);
              return (
                <li key={job.id}>
                  <Link href={`/employer/jobs/${job.id}`} className="group block">
                    <div className="flex items-baseline justify-between">
                      <p className="text-sm font-semibold text-ink group-hover:text-trust">{job.title}</p>
                      <p className="mono text-xs text-muted">{total} in pipeline</p>
                    </div>
                    <div className="mt-2 flex h-2.5 w-full gap-0.5 overflow-hidden rounded-full bg-paper">
                      {total > 0 &&
                        STAGE_ORDER.map((stage, i) => {
                          const count = job.stage_counts[stage] ?? 0;
                          if (count === 0) return null;
                          return (
                            <motion.div
                              key={stage}
                              title={`${STAGE_LABELS[stage]}: ${count}`}
                              initial={reduce ? false : { scaleX: 0 }}
                              animate={{ scaleX: 1 }}
                              transition={{ duration: durations.slower, ease }}
                              style={{ width: `${(count / total) * 100}%`, transformOrigin: "left" }}
                              className={SEGMENT_CLASSES[i] ?? "bg-sky"}
                            />
                          );
                        })}
                    </div>
                  </Link>
                </li>
              );
            })}
          </ul>
        )}
      </motion.div>

      <motion.p variants={revealVariants} className="mono text-[10px] leading-relaxed text-muted">
        Counts reflect this workspace&apos;s live pipeline. Interview grading normally completes within the hour;
        delayed gradings are shown honestly as pending — scores are never fabricated.
      </motion.p>
    </motion.div>
  );
}

function Stat({ label, value, accent = false }: { label: string; value: number; accent?: boolean }) {
  return (
    <div className={`rounded-card border p-5 shadow-soft ${accent && value > 0 ? "border-trust bg-sky" : "border-line bg-white"}`}>
      <MonoCounter value={value} className="mono text-3xl font-semibold text-ink" />
      <p className="mt-1 text-xs text-muted">{label}</p>
    </div>
  );
}
