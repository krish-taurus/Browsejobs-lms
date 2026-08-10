"use client";

/**
 * Self-demonstrating devices for the /employers pipeline scenes. Each one plays
 * out the stage it illustrates — a JD filling itself in, a shortlist ranking,
 * a screening call being scored, an ATS board moving. Every demo freezes on its
 * finished state under `prefers-reduced-motion` so nothing loops or types.
 *
 * All candidate names, companies and numbers here are illustrative fixtures.
 */

import { useEffect, useRef, useState } from "react";
import { AnimatePresence, motion, useInView } from "framer-motion";
import { ease, durations, useMotionOk } from "./primitives";

/* ---------------------------------- shell ---------------------------------- */

/** The dark glass panel every demo sits in. */
function Device({
  children,
  accent,
  label,
  chip,
  className = "",
}: {
  children: React.ReactNode;
  accent: string;
  label: string;
  chip?: React.ReactNode;
  className?: string;
}) {
  return (
    <div
      className={`mx-auto w-full max-w-[420px] overflow-hidden rounded-panel border border-white/12 bg-white/[0.045] backdrop-blur-sm ${className}`}
      style={{ boxShadow: `0 40px 120px ${accent}33` }}
    >
      <div className="flex items-center justify-between border-b border-white/10 px-5 py-3">
        <div className="flex items-center gap-2">
          <span className="h-2 w-2 rounded-full" style={{ background: accent }} />
          <p className="kicker text-white/55" style={{ fontSize: "0.62rem" }}>
            {label}
          </p>
        </div>
        {chip}
      </div>
      <div className="p-5">{children}</div>
    </div>
  );
}

/** Advances 0..steps-1 on a timer while in view; jumps to the end if motion is off. */
function useSequence(steps: number, inView: boolean, everyMs = 900): number {
  const ok = useMotionOk();
  const [i, setI] = useState(0);
  useEffect(() => {
    if (!inView) return;
    if (!ok) {
      setI(steps - 1);
      return;
    }
    setI(0);
    const id = setInterval(() => setI((n) => (n >= steps - 1 ? n : n + 1)), everyMs);
    return () => clearInterval(id);
  }, [inView, steps, everyMs, ok]);
  return i;
}

function useSceneRef() {
  const ref = useRef<HTMLDivElement>(null);
  const inView = useInView(ref, { once: true, margin: "-15%" });
  return { ref, inView };
}

/* ----------------------------------- 01 JD --------------------------------- */

const JD_TEXT =
  "We're looking for a Senior QA Engineer in Bengaluru (hybrid) with 5+ years in test automation. Must have Selenium, Java and CI/CD experience. Immediate joiners preferred.";

const JD_FIELDS = [
  { k: "Role", v: "Senior QA Engineer" },
  { k: "Experience", v: "5–8 years" },
  { k: "Location", v: "Bengaluru · Hybrid" },
  { k: "Notice", v: "Immediate – 30 days" },
] as const;

const JD_SKILLS = [
  { s: "Selenium", must: true },
  { s: "Java", must: true },
  { s: "CI/CD", must: true },
  { s: "TestNG", must: false },
  { s: "API testing", must: false },
] as const;

export function JdDemo({ accent }: { accent: string }) {
  const { ref, inView } = useSceneRef();
  const ok = useMotionOk();
  const [typed, setTyped] = useState(ok ? "" : JD_TEXT);

  useEffect(() => {
    if (!inView || !ok) return;
    let i = 0;
    const id = setInterval(() => {
      i += 2;
      setTyped(JD_TEXT.slice(0, i));
      if (i >= JD_TEXT.length) clearInterval(id);
    }, 18);
    return () => clearInterval(id);
  }, [inView, ok]);

  const done = typed.length >= JD_TEXT.length;

  return (
    <div ref={ref}>
      <Device
        accent={accent}
        label="Job description"
        chip={
          <span
            className="kicker rounded-full px-2 py-0.5"
            style={{ fontSize: "0.58rem", background: `${accent}20`, color: accent }}
          >
            AI extract
          </span>
        }
      >
        <div className="rounded-input border border-white/10 bg-black/25 p-3.5">
          <p className="min-h-[76px] text-[12.5px] leading-relaxed text-white/75">
            {typed}
            {!done && ok && <span className="ml-0.5 inline-block h-3.5 w-[2px] animate-pulse bg-white/70 align-middle" />}
          </p>
        </div>

        <div className="mt-4 grid grid-cols-2 gap-2.5">
          {JD_FIELDS.map((f, i) => (
            <motion.div
              key={f.k}
              initial={ok ? { opacity: 0, y: 10 } : false}
              animate={done ? { opacity: 1, y: 0 } : ok ? { opacity: 0, y: 10 } : {}}
              transition={{ duration: durations.base, ease, delay: 0.12 * i }}
              className="rounded-input border border-white/10 bg-white/[0.05] px-3 py-2"
            >
              <p className="kicker text-white/40" style={{ fontSize: "0.55rem" }}>
                {f.k}
              </p>
              <p className="mt-0.5 text-[12px] font-semibold text-white/85">{f.v}</p>
            </motion.div>
          ))}
        </div>

        <div className="mt-4">
          <p className="kicker text-white/40" style={{ fontSize: "0.55rem" }}>
            Skills detected
          </p>
          <div className="mt-2 flex flex-wrap gap-1.5">
            {JD_SKILLS.map((s, i) => (
              <motion.span
                key={s.s}
                initial={ok ? { opacity: 0, scale: 0.8 } : false}
                animate={done ? { opacity: 1, scale: 1 } : ok ? { opacity: 0, scale: 0.8 } : {}}
                transition={{ duration: durations.base, ease, delay: 0.5 + 0.08 * i }}
                className="rounded-full border px-2.5 py-1 text-[11px] font-medium"
                style={
                  s.must
                    ? { background: `${accent}1f`, borderColor: `${accent}55`, color: "#fff" }
                    : { background: "rgba(255,255,255,0.04)", borderColor: "rgba(255,255,255,0.12)", color: "rgba(255,255,255,0.6)" }
                }
              >
                {s.s}
                {s.must && <span className="ml-1 opacity-70">must</span>}
              </motion.span>
            ))}
          </div>
        </div>
      </Device>
    </div>
  );
}

/* -------------------------------- 02 shortlist ------------------------------ */

const SHORTLIST = [
  { n: "R. Krishnan", m: 94, why: "8 yrs Selenium + Java, 15-day notice" },
  { n: "S. Varma", m: 88, why: "Strong CI/CD, needs hybrid confirmation" },
  { n: "A. Fernandes", m: 81, why: "Automation depth, 60-day notice" },
  { n: "P. Nair", m: 74, why: "Manual-heavy, thin on CI/CD" },
] as const;

export function ShortlistDemo({ accent }: { accent: string }) {
  const { ref, inView } = useSceneRef();
  const ok = useMotionOk();
  const shown = useSequence(SHORTLIST.length + 1, inView, 550);

  return (
    <div ref={ref}>
      <Device
        accent={accent}
        label="Ranked shortlist · Senior QA Engineer"
        chip={
          <span className="mono text-[10px] text-white/45">
            {Math.min(shown, SHORTLIST.length)}/{SHORTLIST.length}
          </span>
        }
      >
        <div className="space-y-2.5">
          {SHORTLIST.map((c, i) => (
            <motion.div
              key={c.n}
              initial={ok ? { opacity: 0, x: 24 } : false}
              animate={i < shown ? { opacity: 1, x: 0 } : ok ? { opacity: 0, x: 24 } : {}}
              transition={{ duration: durations.slow, ease }}
              className="rounded-card border border-white/10 bg-white/[0.05] p-3"
            >
              <div className="flex items-center justify-between gap-3">
                <div className="flex min-w-0 items-center gap-2.5">
                  <span
                    className="mono grid h-7 w-7 shrink-0 place-items-center rounded-full text-[10px] font-semibold"
                    style={{ background: `${accent}22`, color: accent }}
                  >
                    {i + 1}
                  </span>
                  <p className="truncate text-[12.5px] font-semibold text-white/90">{c.n}</p>
                </div>
                <span className="mono shrink-0 text-[12px] font-semibold" style={{ color: accent }}>
                  {c.m}%
                </span>
              </div>
              <div className="mt-2 h-1 overflow-hidden rounded-full bg-white/10">
                <motion.div
                  initial={ok ? { width: 0 } : false}
                  animate={i < shown ? { width: `${c.m}%` } : ok ? { width: 0 } : {}}
                  transition={{ duration: durations.slower, ease, delay: 0.1 }}
                  className="h-full rounded-full"
                  style={{ background: accent }}
                />
              </div>
              <p className="mt-2 text-[11px] leading-snug text-white/50">{c.why}</p>
            </motion.div>
          ))}
        </div>
      </Device>
    </div>
  );
}

/* ---------------------------------- 03 call -------------------------------- */

/** Mirrors what the Caller.Digital integration files on `lead_calls`. */
const CALL_CHECKS = [
  { k: "Notice period", v: "15 days" },
  { k: "Expected CTC", v: "₹24 LPA" },
  { k: "Outcome", v: "Interested" },
  { k: "Transcript & recording", v: "Attached" },
] as const;

export function CallDemo({ accent }: { accent: string }) {
  const { ref, inView } = useSceneRef();
  const ok = useMotionOk();
  const shown = useSequence(CALL_CHECKS.length + 1, inView, 800);

  return (
    <div ref={ref}>
      <Device
        accent={accent}
        label="AI screening call · R. Krishnan"
        chip={
          <span className="mono flex items-center gap-1.5 text-[10px] text-white/60">
            <span className={`h-1.5 w-1.5 rounded-full ${ok ? "animate-pulse" : ""}`} style={{ background: accent }} />
            04:12
          </span>
        }
      >
        <div className="flex h-14 items-end justify-center gap-[3px]">
          {Array.from({ length: 34 }).map((_, i) => {
            const peak = 22 + Math.abs(Math.sin(i * 1.7)) * 68;
            return (
              <motion.span
                key={i}
                animate={ok && inView ? { height: ["16%", `${peak}%`, "16%"] } : { height: `${peak * 0.5}%` }}
                transition={{ duration: 0.9 + (i % 5) * 0.14, repeat: Infinity, ease: "easeInOut", delay: (i % 7) * 0.08 }}
                className="w-[5px] rounded-full"
                style={{ background: accent, opacity: 0.35 + (i % 4) * 0.18 }}
              />
            );
          })}
        </div>

        <p className="mt-4 rounded-input bg-white/[0.06] px-3.5 py-2.5 text-[11.5px] italic leading-snug text-white/70">
          &ldquo;Before we go further — what&rsquo;s your notice period, and are you able to do three days a week in
          Bengaluru?&rdquo;
        </p>

        <div className="mt-4 space-y-2">
          {CALL_CHECKS.map((c, i) => (
            <motion.div
              key={c.k}
              initial={ok ? { opacity: 0, y: 8 } : false}
              animate={i < shown ? { opacity: 1, y: 0 } : ok ? { opacity: 0, y: 8 } : {}}
              transition={{ duration: durations.base, ease }}
              className="flex items-center justify-between rounded-input border border-white/10 bg-white/[0.04] px-3 py-2"
            >
              <span className="flex items-center gap-2 text-[11.5px] text-white/60">
                <svg viewBox="0 0 24 24" fill="none" className="h-3.5 w-3.5" style={{ color: accent }}>
                  <path
                    d="M5 12.5l4.5 4.5L19 7.5"
                    stroke="currentColor"
                    strokeWidth="2.6"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                  />
                </svg>
                {c.k}
              </span>
              <span className="mono text-[11px] font-semibold text-white/85">{c.v}</span>
            </motion.div>
          ))}
        </div>
      </Device>
    </div>
  );
}

/* --------------------------------- 04 rounds -------------------------------- */

const ROUNDS = [
  {
    tab: "L1",
    name: "Technical screen",
    q: "Design a test suite for a checkout flow that fails 1 in 200 times.",
    rubric: [
      { k: "Automation design", v: 88 },
      { k: "Debugging approach", v: 82 },
      { k: "Communication", v: 90 },
    ],
  },
  {
    tab: "L2",
    name: "Depth round",
    q: "Your Selenium grid is flaky under parallel load. Walk me through the fix.",
    rubric: [
      { k: "Framework depth", v: 84 },
      { k: "CI/CD fluency", v: 79 },
      { k: "Trade-off reasoning", v: 86 },
    ],
  },
  {
    tab: "Custom",
    name: "Your round, your rubric",
    q: "Hiring-manager question set — weighted the way your panel scores it.",
    rubric: [
      { k: "Domain fit", v: 91 },
      { k: "Ownership", v: 87 },
      { k: "Team signal", v: 83 },
    ],
  },
] as const;

export function RoundsDemo({ accent }: { accent: string }) {
  const { ref, inView } = useSceneRef();
  const ok = useMotionOk();
  const [tab, setTab] = useState(0);
  const [auto, setAuto] = useState(true);

  useEffect(() => {
    if (!inView || !ok || !auto) return;
    const id = setInterval(() => setTab((t) => (t + 1) % ROUNDS.length), 3200);
    return () => clearInterval(id);
  }, [inView, ok, auto]);

  const r = ROUNDS[tab];

  return (
    <div ref={ref}>
      <Device accent={accent} label="Interview rounds">
        <div className="flex gap-1.5 rounded-full border border-white/10 bg-black/25 p-1">
          {ROUNDS.map((x, i) => (
            <button
              key={x.tab}
              onClick={() => {
                setAuto(false);
                setTab(i);
              }}
              className="relative flex-1 rounded-full px-3 py-1.5 text-[11.5px] font-semibold transition-colors"
              style={{ color: i === tab ? "#fff" : "rgba(255,255,255,0.5)" }}
            >
              {i === tab && (
                <motion.span
                  layoutId="employer-round-tab"
                  transition={{ duration: durations.base, ease }}
                  className="absolute inset-0 rounded-full"
                  style={{ background: `${accent}30`, border: `1px solid ${accent}66` }}
                />
              )}
              <span className="relative">{x.tab}</span>
            </button>
          ))}
        </div>

        <AnimatePresence mode="wait">
          <motion.div
            key={r.tab}
            initial={ok ? { opacity: 0, y: 12 } : false}
            animate={{ opacity: 1, y: 0 }}
            exit={ok ? { opacity: 0, y: -12 } : undefined}
            transition={{ duration: durations.base, ease }}
          >
            <p className="mt-4 text-[12.5px] font-semibold text-white/90">{r.name}</p>
            <p className="mt-2 rounded-input bg-white/[0.06] px-3.5 py-2.5 text-[11.5px] italic leading-snug text-white/65">
              &ldquo;{r.q}&rdquo;
            </p>
            <div className="mt-4 space-y-2.5">
              {r.rubric.map((s, i) => (
                <div key={s.k}>
                  <div className="mb-1 flex justify-between text-[11px]">
                    <span className="text-white/55">{s.k}</span>
                    <span className="mono font-semibold text-white/85">{s.v}</span>
                  </div>
                  <div className="h-1 overflow-hidden rounded-full bg-white/10">
                    <motion.div
                      initial={ok ? { width: 0 } : false}
                      animate={{ width: `${s.v}%` }}
                      transition={{ duration: durations.slower, ease, delay: 0.08 * i }}
                      className="h-full rounded-full"
                      style={{ background: accent }}
                    />
                  </div>
                </div>
              ))}
            </div>
          </motion.div>
        </AnimatePresence>
      </Device>
    </div>
  );
}

/* ---------------------------------- 05 ATS --------------------------------- */

/** The shipped pipeline stages (PRD-E F5), trimmed to what fits the panel. */
const ATS_COLUMNS = ["Graded", "Shortlisted", "L1", "L2"] as const;

/** Each card's column index at t=0 and where it lands — the board moves once. */
const ATS_CARDS = [
  { n: "R. Krishnan", from: 0, to: 3 },
  { n: "S. Varma", from: 0, to: 2 },
  { n: "A. Fernandes", from: 0, to: 1 },
  { n: "P. Nair", from: 0, to: 0 },
  { n: "M. Joseph", from: 0, to: 1 },
] as const;

export function AtsDemo({ accent }: { accent: string }) {
  const { ref, inView } = useSceneRef();
  const ok = useMotionOk();
  const [moved, setMoved] = useState(!ok);

  useEffect(() => {
    if (!inView || !ok) return;
    const id = setTimeout(() => setMoved(true), 700);
    return () => clearTimeout(id);
  }, [inView, ok]);

  return (
    <div ref={ref}>
      <Device accent={accent} label="Pipeline board · 5 candidates">
        <div className="grid grid-cols-4 gap-1.5">
          {ATS_COLUMNS.map((col, ci) => (
            <div key={col} className="min-h-[168px] rounded-card border border-white/10 bg-black/20 p-1.5">
              <p className="kicker mb-2 truncate text-white/40" style={{ fontSize: "0.5rem" }}>
                {col}
              </p>
              <div className="space-y-1.5">
                {ATS_CARDS.filter((c) => (moved ? c.to : c.from) === ci).map((c) => (
                  <motion.div
                    key={c.n}
                    layout
                    layoutId={`ats-${c.n}`}
                    transition={{ duration: durations.slower, ease }}
                    className="rounded-[8px] border border-white/12 bg-white/[0.07] px-1.5 py-1.5"
                    style={ci === 3 ? { borderColor: `${accent}66`, background: `${accent}1f` } : undefined}
                  >
                    <p className="truncate text-[9.5px] font-semibold leading-tight text-white/85">{c.n}</p>
                    <div className="mt-1 flex gap-0.5">
                      {[0, 1, 2].map((d) => (
                        <span
                          key={d}
                          className="h-0.5 flex-1 rounded-full"
                          style={{
                            background: d <= (moved ? c.to : c.from) - 1 ? accent : "rgba(255,255,255,0.15)",
                          }}
                        />
                      ))}
                    </div>
                  </motion.div>
                ))}
              </div>
            </div>
          ))}
        </div>

        <div className="mt-4 flex items-center justify-between rounded-input border border-white/10 bg-white/[0.04] px-3 py-2.5">
          <span className="text-[11px] text-white/55">Slowest stage</span>
          <span className="mono text-[11px] font-semibold" style={{ color: accent }}>
            L2 · panel availability
          </span>
        </div>
      </Device>
    </div>
  );
}

/* ---------------------------------- 06 BGV --------------------------------- */

/** Interview evidence + the proctoring record (PRD-E F4) — not a BGV report. */
const BGV_CHECKS = [
  { k: "Face match across rounds", v: "Consistent, all 3 rounds", flag: false },
  { k: "Rubric coverage", v: "Every dimension scored", flag: false },
  { k: "Transcript & replay", v: "Attached, seekable", flag: false },
  { k: "Window switches", v: "Review — 2 events in L2", flag: true },
] as const;

export function BgvDemo({ accent }: { accent: string }) {
  const { ref, inView } = useSceneRef();
  const ok = useMotionOk();
  const shown = useSequence(BGV_CHECKS.length + 1, inView, 750);

  return (
    <div ref={ref}>
      <Device
        accent={accent}
        label="Evidence & integrity · R. Krishnan"
        chip={
          <span className="mono text-[10px] text-white/45">
            {Math.min(shown, BGV_CHECKS.length)}/{BGV_CHECKS.length}
          </span>
        }
      >
        <div className="space-y-2">
          {BGV_CHECKS.map((c, i) => {
            const on = i < shown;
            const tint = c.flag ? "var(--bj-amber)" : "var(--bj-verify)";
            return (
              <motion.div
                key={c.k}
                initial={ok ? { opacity: 0, y: 8 } : false}
                animate={on ? { opacity: 1, y: 0 } : ok ? { opacity: 0, y: 8 } : {}}
                transition={{ duration: durations.base, ease }}
                className="flex items-start gap-2.5 rounded-input border border-white/10 bg-white/[0.04] px-3 py-2.5"
                style={c.flag ? { borderColor: "rgba(245,166,35,0.4)" } : undefined}
              >
                <span
                  className="mt-0.5 grid h-4 w-4 shrink-0 place-items-center rounded-full text-[9px] font-bold text-ink"
                  style={{ background: tint }}
                >
                  {c.flag ? "!" : "✓"}
                </span>
                <div className="min-w-0">
                  <p className="text-[11.5px] font-semibold text-white/85">{c.k}</p>
                  <p className="text-[11px] leading-snug text-white/50">{c.v}</p>
                </div>
              </motion.div>
            );
          })}
        </div>
        <p className="mt-3 text-[10.5px] leading-snug text-white/40">
          A flag never auto-rejects. It surfaces the evidence and waits for a human.
        </p>
      </Device>
    </div>
  );
}

/* -------------------------------- 07 report -------------------------------- */

const REPORT_BLOCKS = [
  { k: "Screening call", v: "Cleared · transcript attached" },
  { k: "L1 technical", v: "86 / 100" },
  { k: "L2 depth", v: "83 / 100" },
  { k: "Proctoring", v: "1 item to review" },
] as const;

export function ReportDemo({ accent }: { accent: string }) {
  const { ref, inView } = useSceneRef();
  const ok = useMotionOk();
  const shown = useSequence(REPORT_BLOCKS.length + 2, inView, 600);

  return (
    <div ref={ref}>
      <Device
        accent={accent}
        label="Candidate brief → HR"
        chip={
          <motion.span
            initial={ok ? { opacity: 0, scale: 0.8 } : false}
            animate={shown > REPORT_BLOCKS.length ? { opacity: 1, scale: 1 } : ok ? { opacity: 0, scale: 0.8 } : {}}
            transition={{ duration: durations.base, ease }}
            className="kicker rounded-full px-2 py-0.5"
            style={{ fontSize: "0.55rem", background: `${accent}22`, color: accent }}
          >
            Sent
          </motion.span>
        }
      >
        <div className="rounded-card border border-white/10 bg-black/25 p-4">
          <p className="text-[13px] font-semibold text-white/90">R. Krishnan</p>
          <p className="mono mt-0.5 text-[10.5px] text-white/45">Senior QA Engineer · 4 rounds conducted</p>

          <div className="mt-3.5 space-y-1.5">
            {REPORT_BLOCKS.map((b, i) => (
              <motion.div
                key={b.k}
                initial={ok ? { opacity: 0, x: -10 } : false}
                animate={i < shown ? { opacity: 1, x: 0 } : ok ? { opacity: 0, x: -10 } : {}}
                transition={{ duration: durations.base, ease }}
                className="flex items-center justify-between border-b border-white/[0.07] pb-1.5 last:border-0"
              >
                <span className="text-[11.5px] text-white/55">{b.k}</span>
                <span className="mono text-[11px] font-semibold text-white/85">{b.v}</span>
              </motion.div>
            ))}
          </div>

          <motion.div
            initial={ok ? { opacity: 0, y: 10 } : false}
            animate={shown > REPORT_BLOCKS.length ? { opacity: 1, y: 0 } : ok ? { opacity: 0, y: 10 } : {}}
            transition={{ duration: durations.slow, ease }}
            className="mt-4 rounded-input px-3 py-2.5"
            style={{ background: `${accent}18`, border: `1px solid ${accent}44` }}
          >
            <p className="kicker" style={{ fontSize: "0.55rem", color: accent }}>
              Recommendation
            </p>
            <p className="mt-1 text-[11.5px] leading-snug text-white/75">
              Progress to panel. Watch the flagged L2 segment before you decide.
            </p>
          </motion.div>
        </div>

        <p className="mt-3 text-[10.5px] leading-snug text-white/40">
          Your panel reads this before the first interview is booked.
        </p>
      </Device>
    </div>
  );
}

/* --------------------------------- registry -------------------------------- */

export const DEMOS = {
  jd: JdDemo,
  shortlist: ShortlistDemo,
  call: CallDemo,
  rounds: RoundsDemo,
  ats: AtsDemo,
  bgv: BgvDemo,
  report: ReportDemo,
} as const;
