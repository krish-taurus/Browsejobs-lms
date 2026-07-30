"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { motion, useReducedMotion } from "framer-motion";
import { Funnel, Histogram, Legend, Radial, StatusChip, TimeSeries } from "@/components/viz/Chart";
import {
  Card,
  Deck,
  GhostAction,
  Hero,
  Kicker,
  PageTitle,
  PrimaryAction,
  Shimmer,
} from "@/components/viz/Surface";
import { DUR, EASE, INK, SERIES, SURFACE } from "@/components/viz/tokens";
import { DISCLAIMER } from "@/content/landing";
import {
  candidateApi,
  STAGE_LABELS,
  type CandidateDashboardData,
  type VerificationCheck,
} from "@/lib/candidate";

const STAGE_ORDER = ["applied", "graded", "shortlisted", "l1", "l2", "offer", "hired"];

const CHECK_TONE: Record<VerificationCheck["status"], "good" | "warning" | "critical" | "neutral"> = {
  verified: "good",
  pending: "warning",
  failed: "critical",
  expired: "warning",
  not_started: "neutral",
};

export default function CandidateDashboardPage() {
  const [data, setData] = useState<CandidateDashboardData | null>(null);
  const [failed, setFailed] = useState(false);

  useEffect(() => {
    candidateApi
      .dashboard()
      .then((r) => setData(r.data))
      .catch(() => setFailed(true));
  }, []);

  if (failed) {
    return (
      <Deck>
        <main className="mx-auto max-w-6xl px-5 py-16">
          <Card>
            <p className="text-sm" style={{ color: INK.secondary }}>
              Your dashboard could not load. Refresh to try again.
            </p>
          </Card>
        </main>
      </Deck>
    );
  }

  if (!data) {
    return (
      <Deck>
        <main className="mx-auto max-w-6xl space-y-4 px-5 py-16">
          <Shimmer className="h-28" />
          <div className="grid gap-4 md:grid-cols-3">
            <Shimmer className="h-52 md:col-span-2" />
            <Shimmer className="h-52" />
          </div>
          <Shimmer className="h-64" />
        </main>
      </Deck>
    );
  }

  const live = data.applications.filter(
    (a) => !["rejected", "withdrawn"].includes(a.stage),
  );
  const needsMock = data.applications.filter((a) => a.awaiting_candidate);
  const bestMock = data.mock_history.reduce((m, p) => Math.max(m, p.score), 0);
  const offers = data.applications.filter((a) => ["offer", "hired"].includes(a.stage));

  // Cumulative, not stage occupancy: someone sitting at Round 1 also applied
  // and was graded. Counting raw occupancy would show "Applied 0" to a
  // candidate who is mid-process, which reads as if they never applied.
  // Rejected and withdrawn are excluded — they left the funnel, and where
  // they left from is not recoverable from a stage count.
  const funnelStages = STAGE_ORDER.map((key, i) => ({
    key,
    label: STAGE_LABELS[key] ?? key,
    count: STAGE_ORDER.slice(i).reduce((sum, later) => sum + (data.funnel[later] ?? 0), 0),
  })).filter((s, i) => i < 3 || s.count > 0);

  const scoreBands = Array.from({ length: 10 }, (_, i) => ({
    label: `${i * 10}–${i * 10 + 9}`,
    floor: i * 10,
    count: data.mock_history.filter((p) => Math.min(90, Math.floor(p.score / 10) * 10) === i * 10)
      .length,
  }));

  return (
    <Deck>
      <main className="mx-auto max-w-6xl space-y-6 px-5 py-12 md:py-16">
        <PageTitle
          kicker="Your command centre"
          title="Where you stand,"
          highlight="right now."
          sub="Every application, every check, and the one thing most worth doing next. Nothing here is a prediction — it is what employers can actually see."
          action={
            <div className="flex gap-2">
              <GhostAction href="/jobs">Browse jobs</GhostAction>
              <PrimaryAction href="/candidate/verification">Verify profile</PrimaryAction>
            </div>
          }
        />

        {/* Next best action ------------------------------------------- */}
        <NextBestAction data={data} needsMock={needsMock.length} />

        {/* Stat row ---------------------------------------------------- */}
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <Card index={0} accent={SERIES[0]}>
            <Kicker>Live applications</Kicker>
            <Hero value={<span className="font-mono">{live.length}</span>} />
          </Card>
          <Card index={1} accent={SERIES[1]}>
            <Kicker>Best mock score</Kicker>
            <Hero value={<span className="font-mono">{bestMock || "—"}</span>} suffix={bestMock ? "/100" : undefined} />
          </Card>
          <Card index={2} accent={SERIES[3]}>
            <Kicker>Mock credits</Kicker>
            <Hero
              value={<span className="font-mono">{data.credits.mock}</span>}
              sub={data.credits.mock === 0 ? "Retakes need credits." : "Retakes are unlimited while these last."}
            />
          </Card>
          <Card index={3} accent={SERIES[4]}>
            <Kicker>Offers</Kicker>
            <Hero value={<span className="font-mono">{offers.length}</span>} />
          </Card>
        </div>

        {/* Charts ------------------------------------------------------ */}
        <div className="grid items-start gap-4 lg:grid-cols-3">
          <Card index={0} className="lg:col-span-2">
            <div className="flex flex-wrap items-baseline justify-between gap-2">
              <div>
                <Kicker>Mock score trajectory</Kicker>
                <p className="font-display mt-1 text-lg font-bold">Every graded attempt</p>
              </div>
              <span className="font-mono text-[11px]" style={{ color: INK.muted }}>
                {data.mock_history.length} graded
              </span>
            </div>
            <div className="mt-4">
              {data.mock_history.length === 0 ? (
                <Empty
                  title="No graded mocks yet"
                  body="Apply to a role on BrowseJobs and take its mock — that score is what employers rank you on."
                />
              ) : (
                <TimeSeries
                  labels={data.mock_history.map((p) => p.date)}
                  series={[
                    {
                      key: "score",
                      label: "Mock score",
                      color: SERIES[0],
                      values: data.mock_history.map((p) => p.score),
                    },
                  ]}
                  height={200}
                />
              )}
            </div>
          </Card>

          <Card index={1}>
            <Kicker>Your funnel</Kicker>
            <p className="font-display mt-1 text-lg font-bold">Stage by stage</p>
            <div className="mt-4">
              {data.applications.length === 0 ? (
                <Empty title="Nothing applied yet" body="Your funnel fills in as you apply." />
              ) : (
                <Funnel stages={funnelStages} />
              )}
            </div>
          </Card>
        </div>

        {/* Verification ------------------------------------------------ */}
        <div className="grid items-start gap-4 lg:grid-cols-3">
          <Card index={0} accent={data.verification.badge ? SERIES[1] : undefined} glow>
            <Kicker>Verified profile</Kicker>
            <div className="mt-4 flex items-center gap-5">
              <Radial
                value={(data.verification.verified_count / data.verification.total_count) * 100}
                size={112}
                color={data.verification.badge ? SERIES[1] : SERIES[0]}
              >
                <span className="font-mono text-lg font-bold">
                  {data.verification.verified_count}
                  <span style={{ color: INK.muted }}>/{data.verification.total_count}</span>
                </span>
              </Radial>
              <div>
                {data.verification.badge ? (
                  <StatusChip tone="good">Verified</StatusChip>
                ) : (
                  <StatusChip tone="neutral">Not yet verified</StatusChip>
                )}
                <p className="mt-3 text-[13px] leading-snug" style={{ color: INK.secondary }}>
                  {data.verification.badge
                    ? "Employers see a verified stamp on your profile."
                    : `Still needed: ${data.verification.missing_for_badge.join(", ")}.`}
                </p>
              </div>
            </div>
          </Card>

          <Card index={1} className="lg:col-span-2">
            <Kicker>Checks</Kicker>
            <p className="font-display mt-1 text-lg font-bold">What an employer can confirm</p>
            <ul className="mt-4 space-y-2">
              {data.verification.checks.map((c, i) => (
                <motion.li
                  key={c.kind}
                  initial={{ opacity: 0, x: -6 }}
                  animate={{ opacity: 1, x: 0 }}
                  transition={{ duration: DUR.base, ease: EASE, delay: i * 0.05 }}
                  className="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-xl border px-3.5 py-3"
                  style={{ borderColor: SURFACE.line, background: "rgba(255,255,255,0.02)" }}
                >
                  <span className="text-[13px] font-semibold">{c.label}</span>
                  <StatusChip tone={CHECK_TONE[c.status]}>{c.status_label}</StatusChip>
                  {c.required_for_badge && (
                    <span className="font-mono text-[10px]" style={{ color: INK.faint }}>
                      REQUIRED
                    </span>
                  )}
                  <span
                    className="w-full text-[12px] leading-snug sm:w-auto sm:flex-1"
                    style={{ color: INK.muted }}
                  >
                    {c.employer_value}
                  </span>
                </motion.li>
              ))}
            </ul>
          </Card>
        </div>

        {/* Where you sit vs people who got offers ---------------------- */}
        <div className="grid items-start gap-4 lg:grid-cols-3">
          <Card index={0} className="lg:col-span-2" accent={SERIES[3]}>
            <Kicker>Compared with candidates who reached an offer</Kicker>
            {data.cohort === null ? (
              <div className="mt-3">
                <p className="font-display text-lg font-bold">Not enough history yet</p>
                <p className="mt-2 max-w-lg text-[13px] leading-relaxed" style={{ color: INK.secondary }}>
                  We only show this comparison once enough people have reached an offer for it to
                  mean something. Until then we would be inventing a number, so we do not show one.
                </p>
              </div>
            ) : (
              <Cohort data={data} />
            )}
          </Card>

          <Card index={1}>
            <Kicker>Score distribution</Kicker>
            <p className="font-display mt-1 text-lg font-bold">Your attempts</p>
            <div className="mt-4">
              <Histogram
                bands={scoreBands}
                threshold={70}
                thresholdLabel="of your attempts scored 70 or above"
              />
            </div>
          </Card>
        </div>

        {/* Applications ------------------------------------------------ */}
        <Card index={0}>
          <div className="flex flex-wrap items-baseline justify-between gap-2">
            <div>
              <Kicker>Applications</Kicker>
              <p className="font-display mt-1 text-lg font-bold">
                {data.applications.length === 0
                  ? "Nothing yet"
                  : `${data.applications.length} in flight`}
              </p>
            </div>
            <GhostAction href="/jobs">Find roles</GhostAction>
          </div>

          {data.applications.length === 0 ? (
            <div className="mt-4">
              <Empty
                title="You have not applied to a BrowseJobs employer yet"
                body="Roles hiring through BrowseJobs let you apply by taking that job's mock — the employer sees a scored profile instead of a CV in a pile."
              />
            </div>
          ) : (
            <ul className="mt-4 space-y-2">
              {data.applications.map((a, i) => (
                <motion.li
                  key={a.id}
                  initial={{ opacity: 0, y: 8 }}
                  animate={{ opacity: 1, y: 0 }}
                  transition={{ duration: DUR.base, ease: EASE, delay: Math.min(i, 8) * 0.04 }}
                >
                  <Link
                    href={`/jobs/${a.job_id}`}
                    className="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-xl border px-4 py-3.5 transition-colors hover:border-white/20"
                    style={{ borderColor: SURFACE.line, background: "rgba(255,255,255,0.02)" }}
                  >
                    <div className="min-w-0 flex-1">
                      <p className="truncate text-sm font-semibold">{a.title ?? "Role"}</p>
                      <p className="truncate text-[12px]" style={{ color: INK.muted }}>
                        {a.company ?? "—"}
                      </p>
                    </div>
                    {a.mock_score !== null ? (
                      <span className="font-mono text-sm font-bold" style={{ color: SERIES[0] }}>
                        {a.mock_score}
                        <span className="text-[10px]" style={{ color: INK.faint }}>
                          /100
                        </span>
                      </span>
                    ) : (
                      <StatusChip tone="warning">Mock pending</StatusChip>
                    )}
                    <span
                      className="rounded-full px-3 py-1 font-mono text-[10px] font-semibold uppercase tracking-[0.1em]"
                      style={{
                        background: "rgba(255,255,255,0.06)",
                        color: INK.secondary,
                      }}
                    >
                      {STAGE_LABELS[a.stage] ?? a.stage}
                    </span>
                  </Link>
                </motion.li>
              ))}
            </ul>
          )}
        </Card>

        <p className="font-mono text-[10px] leading-relaxed" style={{ color: INK.faint }}>
          {DISCLAIMER}
        </p>
      </main>
    </Deck>
  );
}

/* ------------------------------------------------------------------ */

function NextBestAction({
  data,
  needsMock,
}: {
  data: CandidateDashboardData;
  needsMock: number;
}) {
  const reduce = useReducedMotion();

  // Ordered by what actually moves the candidate forward, not by what we
  // would most like to sell.
  const action =
    needsMock > 0
      ? {
          head: `${needsMock} application${needsMock === 1 ? "" : "s"} still need${needsMock === 1 ? "s" : ""} a mock.`,
          body: "Until you take the mock, the employer has nothing to rank you on. It takes about twenty minutes.",
          cta: "Take a mock",
          href: "/candidate/applications",
        }
      : !data.verification.badge
        ? {
            head: `Finish ${data.verification.missing_for_badge.join(" and ")}.`,
            body: "A verified profile is the one thing on this page an employer cannot get from a CV.",
            cta: "Start verification",
            href: "/candidate/verification",
          }
        : data.profile_completeness.pct < 100
          ? {
              head: "Fill the gaps in your profile.",
              body: `${data.profile_completeness.items.filter((i) => !i.done).map((i) => i.label).join(", ")}.`,
              cta: "Complete profile",
              href: "/candidate/profile",
            }
          : {
              head: "You are match-ready.",
              body: "Profile complete, verified, and graded. Keep applying — that is the only lever left.",
              cta: "Browse jobs",
              href: "/jobs",
            };

  return (
    <motion.section
      initial={reduce ? false : { opacity: 0, y: 18 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: DUR.slow, ease: EASE }}
      className="relative overflow-hidden rounded-2xl border p-6 md:p-8"
      style={{
        background: `linear-gradient(140deg, ${SERIES[0]}1f, ${SURFACE.card} 55%)`,
        borderColor: `${SERIES[0]}3d`,
      }}
    >
      <span
        aria-hidden
        className="pointer-events-none absolute -right-20 -top-24 h-64 w-80 rounded-full blur-[110px]"
        style={{ background: SERIES[0], opacity: 0.22 }}
      />
      <div className="relative flex flex-wrap items-end justify-between gap-6">
        <div className="max-w-xl">
          <Kicker color={SERIES[0]}>Next best action</Kicker>
          <p className="font-display mt-2.5 text-2xl font-bold leading-[1.15] md:text-[2rem]">
            {action.head}
          </p>
          <p className="mt-2.5 text-[14px] leading-relaxed" style={{ color: INK.secondary }}>
            {action.body}
          </p>
        </div>
        <PrimaryAction href={action.href}>{action.cta}</PrimaryAction>
      </div>
    </motion.section>
  );
}

function Cohort({ data }: { data: CandidateDashboardData }) {
  const c = data.cohort;
  if (!c) return null;

  const yours = c.your_best_mock ?? 0;
  const gap = c.offer_median_mock - yours;

  return (
    <div className="mt-3">
      <p className="font-display text-lg font-bold">
        {gap > 0 ? (
          <>
            You are{" "}
            <span className="font-mono" style={{ color: SERIES[3] }}>
              {gap}
            </span>{" "}
            points below the median that reached an offer.
          </>
        ) : (
          <>Your best mock is at or above the median that reached an offer.</>
        )}
      </p>

      <div className="mt-5 space-y-3">
        <Bar label="Median mock score at offer" value={c.offer_median_mock} color={SERIES[3]} />
        <Bar label="Your best mock score" value={yours} color={SERIES[0]} />
      </div>

      <Legend
        items={[
          { label: "Offer cohort median", color: SERIES[3] },
          { label: "You", color: SERIES[0] },
        ]}
      />

      <p className="mt-4 text-[12px] leading-relaxed" style={{ color: INK.muted }}>
        Measured across {c.sample} applications that reached an offer, and {c.verified_share_pct}% of
        them held a verified profile. This is a correlation, not a cause — it describes what those
        candidates had, not what will happen to you.
      </p>
    </div>
  );
}

function Bar({ label, value, color }: { label: string; value: number; color: string }) {
  const reduce = useReducedMotion();
  return (
    <div>
      <div className="flex items-baseline justify-between">
        <span className="text-[12px]" style={{ color: INK.secondary }}>
          {label}
        </span>
        <span className="font-mono text-[13px] font-bold" style={{ color: INK.primary }}>
          {value}
        </span>
      </div>
      <div
        className="mt-1.5 h-2.5 overflow-hidden rounded-full"
        style={{ background: "rgba(255,255,255,0.05)" }}
      >
        <motion.div
          initial={reduce ? false : { width: 0 }}
          animate={{ width: `${Math.min(100, value)}%` }}
          transition={{ duration: DUR.slow, ease: EASE }}
          className="h-full rounded-full"
          style={{ background: `linear-gradient(90deg, ${color}99, ${color})` }}
        />
      </div>
    </div>
  );
}

function Empty({ title, body }: { title: string; body: string }) {
  return (
    <div
      className="rounded-xl border border-dashed px-5 py-6"
      style={{ borderColor: SURFACE.lineStrong }}
    >
      <p className="text-sm font-semibold">{title}</p>
      <p className="mt-1.5 max-w-md text-[13px] leading-relaxed" style={{ color: INK.muted }}>
        {body}
      </p>
    </div>
  );
}
