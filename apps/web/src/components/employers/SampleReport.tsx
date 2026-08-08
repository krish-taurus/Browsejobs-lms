"use client";

import { motion, useReducedMotion } from "framer-motion";
import { durations, ease } from "@/lib/motion";
import { InkLabel, InkPanel, LiveDot, SampleStamp } from "@/components/employers/ui";
import {
  sampleDistribution,
  sampleIntegrity,
  sampleReport,
  sampleThreshold,
} from "@/content/employers";

/**
 * The candidate report an employer opens, rebuilt from the same
 * `CandidateProfileData` shape the portal renders — including the parts a
 * sales page would normally hide:
 *
 *  - contact details are withheld, because the sample is at Graded
 *  - one verification check is pending and one has not started
 *
 * A preview that showed a fully verified, fully green candidate would be
 * selling a screen that does not exist.
 *
 * Status colour follows the portal: green for a check that passed, amber for
 * one under review, red for one that came back failed, neutral for one nobody
 * has started. Every state is also named in text — colour never carries it
 * alone.
 */

const STATUS_SKIN: Record<string, { chip: string; text: string }> = {
  verified: { chip: "border-verify/40 bg-verify/10", text: "text-verify" },
  pending: { chip: "border-amber/40 bg-amber/10", text: "text-amber" },
  failed: { chip: "border-warn/40 bg-warn/10", text: "text-warn" },
  expired: { chip: "border-warn/40 bg-warn/10", text: "text-warn" },
  not_started: { chip: "border-white/10 bg-white/[0.03]", text: "text-white/45" },
};

function skin(status: string) {
  return STATUS_SKIN[status] ?? STATUS_SKIN.not_started;
}

function checkSubtitle(status: string, verifiedAt: string | null): string {
  if (verifiedAt) return `Confirmed ${shortDate(verifiedAt)}`;
  switch (status) {
    case "pending":
      return "Submitted — BrowseJobs is checking it";
    case "failed":
      return "Checked, and it did not confirm";
    case "expired":
      return "Confirmed once, now out of date";
    default:
      return "Nothing submitted yet";
  }
}

function shortDate(iso: string | null): string {
  if (!iso) return "—";
  return new Date(iso).toLocaleDateString("en-IN", {
    day: "numeric",
    month: "short",
    year: "numeric",
  });
}

/** Score ring that draws in once, transform/opacity only under the hood. */
function Ring({ value }: { value: number }) {
  const reduce = useReducedMotion();
  const r = 32;
  const c = 2 * Math.PI * r;
  return (
    <div className="relative grid h-[84px] w-[84px] shrink-0 place-items-center">
      <svg viewBox="0 0 80 80" className="absolute inset-0 -rotate-90" aria-hidden>
        <circle cx="40" cy="40" r={r} fill="none" strokeWidth="7" className="stroke-white/[0.08]" />
        <motion.circle
          cx="40"
          cy="40"
          r={r}
          fill="none"
          strokeWidth="7"
          strokeLinecap="round"
          className="stroke-trust"
          strokeDasharray={c}
          initial={reduce ? { strokeDashoffset: c * (1 - value / 100) } : { strokeDashoffset: c }}
          whileInView={{ strokeDashoffset: c * (1 - value / 100) }}
          viewport={{ once: true, margin: "-60px" }}
          transition={{ duration: 1.1, ease }}
        />
      </svg>
      <span className="mono relative text-xl font-semibold text-white">{value}</span>
    </div>
  );
}

/** Competency bar. Green at 80+, blue below — the portal's own thresholds. */
function Dimension({ name, score, i }: { name: string; score: number; i: number }) {
  const reduce = useReducedMotion();
  return (
    <li>
      <div className="flex items-baseline justify-between gap-3">
        <span className="text-[13px] font-medium text-white/85">{name}</span>
        <span className="mono text-[12px] text-white/60">{score}</span>
      </div>
      <span className="mt-1.5 block h-1.5 overflow-hidden rounded-full bg-white/[0.07]">
        <motion.span
          className={`block h-full rounded-full ${score >= 80 ? "bg-verify" : "bg-trust"}`}
          initial={reduce ? { width: `${score}%` } : { width: 0 }}
          whileInView={{ width: `${score}%` }}
          viewport={{ once: true, margin: "-60px" }}
          transition={{ duration: 0.9, ease, delay: i * 0.06 }}
        />
      </span>
    </li>
  );
}

export function SampleReport() {
  const reduce = useReducedMotion();
  const { application, candidate, cv, verification, interview } = sampleReport;
  const peak = Math.max(...sampleDistribution.map((b) => b.count));

  return (
    <div className="mt-12 space-y-4">
      {/* Identity ---------------------------------------------------- */}
      <InkPanel accent="trust" className="p-6 md:p-8">
        <div className="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
          <div className="flex items-center gap-5">
            <Ring value={application.mock_score ?? 0} />
            <div>
              <h3 className="display text-2xl text-white md:text-3xl">{candidate.name}</h3>
              <p className="mt-1 text-[13px] text-white/50">
                Applied {shortDate(application.applied_at)} ·{" "}
                {application.mock_attempts} interview attempts, best score shown
              </p>
            </div>
          </div>
          <div className="flex flex-wrap items-center gap-2">
            <span className="mono rounded-full border border-trust/40 bg-trust/10 px-3 py-1.5 text-[10px] uppercase tracking-[0.12em] text-trust">
              Graded
            </span>
            {/* The stamp is earned. Its absence is labelled, not left blank. */}
            <span className="mono inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/[0.04] px-3 py-1.5 text-[10px] uppercase tracking-[0.12em] text-white/55">
              <span aria-hidden>○</span> Not fully verified
            </span>
            <SampleStamp />
          </div>
        </div>

        <div className="mt-6 border-t border-white/[0.07] pt-5">
          <p className="text-[13px] text-white/45">
            Contact details unlock at Shortlisted. Until then you assess the work, not the person.
          </p>
        </div>
      </InkPanel>

      {/* Verification ------------------------------------------------- */}
      <InkPanel accent="verify" className="p-6 md:p-8">
        <InkLabel>Verification</InkLabel>
        <h3 className="display mt-2 text-xl text-white">What has actually been checked</h3>
        <p className="mt-2 max-w-2xl text-[13px] leading-relaxed text-white/50">
          Identity and education come from DigiLocker&apos;s issued documents; employment is
          checked against the EPFO contribution record. Each check reports its result only —
          BrowseJobs holds the documents behind them.
        </p>

        <ul className="mt-5 grid gap-2.5 sm:grid-cols-2">
          {verification.checks.map((check, i) => {
            const s = skin(check.status);
            return (
              <motion.li
                key={check.kind}
                initial={reduce ? false : { opacity: 0, y: 10 }}
                whileInView={{ opacity: 1, y: 0 }}
                viewport={{ once: true, margin: "-60px" }}
                transition={{ duration: durations.slow, ease, delay: i * 0.05 }}
                className={`rounded-2xl border p-4 ${s.chip}`}
              >
                <div className="flex items-baseline justify-between gap-3">
                  <p className="text-[13px] font-semibold text-white">{check.label}</p>
                  <span className={`mono text-[10px] uppercase tracking-[0.12em] ${s.text}`}>
                    {check.status_label}
                  </span>
                </div>
                <p className="mt-1 text-[11px] text-white/40">
                  {checkSubtitle(check.status, check.verified_at)}
                </p>
              </motion.li>
            );
          })}
        </ul>
      </InkPanel>

      {/* Interview analysis ------------------------------------------- */}
      {interview && (
        <>
          <InkPanel accent="trust" className="p-6 md:p-8">
            <InkLabel>Interview analysis</InkLabel>
            <h3 className="display mt-2 text-xl text-white">
              How they scored, dimension by dimension
            </h3>
            <ul className="mt-5 space-y-3.5">
              {interview.competencies.map((c, i) => (
                <Dimension key={c.name} name={c.name} score={c.score ?? 0} i={i} />
              ))}
            </ul>
            <p className="mono mt-5 text-[10px] uppercase tracking-[0.14em] text-white/35">
              Graded by AI against this JD&apos;s rubric
            </p>
          </InkPanel>

          <div className="grid gap-4 md:grid-cols-2">
            <InkPanel accent="verify" className="p-6 md:p-8">
              <InkLabel>What went well</InkLabel>
              <ul className="mt-4 space-y-3">
                {interview.strengths.map((s) => (
                  <li key={s} className="flex gap-2.5 text-[13px] leading-relaxed text-white/75">
                    <span aria-hidden className="text-verify">
                      ▸
                    </span>
                    {s}
                  </li>
                ))}
              </ul>
            </InkPanel>

            {/* Amber here is the design system's coach-note use: a written
                note on where someone was weaker, not a failure state. */}
            <InkPanel accent="trust" className="p-6 md:p-8">
              <InkLabel>Where they were weaker</InkLabel>
              <ul className="mt-4 space-y-3">
                {interview.concerns.map((s) => (
                  <li key={s} className="flex gap-2.5 text-[13px] leading-relaxed text-white/75">
                    <span aria-hidden className="text-amber">
                      ▸
                    </span>
                    {s}
                  </li>
                ))}
              </ul>
            </InkPanel>
          </div>

          {/* Session, including the fact that is missing ------------- */}
          <InkPanel accent="employer" className="p-6 md:p-8">
            <InkLabel>The session itself</InkLabel>
            <dl className="mt-4 grid gap-4 sm:grid-cols-3">
              <div>
                <dt className="mono text-[10px] uppercase tracking-[0.12em] text-white/35">Mode</dt>
                <dd className="mono mt-1 text-[13px] text-white/80">{interview.session.mode}</dd>
              </div>
              <div>
                <dt className="mono text-[10px] uppercase tracking-[0.12em] text-white/35">
                  Length
                </dt>
                <dd className="mono mt-1 text-[13px] text-white/80">
                  {Math.round((interview.session.duration_seconds ?? 0) / 60)} min
                </dd>
              </div>
              <div>
                <dt className="mono text-[10px] uppercase tracking-[0.12em] text-white/35">
                  Completed
                </dt>
                <dd className="mono mt-1 text-[13px] text-white/80">
                  {shortDate(interview.session.completed_at)}
                </dd>
              </div>
            </dl>
            {/* Session integrity. Present because the session was proctored;
                if it had not been, this panel would say that instead of
                quietly omitting the row. */}
            <div className="mt-6 border-t border-white/[0.07] pt-5">
              <p className="mono flex items-center gap-2 text-[10px] uppercase tracking-[0.14em] text-verify">
                <LiveDot />
                {interview.session.proctoring_captured ? "Proctored" : "Not observed"}
              </p>

              <ul className="mt-4 grid gap-2.5 sm:grid-cols-2">
                {sampleIntegrity.signals.map((sig, i) => (
                  <motion.li
                    key={sig.label}
                    initial={reduce ? false : { opacity: 0, y: 10 }}
                    whileInView={{ opacity: 1, y: 0 }}
                    viewport={{ once: true, margin: "-60px" }}
                    transition={{ duration: durations.slow, ease, delay: i * 0.05 }}
                    className={`flex items-baseline justify-between gap-3 rounded-2xl border px-4 py-3 ${
                      sig.clean ? "border-verify/30 bg-verify/[0.07]" : "border-amber/40 bg-amber/10"
                    }`}
                  >
                    <span className="text-[12px] text-white/70">{sig.label}</span>
                    <span
                      className={`mono shrink-0 text-[12px] ${
                        sig.clean ? "text-verify" : "text-amber"
                      }`}
                    >
                      {sig.value}
                    </span>
                  </motion.li>
                ))}
              </ul>

              <p className="mt-4 text-[12px] leading-relaxed text-white/45">
                A session that had raised flags would show them here, in the same place, rather
                than being averaged into the score above.
              </p>
            </div>
          </InkPanel>
        </>
      )}

      {/* Background --------------------------------------------------- */}
      {cv && (
        <InkPanel accent="trust" className="p-6 md:p-8">
          <InkLabel>Background</InkLabel>
          <p className="mt-4 max-w-3xl text-[14px] leading-relaxed text-white/70">{cv.summary}</p>
          <div className="mt-5 flex flex-wrap gap-1.5">
            {cv.skills.map((s) => (
              <span
                key={s}
                className="mono rounded-full border border-white/10 px-2.5 py-1 text-[10px] text-white/60"
              >
                {s}
              </span>
            ))}
          </div>
          <dl className="mt-6 grid gap-5 sm:grid-cols-2">
            {(["experience", "education"] as const).map((key) => (
              <div key={key}>
                <dt className="mono text-[10px] uppercase tracking-[0.12em] text-white/35">
                  {key === "experience" ? "Experience" : "Education"}
                </dt>
                <dd className="mt-2 space-y-2">
                  {cv[key].map((row) => (
                    <p key={String(row.title)} className="text-[13px] text-white/70">
                      <span className="font-semibold text-white/85">{String(row.title)}</span>
                      <span className="text-white/40"> · {String(row.org)}</span>
                      <span className="mono block text-[11px] text-white/35">
                        {String(row.period)}
                      </span>
                    </p>
                  ))}
                </dd>
              </div>
            ))}
          </dl>
        </InkPanel>
      )}

      {/* Where this candidate sits in the round ----------------------- */}
      <InkPanel accent="employer" className="p-6 md:p-8">
        <InkLabel>The rest of the applicants</InkLabel>
        <h3 className="display mt-2 text-xl text-white">
          One score means nothing without the distribution
        </h3>
        <p className="mt-2 max-w-2xl text-[13px] leading-relaxed text-white/50">
          The dashboard draws your threshold across the whole round, so &ldquo;78&rdquo; becomes a
          position rather than a number.
        </p>

        <div className="mt-6 flex items-end gap-3 sm:gap-5">
          {sampleDistribution.map((b, i) => {
            const above = b.floor >= sampleThreshold;
            return (
              <div key={b.band} className="flex flex-1 flex-col items-center gap-2">
                <span className="mono text-[11px] text-white/50">{b.count}</span>
                <motion.span
                  className={`w-full rounded-t-lg ${above ? "bg-verify" : "bg-white/[0.12]"}`}
                  style={{ transformOrigin: "bottom" }}
                  initial={reduce ? false : { scaleY: 0 }}
                  whileInView={{ scaleY: 1 }}
                  viewport={{ once: true, margin: "-60px" }}
                  transition={{ duration: 0.8, ease, delay: i * 0.07 }}
                >
                  <span
                    className="block"
                    style={{ height: `${Math.max(12, (b.count / peak) * 132)}px` }}
                  />
                </motion.span>
                <span className="mono text-[10px] text-white/35">{b.band}</span>
              </div>
            );
          })}
        </div>
        <p className="mono mt-4 text-[10px] uppercase tracking-[0.13em] text-verify">
          69 of 261 applicants at or above your {sampleThreshold}% threshold
        </p>
      </InkPanel>
    </div>
  );
}
