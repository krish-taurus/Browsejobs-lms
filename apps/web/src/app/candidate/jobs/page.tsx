"use client";

import { useEffect, useState } from "react";
import { AnimatePresence, motion, useReducedMotion } from "framer-motion";
import { StatusChip } from "@/components/viz/Chart";
import {
  Card,
  Deck,
  GhostAction,
  Kicker,
  PageTitle,
  PrimaryAction,
  Shimmer,
} from "@/components/viz/Surface";
import { DUR, EASE, INK, SERIES, SURFACE } from "@/components/viz/tokens";
import { candidateApi, type ExternalJob, type InternalJob, type JobBoard } from "@/lib/candidate";

type Segment = "internal" | "external";

function posted(iso: string | null): string {
  if (!iso) return "recently";
  const days = Math.max(0, Math.floor((Date.now() - new Date(iso).getTime()) / 86_400_000));
  if (days === 0) return "today";
  if (days === 1) return "yesterday";
  return `${days}d ago`;
}

export default function CandidateJobsPage() {
  const [board, setBoard] = useState<JobBoard | null>(null);
  const [segment, setSegment] = useState<Segment>("internal");
  const [term, setTerm] = useState("");
  const [query, setQuery] = useState("");
  const [failed, setFailed] = useState(false);

  useEffect(() => {
    setBoard(null);
    candidateApi
      .board(query || undefined)
      .then((r) => setBoard(r.data))
      .catch(() => setFailed(true));
  }, [query]);

  return (
    <Deck>
      <main className="mx-auto max-w-6xl space-y-6 px-5 py-12 md:py-16">
        <PageTitle
          kicker="Jobs"
          title="Two kinds of opening."
          highlight="Only one lets you skip the pile."
          sub="Employers hiring through BrowseJobs let you apply by taking that job's mock — they see a scored interview instead of a CV. Market roles are aggregated from public postings; we prepare you, then you apply on their site."
        />

        {/* Filters in one row above the results ---------------------- */}
        <div className="flex flex-wrap items-center gap-3">
          <div
            className="inline-flex rounded-full border p-1"
            style={{ borderColor: SURFACE.lineStrong, background: SURFACE.card }}
          >
            {(["internal", "external"] as Segment[]).map((s) => {
              const active = segment === s;
              return (
                <button
                  key={s}
                  onClick={() => setSegment(s)}
                  aria-pressed={active}
                  className="relative rounded-full px-4 py-2 text-[13px] font-semibold transition-colors"
                  style={{ color: active ? "#fff" : INK.muted }}
                >
                  {active && (
                    <motion.span
                      layoutId="segment-pill"
                      transition={{ duration: DUR.fast, ease: EASE }}
                      className="absolute inset-0 rounded-full"
                      style={{
                        background: `linear-gradient(100deg, ${SERIES[0]}, #3f7ce8)`,
                        boxShadow: `0 6px 22px ${SERIES[0]}47`,
                      }}
                    />
                  )}
                  <span className="relative">
                    {s === "internal" ? "Hiring on BrowseJobs" : "Wider market"}
                    {board && (
                      <span className="ml-2 font-mono text-[11px] opacity-70">
                        {board.counts[s]}
                      </span>
                    )}
                  </span>
                </button>
              );
            })}
          </div>

          <form
            onSubmit={(e) => {
              e.preventDefault();
              setQuery(term.trim());
            }}
            className="flex min-w-[220px] flex-1 gap-2"
          >
            <input
              value={term}
              onChange={(e) => setTerm(e.target.value)}
              placeholder="Search role or company"
              aria-label="Search jobs"
              className="w-full rounded-full border px-4 py-2.5 text-sm outline-none transition-shadow focus:ring-4"
              style={{
                background: SURFACE.card,
                borderColor: SURFACE.lineStrong,
                color: INK.primary,
              }}
            />
            <GhostAction onClick={() => setQuery(term.trim())}>Search</GhostAction>
          </form>
        </div>

        {failed ? (
          <Card>
            <p className="text-sm" style={{ color: INK.secondary }}>
              The board could not load. Refresh to try again.
            </p>
          </Card>
        ) : !board ? (
          <div className="grid gap-4 md:grid-cols-2">
            {[0, 1, 2, 3].map((i) => (
              <Shimmer key={i} className="h-48" />
            ))}
          </div>
        ) : (
          <AnimatePresence mode="wait">
            <motion.div
              key={segment}
              initial={{ opacity: 0, y: 10 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: -6 }}
              transition={{ duration: DUR.fast, ease: EASE }}
            >
              {segment === "internal" ? (
                <InternalList jobs={board.internal} />
              ) : (
                <ExternalList jobs={board.external} />
              )}
            </motion.div>
          </AnimatePresence>
        )}
      </main>
    </Deck>
  );
}

function InternalList({ jobs }: { jobs: InternalJob[] }) {
  const reduce = useReducedMotion();

  if (jobs.length === 0) {
    return (
      <Card>
        <p className="text-sm font-semibold">No BrowseJobs employers hiring for that yet.</p>
        <p className="mt-1.5 text-[13px]" style={{ color: INK.muted }}>
          Try the wider market tab — we can still prepare you for those roles.
        </p>
      </Card>
    );
  }

  return (
    <div className="grid gap-4 md:grid-cols-2">
      {jobs.map((job, i) => (
        <motion.article
          key={job.id}
          initial={reduce ? false : { opacity: 0, y: 14 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: DUR.base, ease: EASE, delay: Math.min(i, 8) * 0.05 }}
          whileHover={reduce ? undefined : { y: -3 }}
          className="relative flex flex-col overflow-hidden rounded-2xl border p-5"
          style={{
            background: `linear-gradient(158deg, ${SERIES[0]}12, ${SURFACE.card} 60%)`,
            borderColor: `${SERIES[0]}2e`,
          }}
        >
          <span
            aria-hidden
            className="pointer-events-none absolute inset-x-0 top-0 h-px"
            style={{ background: `linear-gradient(90deg, ${SERIES[0]}, transparent 70%)` }}
          />
          <div className="flex items-start justify-between gap-3">
            <div className="min-w-0">
              <p className="font-display text-lg font-bold leading-tight">{job.title}</p>
              <p className="mt-1 text-[13px]" style={{ color: INK.secondary }}>
                {job.company}
              </p>
            </div>
            <span className="shrink-0 font-mono text-[10px]" style={{ color: INK.faint }}>
              {posted(job.posted_at)}
            </span>
          </div>

          <p className="mt-3 font-mono text-[11px]" style={{ color: INK.muted }}>
            {[
              job.remote ? "Remote" : job.locations.join(", ") || "—",
              job.experience_min_years !== null
                ? `${job.experience_min_years}–${job.experience_max_years ?? "+"} yrs`
                : null,
              job.openings ? `${job.openings} opening${job.openings === 1 ? "" : "s"}` : null,
            ]
              .filter(Boolean)
              .join("  ·  ")}
          </p>

          {job.skills.length > 0 && (
            <div className="mt-3 flex flex-wrap gap-1.5">
              {job.skills.map((s) => (
                <span
                  key={s}
                  className="rounded-full px-2.5 py-1 font-mono text-[10px] uppercase tracking-[0.08em]"
                  style={{ background: `${SERIES[0]}1c`, color: "#9dc2fb" }}
                >
                  {s}
                </span>
              ))}
            </div>
          )}

          <div
            className="mt-4 rounded-xl border px-3.5 py-3"
            style={{ borderColor: SURFACE.line, background: "rgba(255,255,255,0.025)" }}
          >
            <Kicker>How you apply</Kicker>
            <p className="mt-1.5 text-[12px] leading-relaxed" style={{ color: INK.secondary }}>
              {job.mock_ready
                ? "Take this job's mock interview. Your score and transcript go straight to the employer, and we rebuild your CV against this JD."
                : "This role's mock is still being generated. Apply now and take it as soon as it is ready."}
            </p>
          </div>

          <div className="mt-4 flex items-center gap-3 pt-1">
            {job.has_applied ? (
              <>
                <StatusChip tone="good">Applied</StatusChip>
                <GhostAction href={`/candidate/jobs/${job.id}`}>View</GhostAction>
              </>
            ) : (
              <PrimaryAction href={`/candidate/jobs/${job.id}`}>Apply with a mock</PrimaryAction>
            )}
          </div>
        </motion.article>
      ))}
    </div>
  );
}

function ExternalList({ jobs }: { jobs: ExternalJob[] }) {
  const reduce = useReducedMotion();

  if (jobs.length === 0) {
    return (
      <Card>
        <p className="text-sm font-semibold">No market roles matched.</p>
        <p className="mt-1.5 text-[13px]" style={{ color: INK.muted }}>
          The feed refreshes with the morning sync.
        </p>
      </Card>
    );
  }

  return (
    <>
      <p className="mb-4 text-[13px]" style={{ color: INK.muted }}>
        Aggregated from public postings. Listings belong to the hiring companies — you apply on
        their site, and we prepare you for the interview.
      </p>
      <div className="grid gap-4 md:grid-cols-2">
        {jobs.map((job, i) => (
          <motion.article
            key={job.id}
            initial={reduce ? false : { opacity: 0, y: 14 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: DUR.base, ease: EASE, delay: Math.min(i, 8) * 0.05 }}
            whileHover={reduce ? undefined : { y: -3 }}
            className="flex flex-col rounded-2xl border p-5"
            style={{ background: SURFACE.card, borderColor: SURFACE.line }}
          >
            <div className="flex items-start justify-between gap-3">
              <div className="min-w-0">
                <p className="font-display text-lg font-bold leading-tight">{job.title}</p>
                <p className="mt-1 text-[13px]" style={{ color: INK.secondary }}>
                  {job.company}
                </p>
              </div>
              <span className="shrink-0 font-mono text-[10px]" style={{ color: INK.faint }}>
                {posted(job.posted_at)}
              </span>
            </div>

            <p className="mt-3 font-mono text-[11px]" style={{ color: INK.muted }}>
              {[job.location, job.work_mode, job.seniority].filter(Boolean).join("  ·  ") || "—"}
            </p>

            {job.skills.length > 0 && (
              <div className="mt-3 flex flex-wrap gap-1.5">
                {job.skills.map((s) => (
                  <span
                    key={s}
                    className="rounded-full px-2.5 py-1 font-mono text-[10px] uppercase tracking-[0.08em]"
                    style={{ background: "rgba(255,255,255,0.06)", color: INK.secondary }}
                  >
                    {s}
                  </span>
                ))}
              </div>
            )}

            <div className="mt-auto flex items-center gap-3 pt-4">
              <GhostAction href="/jobs-for-you">Prep for this role</GhostAction>
              {job.question_count > 0 && (
                <span className="font-mono text-[11px]" style={{ color: INK.faint }}>
                  {job.question_count} likely questions
                </span>
              )}
            </div>
          </motion.article>
        ))}
      </div>
    </>
  );
}
