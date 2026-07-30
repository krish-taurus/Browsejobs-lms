"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { motion, useReducedMotion } from "framer-motion";
import { MonoCounter } from "@/components/motion/MonoCounter";
import { useWorkspace } from "@/components/employer/EmployerShell";
import {
  EASE,
  InkPanel,
  Label,
  LiveDot,
  PageHead,
  Pill,
  PrimaryButton,
  Skeleton,
  Tile,
} from "@/components/employer/ui";
import {
  DEEP,
  Funnel,
  PipelineBar,
  Ring,
  ScoreHistogram,
  SKY,
  TrendChart,
  TRUST,
  VERIFY,
  VIOLET,
} from "@/components/employer/charts";
import { employerApi, STAGE_LABELS, type DashboardData } from "@/lib/employer";

const SEGMENT_COLORS: Record<string, string> = {
  applied: "#c7d7f5",
  graded: SKY,
  shortlisted: TRUST,
  l1: DEEP,
  l2: VIOLET,
  human_round: "#5b3fb8",
  offer: VERIFY,
  hired: "#08935420",
};

export default function EmployerDashboardPage() {
  const { workspace } = useWorkspace();
  const reduce = useReducedMotion();
  const [data, setData] = useState<DashboardData | null>(null);
  const [failed, setFailed] = useState(false);

  useEffect(() => {
    setData(null);
    setFailed(false);
    employerApi.dashboard(workspace.id).then((res) => setData(res.data)).catch(() => setFailed(true));
  }, [workspace.id]);

  if (failed) {
    return (
      <p className="rounded-3xl border border-black/[0.07] bg-white p-8 text-sm text-black/50">
        The command centre could not load. Refresh to try again.
      </p>
    );
  }

  if (!data) {
    return (
      <div className="space-y-5">
        <Skeleton className="h-24" />
        <div className="grid gap-4 md:grid-cols-6">
          <Skeleton className="h-36 md:col-span-2" />
          <Skeleton className="h-36 md:col-span-2" />
          <Skeleton className="h-36 md:col-span-2" />
        </div>
        <Skeleton className="h-64" />
      </div>
    );
  }

  const topJob = [...data.pipeline].sort((a, b) => b.awaiting_review - a.awaiting_review)[0];
  const gradedPct = data.total_applications
    ? Math.round((data.graded_applications / data.total_applications) * 100)
    : 0;

  return (
    <div className="space-y-7 pb-10">
      <PageHead
        kicker="Command centre"
        title={workspace.name}
        sub="Every applicant below arrived pre-interviewed and graded against your own job description."
      />

      {/* Hero row: the one action that matters + the proof metric ------ */}
      <div className="grid gap-4 md:grid-cols-6 md:gap-5">
        <InkPanel className="md:col-span-4">
          <div className="flex h-full flex-col justify-between gap-6">
            <div>
              <div className="flex items-center gap-2">
                <LiveDot />
                <Label dark>Next best action</Label>
              </div>
              {topJob && topJob.awaiting_review > 0 ? (
                <p className="font-display mt-3 text-2xl font-bold leading-[1.15] tracking-tight md:text-[2.1rem]">
                  <span className="bg-gradient-to-r from-[#4d94ff] to-[#a78bfa] bg-clip-text text-transparent">
                    {topJob.awaiting_review} graded {topJob.awaiting_review === 1 ? "applicant" : "applicants"}
                  </span>
                  <br />
                  waiting on {topJob.title}.
                </p>
              ) : (
                <p className="font-display mt-3 text-2xl font-bold leading-[1.15] tracking-tight md:text-[2.1rem]">
                  {data.active_jobs === 0 ? (
                    <>
                      Post your first JD.{" "}
                      <span className="bg-gradient-to-r from-[#4d94ff] to-[#a78bfa] bg-clip-text text-transparent">
                        The mock builds itself.
                      </span>
                    </>
                  ) : (
                    <>
                      Nothing waiting on you.{" "}
                      <span className="bg-gradient-to-r from-[#4d94ff] to-[#a78bfa] bg-clip-text text-transparent">
                        Pipeline is clear.
                      </span>
                    </>
                  )}
                </p>
              )}
              <p className="mt-3 max-w-md text-sm leading-relaxed text-white/50">
                {topJob && topJob.awaiting_review > 0
                  ? "Each one has a scored interview transcript and a rubric breakdown attached — no CV guesswork."
                  : data.active_jobs === 0
                    ? "Publishing a JD generates its own interview and grading rubric automatically."
                    : "New graded applicants appear here the moment they finish their interview."}
              </p>
            </div>
            <div className="flex flex-wrap items-center gap-3">
              {topJob && topJob.awaiting_review > 0 ? (
                <PrimaryButton href={`/employer/jobs/${topJob.id}`}>Review now</PrimaryButton>
              ) : (
                <PrimaryButton href="/employer/jobs/new">Post a JD</PrimaryButton>
              )}
              <Link
                href="/employer/jobs"
                className="text-sm font-medium text-white/50 underline-offset-4 transition-colors hover:text-white"
              >
                All jobs
              </Link>
            </div>
          </div>
        </InkPanel>

        <Tile accent={VERIFY} className="md:col-span-2" index={1}>
          <Label>Pre-interviewed</Label>
          <div className="mt-4 flex items-center gap-4">
            <Ring value={gradedPct} size={96} color={VERIFY} track="#e6f7ef">
              <span className="font-display text-xl font-bold">{gradedPct}%</span>
            </Ring>
            <div>
              <p className="font-display text-2xl font-bold leading-none">
                <MonoCounter value={data.graded_applications} className="font-display" />
              </p>
              <p className="mt-1 text-[13px] leading-snug text-black/50">
                of {data.total_applications} applicants arrived with a graded interview
              </p>
            </div>
          </div>
        </Tile>
      </div>

      {/* Stat band -------------------------------------------------- */}
      <div className="grid grid-cols-2 gap-4 md:grid-cols-4 md:gap-5">
        <Stat index={0} label="Active JDs" value={data.active_jobs} accent={TRUST} ghost="01" />
        <Stat index={1} label="Graded · last 7 days" value={data.graded_last_7d} accent={SKY} ghost="02" />
        <Stat index={2} label="Interviews in flight" value={data.interviews_in_flight} accent={VIOLET} ghost="03" />
        <Stat index={3} label="Offers open" value={data.offers_open} accent={VERIFY} ghost="04" />
      </div>

      {/* Charts row -------------------------------------------------- */}
      <div className="grid items-start gap-4 md:grid-cols-6 md:gap-5">
        <Tile accent={TRUST} className="md:col-span-4" index={0} hover={false}>
          <div className="flex items-baseline justify-between">
            <div>
              <Label>Applications vs gradings</Label>
              <p className="font-display mt-1 text-lg font-bold">Last 14 days</p>
            </div>
            <Pill tone="trust">{data.total_applications} total</Pill>
          </div>
          <div className="mt-5">
            <TrendChart points={data.trend} height={130} />
          </div>
        </Tile>

        <Tile accent={DEEP} className="md:col-span-2" index={1} hover={false}>
          <Label>Hiring funnel</Label>
          <p className="font-display mt-1 text-lg font-bold">Stage by stage</p>
          <div className="mt-4">
            <Funnel stages={data.funnel} />
          </div>
        </Tile>
      </div>

      <div className="grid items-start gap-4 md:grid-cols-6 md:gap-5">
        <Tile accent={VIOLET} className="md:col-span-2" index={0} hover={false}>
          <Label>Score distribution</Label>
          <p className="font-display mt-1 text-lg font-bold">Where your talent sits</p>
          <div className="mt-4">
            <ScoreHistogram bands={data.score_distribution} threshold={70} />
          </div>
        </Tile>

        {/* Pipeline pulse per JD ------------------------------------ */}
        <Tile accent={TRUST} className="md:col-span-4" index={1} hover={false}>
          <div className="flex items-baseline justify-between">
            <div>
              <Label>Pipeline pulse</Label>
              <p className="font-display mt-1 text-lg font-bold">Live per JD</p>
            </div>
            <span className="font-mono text-[11px] text-black/40">
              {data.awaiting_review} awaiting review
            </span>
          </div>

          {data.pipeline.length === 0 ? (
            <p className="mt-4 text-sm text-black/50">
              No published JDs yet.{" "}
              <Link className="font-semibold text-[#1b6df0]" href="/employer/jobs/new">
                Post one
              </Link>{" "}
              and pre-interviewed applicants start flowing in.
            </p>
          ) : (
            <ul className="mt-5 space-y-5">
              {data.pipeline.map((job, i) => {
                const total = Object.values(job.stage_counts).reduce((a, b) => a + b, 0);
                const segments = Object.entries(job.stage_counts).map(([key, count]) => ({
                  key,
                  label: STAGE_LABELS[key as keyof typeof STAGE_LABELS] ?? key,
                  count,
                  color: SEGMENT_COLORS[key] ?? SKY,
                }));

                return (
                  <motion.li
                    key={job.id}
                    initial={reduce ? false : { opacity: 0, x: -8 }}
                    animate={{ opacity: 1, x: 0 }}
                    transition={{ duration: 0.6, ease: EASE, delay: i * 0.06 }}
                  >
                    <Link href={`/employer/jobs/${job.id}`} className="group block">
                      <div className="flex items-baseline justify-between gap-3">
                        <p className="text-sm font-semibold transition-colors group-hover:text-[#1b6df0]">
                          {job.title}
                        </p>
                        <span className="font-mono text-[11px] text-black/40">
                          {job.awaiting_review > 0 && (
                            <span className="mr-2 font-semibold text-[#1b6df0]">
                              {job.awaiting_review} to review
                            </span>
                          )}
                          {total} in pipeline
                        </span>
                      </div>
                      <div className="mt-2">
                        <PipelineBar segments={segments} />
                      </div>
                    </Link>
                  </motion.li>
                );
              })}
            </ul>
          )}
        </Tile>
      </div>

      <p className="font-mono text-[10px] leading-relaxed text-black/35">
        Counts reflect this workspace&apos;s live pipeline. Interview grading normally completes within the hour;
        delayed gradings are shown as pending — scores are never fabricated.
      </p>
    </div>
  );
}

function Stat({
  label,
  value,
  accent,
  ghost,
  index,
}: {
  label: string;
  value: number;
  accent: string;
  ghost: string;
  index: number;
}) {
  return (
    <Tile accent={accent} index={index} ghost={ghost}>
      <Label>{label}</Label>
      <p className="font-display mt-3 text-4xl font-bold leading-none tracking-tight md:text-5xl">
        <MonoCounter value={value} className="font-display" />
      </p>
    </Tile>
  );
}
