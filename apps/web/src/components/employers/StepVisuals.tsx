"use client";

import { useEffect, useState } from "react";
import { motion, useReducedMotion } from "framer-motion";
import { durations, ease, stagger } from "@/lib/motion";
import { InkLabel, LiveDot } from "@/components/employers/ui";
import type { Step } from "@/content/employers";

/**
 * The seven micro-visuals behind the walkthrough. Each is a still of a real
 * screen in the employer portal, rebuilt in tokens — not a screenshot, so it
 * stays legible at any width and flips with the theme.
 *
 * They animate on mount rather than on scroll, because the walkthrough
 * remounts one on every step change: the visual assembling itself is what
 * makes stepping through the product feel like watching it work. Under
 * reduced motion every one of them renders finished, instantly.
 */

/** Children fade + rise in sequence when the visual mounts. */
function Stack({ children, className = "" }: { children: React.ReactNode; className?: string }) {
  const reduce = useReducedMotion();
  return (
    <motion.div
      className={className}
      initial={reduce ? false : "hidden"}
      animate="show"
      variants={{ hidden: {}, show: { transition: { staggerChildren: stagger } } }}
    >
      {children}
    </motion.div>
  );
}

const ITEM = {
  hidden: { opacity: 0, y: 12 },
  show: { opacity: 1, y: 0, transition: { duration: durations.slow, ease } },
} as const;

function Frame({ label, live, children }: { label: string; live?: boolean; children: React.ReactNode }) {
  return (
    <div className="flex h-full flex-col">
      <div className="flex items-center justify-between">
        <InkLabel>{label}</InkLabel>
        {live ? (
          <span className="mono inline-flex items-center gap-2 text-[10px] uppercase tracking-[0.14em] text-white/35">
            <LiveDot />
            live
          </span>
        ) : (
          <span aria-hidden className="flex gap-1.5">
            <span className="h-1.5 w-1.5 rounded-full bg-white/15" />
            <span className="h-1.5 w-1.5 rounded-full bg-white/15" />
            <span className="h-1.5 w-1.5 rounded-full bg-white/15" />
          </span>
        )}
      </div>
      <div className="mt-5 flex-1">{children}</div>
    </div>
  );
}

function Row({ children, className = "" }: { children: React.ReactNode; className?: string }) {
  return (
    <div className={`rounded-2xl border border-white/[0.08] bg-white/[0.03] px-4 py-3 ${className}`}>
      {children}
    </div>
  );
}

function Chip({
  children,
  tone = "muted",
}: {
  children: React.ReactNode;
  tone?: "muted" | "trust" | "employer" | "verify";
}) {
  const skin =
    tone === "trust"
      ? "border-trust/40 text-trust"
      : tone === "employer"
        ? "border-employer/50 text-employer"
        : tone === "verify"
          ? "border-verify/40 text-verify"
          : "border-white/12 text-white/50";
  return <span className={`mono rounded-full border px-2.5 py-1 text-[10px] ${skin}`}>{children}</span>;
}

/** A bar that fills to `pct` on mount. */
function Meter({ pct, tone = "trust", delay = 0 }: { pct: number; tone?: "trust" | "employer" | "verify"; delay?: number }) {
  const reduce = useReducedMotion();
  const bar = tone === "employer" ? "bg-employer" : tone === "verify" ? "bg-verify" : "bg-trust";
  return (
    <span className="block h-1.5 overflow-hidden rounded-full bg-white/[0.07]">
      <motion.span
        className={`block h-full rounded-full ${bar}`}
        initial={reduce ? { width: `${pct}%` } : { width: 0 }}
        animate={{ width: `${pct}%` }}
        transition={{ duration: 0.9, ease, delay }}
      />
    </span>
  );
}

/** Types `text` out one character at a time; instant under reduced motion. */
function useTyped(text: string, msPerChar = 18): string {
  const reduce = useReducedMotion();
  const [n, setN] = useState(reduce ? text.length : 0);

  useEffect(() => {
    if (reduce) {
      setN(text.length);
      return;
    }
    setN(0);
    const t = window.setInterval(() => {
      setN((c) => {
        if (c >= text.length) {
          window.clearInterval(t);
          return c;
        }
        return c + 1;
      });
    }, msPerChar);
    return () => window.clearInterval(t);
  }, [text, msPerChar, reduce]);

  return text.slice(0, n);
}

/* ------------------------------------------------------------------ 01 */

function WorkspaceVisual() {
  const members = [
    { name: "You", role: "Owner", tone: "trust" as const },
    { name: "Divya P.", role: "Recruiter", tone: "employer" as const },
    { name: "Karthik S.", role: "Hiring manager", tone: "muted" as const },
  ];
  return (
    <Frame label="Workspace">
      <Stack>
        <motion.div variants={ITEM}>
          <Row className="!px-5 !py-4">
            <p className="display text-lg text-white">Meridian Logistics</p>
            <p className="mono mt-1 text-[10px] uppercase tracking-[0.12em] text-white/35">
              Logistics · 201–500 people
            </p>
          </Row>
        </motion.div>
        <ul className="mt-3 space-y-2">
          {members.map((m) => (
            <motion.li key={m.name} variants={ITEM}>
              <Row>
                <div className="flex items-center justify-between gap-3">
                  <span className="text-[13px] text-white/80">{m.name}</span>
                  <Chip tone={m.tone}>{m.role}</Chip>
                </div>
              </Row>
            </motion.li>
          ))}
        </ul>
        <motion.p variants={ITEM} className="mt-4 text-[11px] leading-relaxed text-white/35">
          A recruiter can move the pipeline. A hiring manager reads it. Neither can see another
          company&apos;s workspace.
        </motion.p>
      </Stack>
    </Frame>
  );
}

/* ------------------------------------------------------------------ 02 */

const JD_TEXT =
  "Own the batch pipelines that move operations data into the warehouse, and the models the ops dashboards read from…";

function JdVisual() {
  const typed = useTyped(JD_TEXT);
  const done = typed.length === JD_TEXT.length;

  return (
    <Frame label="JD drafter" live={!done}>
      <Stack>
        <motion.div variants={ITEM}>
          <Row>
            <p className="mono text-[10px] uppercase tracking-[0.12em] text-white/35">Title</p>
            <p className="mt-1 text-[15px] font-semibold text-white">Data Engineer</p>
          </Row>
        </motion.div>

        <motion.div variants={ITEM} className="mt-3">
          <p className="mono text-[10px] uppercase tracking-[0.12em] text-white/35">
            Suggested core skills
          </p>
          <div className="mt-2 flex flex-wrap gap-1.5">
            {["SQL", "Python", "Airflow", "dbt", "Warehousing"].map((s) => (
              <Chip key={s} tone="trust">
                {s}
              </Chip>
            ))}
          </div>
        </motion.div>

        <motion.div variants={ITEM} className="mt-4">
          <p className="mono text-[10px] uppercase tracking-[0.12em] text-white/35">Optional</p>
          <div className="mt-2 flex flex-wrap gap-1.5">
            {["Spark", "Kafka", "Terraform"].map((s) => (
              <Chip key={s}>{s}</Chip>
            ))}
          </div>
        </motion.div>

        <motion.div variants={ITEM} className="mt-4">
          <Row>
            {/* The draft writes itself — the point of the step. */}
            <p className="min-h-[3.9em] text-[12px] leading-relaxed text-white/60">
              &ldquo;{typed}
              {!done && (
                <span aria-hidden className="ml-0.5 inline-block h-[1em] w-[2px] translate-y-[2px] bg-trust" />
              )}
              {done && "”"}
            </p>
            <p className="mono mt-2 text-[10px] uppercase tracking-[0.12em] text-white/30">
              Drafted · edit before publishing
            </p>
          </Row>
        </motion.div>
      </Stack>
    </Frame>
  );
}

/* ------------------------------------------------------------------ 03 */

function MockVisual() {
  const weights = [
    { label: "SQL and data modelling", pct: 35 },
    { label: "Python for data", pct: 25 },
    { label: "Pipeline design", pct: 25 },
    { label: "Communication", pct: 15 },
  ];
  return (
    <Frame label="Assessment designer">
      <Stack>
        <ul className="space-y-3.5">
          {weights.map((w, i) => (
            <motion.li key={w.label} variants={ITEM}>
              <div className="flex items-baseline justify-between gap-3">
                <span className="text-[12px] text-white/75">{w.label}</span>
                <span className="mono text-[11px] text-white/50">{w.pct}%</span>
              </div>
              {/* The bar is the weight, unscaled — stretching 35% across most
                  of the track to "fill the space" would misread the chart. */}
              <div className="mt-1.5">
                <Meter pct={w.pct} tone="employer" delay={0.1 + i * 0.08} />
              </div>
            </motion.li>
          ))}
        </ul>
        <motion.div variants={ITEM} className="mt-5">
          <Row>
            <div className="flex flex-wrap items-center gap-x-5 gap-y-2">
              <span className="mono text-[11px] text-white/50">
                12 <span className="text-white/30">questions</span>
              </span>
              <span className="mono text-[11px] text-white/50">
                60% <span className="text-white/30">scenario</span>
              </span>
              <span className="mono text-[11px] text-white/50">
                40% <span className="text-white/30">short answer</span>
              </span>
            </div>
          </Row>
        </motion.div>
      </Stack>
    </Frame>
  );
}

/* ------------------------------------------------------------------ 04 */

function RoundsVisual() {
  const rounds = [
    { n: "R1", name: "Screening interview", kind: "AI interview", window: "48h", auto: "auto ≥ 70" },
    { n: "R2", name: "SQL depth", kind: "MCQ", window: "24h", auto: "auto ≥ 75" },
    { n: "R3", name: "Team conversation", kind: "Human", window: "—", auto: "manual" },
  ];
  return (
    <Frame label="Interview process">
      <Stack>
        <ul className="space-y-2.5">
          {rounds.map((r) => (
            <motion.li key={r.n} variants={ITEM}>
              <Row>
                <div className="flex items-center gap-3">
                  <span className="mono text-[11px] text-white/30">{r.n}</span>
                  <div className="min-w-0 flex-1">
                    <p className="truncate text-[13px] font-semibold text-white">{r.name}</p>
                    <p className="mono mt-0.5 text-[10px] uppercase tracking-[0.1em] text-white/35">
                      {r.kind} · window {r.window}
                    </p>
                  </div>
                  <Chip tone={r.auto === "manual" ? "muted" : "verify"}>{r.auto}</Chip>
                </div>
              </Row>
            </motion.li>
          ))}
        </ul>
        <motion.p variants={ITEM} className="mt-4 text-[11px] leading-relaxed text-white/35">
          A round already sat can be switched off, never deleted — the evidence stays.
        </motion.p>
      </Stack>
    </Frame>
  );
}

/* ------------------------------------------------------------------ 05 */

function PublishVisual() {
  const pool = [
    { name: "Rahul M.", match: 92 },
    { name: "Sneha K.", match: 84 },
    { name: "Imran S.", match: 71 },
  ];
  return (
    <Frame label="Published · talent pool" live>
      <Stack>
        <motion.div variants={ITEM}>
          <Row className="!px-5 !py-4">
            <div className="flex items-center justify-between gap-3">
              <div>
                <p className="text-[14px] font-semibold text-white">Data Engineer</p>
                <p className="mono mt-1 text-[10px] uppercase tracking-[0.12em] text-white/35">
                  Bengaluru · 2–4 yrs · 3 openings
                </p>
              </div>
              <Chip tone="verify">live</Chip>
            </div>
          </Row>
        </motion.div>

        <motion.p
          variants={ITEM}
          className="mono mt-4 text-[10px] uppercase tracking-[0.12em] text-white/35"
        >
          Matched from the trained pool
        </motion.p>
        <ul className="mt-2 space-y-2">
          {pool.map((p) => (
            <motion.li key={p.name} variants={ITEM}>
              <Row>
                <div className="flex items-center justify-between gap-3">
                  <span className="text-[13px] text-white/80">{p.name}</span>
                  <span className="flex items-center gap-3">
                    <span className="mono text-[12px] text-white/60">{p.match}% match</span>
                    <Chip tone="trust">invite</Chip>
                  </span>
                </div>
              </Row>
            </motion.li>
          ))}
        </ul>
      </Stack>
    </Frame>
  );
}

/* ------------------------------------------------------------------ 06 */

function GradeVisual() {
  const reduce = useReducedMotion();
  const dims = [
    { label: "SQL and data modelling", pct: 88, tone: "verify" as const },
    { label: "Python for data", pct: 81, tone: "verify" as const },
    { label: "Pipeline design", pct: 74, tone: "trust" as const },
    { label: "Debugging", pct: 66, tone: "trust" as const },
  ];
  const r = 30;
  const c = 2 * Math.PI * r;

  return (
    <Frame label="Graded against your rubric">
      <Stack>
        <motion.div variants={ITEM} className="flex items-center gap-5">
          <div className="relative grid h-[72px] w-[72px] shrink-0 place-items-center">
            <svg viewBox="0 0 72 72" className="absolute inset-0 -rotate-90" aria-hidden>
              <circle cx="36" cy="36" r={r} fill="none" strokeWidth="6" className="stroke-white/[0.08]" />
              <motion.circle
                cx="36"
                cy="36"
                r={r}
                fill="none"
                strokeWidth="6"
                strokeLinecap="round"
                className="stroke-trust"
                strokeDasharray={c}
                initial={reduce ? { strokeDashoffset: c * 0.22 } : { strokeDashoffset: c }}
                animate={{ strokeDashoffset: c * 0.22 }}
                transition={{ duration: 1.1, ease, delay: 0.15 }}
              />
            </svg>
            <span className="mono relative text-lg font-semibold text-white">78</span>
          </div>
          <div>
            <p className="text-[13px] font-semibold text-white">Overall score</p>
            <p className="mono mt-1 text-[10px] uppercase tracking-[0.12em] text-white/35">
              Graded by AI against this JD&apos;s rubric
            </p>
          </div>
        </motion.div>

        <ul className="mt-5 space-y-3">
          {dims.map((d, i) => (
            <motion.li key={d.label} variants={ITEM}>
              <div className="flex items-baseline justify-between gap-3">
                <span className="text-[12px] text-white/70">{d.label}</span>
                <span className="mono text-[11px] text-white/50">{d.pct}</span>
              </div>
              <div className="mt-1.5">
                <Meter pct={d.pct} tone={d.tone} delay={0.2 + i * 0.08} />
              </div>
            </motion.li>
          ))}
        </ul>

        <motion.div variants={ITEM} className="mt-5">
          <Row>
            <p className="mono flex items-center gap-2 text-[10px] uppercase tracking-[0.12em] text-verify">
              <LiveDot />
              Rule fired
            </p>
            <p className="mt-1 text-[12px] text-white/55">
              Application graded ≥ 70 → advanced to Shortlisted
            </p>
          </Row>
        </motion.div>
      </Stack>
    </Frame>
  );
}

/* ------------------------------------------------------------------ 07 */

const STAGES = ["Applied", "Graded", "Shortlisted", "L1", "L2", "Offer", "Hired"];
const AT = 2;

function DecideVisual() {
  const reduce = useReducedMotion();
  return (
    <Frame label="Candidate · decision">
      <Stack>
        <motion.ol variants={ITEM} className="flex flex-wrap items-center gap-1.5">
          {STAGES.map((s, i) => (
            <li key={s} className="flex items-center gap-1.5">
              <motion.span
                initial={reduce ? false : { opacity: 0, scale: 0.9 }}
                animate={{ opacity: 1, scale: 1 }}
                transition={{ duration: durations.base, ease, delay: 0.15 + i * 0.07 }}
                className={`mono rounded-full px-2.5 py-1 text-[10px] ${
                  i < AT
                    ? "bg-white/[0.06] text-white/40"
                    : i === AT
                      ? "bg-trust text-white"
                      : "border border-white/10 text-white/25"
                }`}
              >
                {s}
              </motion.span>
              {i < STAGES.length - 1 && <span aria-hidden className="h-px w-2 bg-white/10" />}
            </li>
          ))}
        </motion.ol>

        <motion.div variants={ITEM} className="mt-5">
          <Row>
            <p className="mono flex items-center gap-2 text-[10px] uppercase tracking-[0.12em] text-verify">
              <LiveDot />
              Contact unlocked
            </p>
            <p className="mt-1 text-[12px] leading-relaxed text-white/55">
              Details stay closed until Shortlisted. Until then you assess the work, not the
              person.
            </p>
          </Row>
        </motion.div>

        <div className="mt-3 grid grid-cols-2 gap-2">
          <motion.div variants={ITEM}>
            <Row>
              <p className="mono text-[10px] uppercase tracking-[0.12em] text-white/30">
                Next round
              </p>
              <p className="mt-1 text-[12px] text-white/70">SQL depth · send</p>
            </Row>
          </motion.div>
          <motion.div variants={ITEM}>
            <Row>
              <p className="mono text-[10px] uppercase tracking-[0.12em] text-white/30">On file</p>
              <p className="mt-1 text-[12px] text-white/70">Answers · rubric · timeline</p>
            </Row>
          </motion.div>
        </div>
      </Stack>
    </Frame>
  );
}

const VISUALS: Record<Step["visual"], () => React.ReactElement> = {
  workspace: WorkspaceVisual,
  jd: JdVisual,
  mock: MockVisual,
  rounds: RoundsVisual,
  publish: PublishVisual,
  grade: GradeVisual,
  decide: DecideVisual,
};

export function StepVisual({ kind }: { kind: Step["visual"] }) {
  const Visual = VISUALS[kind];
  return <Visual />;
}
