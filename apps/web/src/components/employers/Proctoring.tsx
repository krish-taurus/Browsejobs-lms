"use client";

import { useEffect, useState } from "react";
import { AnimatePresence, motion, useReducedMotion } from "framer-motion";
import { durations, ease } from "@/lib/motion";
import { Aurora, InkLabel, InkPanel, LiveDot, Section, SectionHead } from "@/components/employers/ui";
import { proctoring, proctoringNext } from "@/content/employers";

/**
 * Session integrity.
 *
 * The monitor on the right streams events as the section is read, because
 * "we watch the session" is a claim and a moving log is a demonstration. The
 * events are a fixed, fictional script on a loop — labelled as such — not a
 * simulation pretending to be somebody's live attempt.
 */

const EVENTS = [
  { t: "00:00", label: "Session opened", tone: "muted" },
  { t: "00:00", label: "Camera on · recording started", tone: "verify" },
  { t: "00:00", label: "Window focus locked", tone: "verify" },
  { t: "04:12", label: "Q3 answered · typed, 2 min 41 s", tone: "muted" },
  { t: "09:38", label: "Focus lost · 4 s", tone: "amber" },
  { t: "09:42", label: "Focus returned", tone: "verify" },
  { t: "17:05", label: "Paste blocked · Q7", tone: "amber" },
  { t: "22:40", label: "Out of frame · 2 s", tone: "amber" },
  { t: "28:19", label: "Q11 answered · typed, 3 min 08 s", tone: "muted" },
  { t: "44:00", label: "Session submitted · recording stored", tone: "verify" },
];

const TONE: Record<string, string> = {
  verify: "text-verify",
  amber: "text-amber",
  muted: "text-white/40",
};

const WINDOW = 7;

export function Proctoring() {
  const reduce = useReducedMotion();
  const [head, setHead] = useState(0);

  useEffect(() => {
    if (reduce) return;
    const t = window.setInterval(() => setHead((i) => (i + 1) % EVENTS.length), 2200);
    return () => window.clearInterval(t);
  }, [reduce]);

  // A contiguous, ascending slice ending at `head`, shown newest-first. A ring
  // buffer over the whole list was simpler but printed 44:00 above 00:00 — a
  // log whose timestamps run backwards reads as broken, not as live.
  const shown = EVENTS.slice(Math.max(0, head - WINDOW + 1), head + 1)
    .slice()
    .reverse();

  return (
    <Section id="proctoring" className="overflow-hidden">
      <Aurora />

      <SectionHead
        kicker={proctoring.kicker}
        title={proctoring.title}
        highlight={proctoring.highlight}
        sub={proctoring.sub}
        tone="verify"
      />

      <div className="mt-12 grid gap-4 lg:grid-cols-[1.05fr_1fr]">
        {/* What is recorded ------------------------------------------- */}
        <div className="grid gap-3 sm:grid-cols-2">
          {proctoring.signals.map((s, i) => (
            <motion.div
              key={s.key}
              initial={reduce ? false : { opacity: 0, y: 16 }}
              whileInView={{ opacity: 1, y: 0 }}
              viewport={{ once: true, margin: "-60px" }}
              transition={{ duration: durations.slow, ease, delay: (i % 2) * 0.06 }}
              className="h-full"
            >
              <InkPanel accent="verify" glow={false} className="h-full p-5">
                <p className="mono flex items-center gap-2 text-[10px] uppercase tracking-[0.14em] text-verify">
                  <LiveDot />
                  Recorded
                </p>
                <h3 className="mt-3 text-[15px] font-semibold text-white">{s.label}</h3>
                <p className="mt-1.5 text-[13px] leading-relaxed text-white/55">{s.body}</p>
              </InkPanel>
            </motion.div>
          ))}
        </div>

        {/* The monitor ------------------------------------------------- */}
        {/* Stretches to the signal grid beside it rather than sitting short
            at the top of a tall column. */}
        <InkPanel accent="verify" live className="flex h-full flex-col p-5 md:p-6">
          <div className="flex items-center justify-between">
            <InkLabel>Session monitor</InkLabel>
            <span className="mono inline-flex items-center gap-2 text-[10px] uppercase tracking-[0.14em] text-white/40">
              <LiveDot />
              recording
            </span>
          </div>

          <div className="relative mt-5 min-h-[228px] flex-1 overflow-hidden">
            <AnimatePresence initial={false} mode="popLayout">
              {shown.map((e, i) => (
                <motion.div
                  key={`${e.t}-${e.label}`}
                  layout
                  initial={reduce ? false : { opacity: 0, y: 20 }}
                  animate={{ opacity: i === 0 ? 1 : 1 - i * 0.16, y: 0 }}
                  exit={reduce ? undefined : { opacity: 0, y: -14 }}
                  transition={{ duration: durations.slow, ease }}
                  className="mb-2 flex items-baseline gap-3 rounded-2xl border border-white/[0.08] bg-white/[0.03] px-4 py-2.5"
                >
                  <span className="mono shrink-0 text-[11px] text-white/30">{e.t}</span>
                  <span className={`text-[12px] ${TONE[e.tone] ?? TONE.muted}`}>{e.label}</span>
                </motion.div>
              ))}
            </AnimatePresence>
            <span
              aria-hidden
              className="pointer-events-none absolute inset-x-0 bottom-0 h-12 bg-gradient-to-t from-deck-card to-transparent"
            />
          </div>

          <p className="mt-4 shrink-0 border-t border-white/[0.07] pt-4 text-[11px] leading-relaxed text-white/35">
            Illustrative event stream from a fictional session.
          </p>
        </InkPanel>
      </div>

      <motion.p
        initial={reduce ? false : { opacity: 0, y: 16 }}
        whileInView={{ opacity: 1, y: 0 }}
        viewport={{ once: true, margin: "-60px" }}
        transition={{ duration: durations.slow, ease }}
        className="mt-8 max-w-3xl border-l-2 border-verify pl-5 text-[15px] leading-relaxed text-white/60"
      >
        {proctoring.closer}
      </motion.p>

      {/* In build. Deliberately not green and deliberately not mixed in with
          the live signals above — an employer must be able to tell at a
          glance what runs on their next interview. */}
      <motion.div
        initial={reduce ? false : { opacity: 0, y: 16 }}
        whileInView={{ opacity: 1, y: 0 }}
        viewport={{ once: true, margin: "-60px" }}
        transition={{ duration: durations.slow, ease }}
        className="mt-4"
      >
        <InkPanel accent="employer" glow={false} className="border-dashed p-6 md:p-8">
          <div className="flex flex-wrap items-baseline justify-between gap-3">
            <p className="mono text-[10px] uppercase tracking-[0.16em] text-white/45">
              {proctoringNext.kicker}
            </p>
            <span className="mono rounded-full border border-white/15 px-3 py-1 text-[10px] uppercase tracking-[0.14em] text-white/45">
              Phase 2
            </span>
          </div>
          <h3 className="display mt-3 text-xl text-white">{proctoringNext.title}</h3>
          <p className="mt-2 max-w-2xl text-[14px] leading-relaxed text-white/50">
            {proctoringNext.sub}
          </p>

          <ul className="mt-6 grid gap-4 md:grid-cols-2">
            {proctoringNext.items.map((item) => (
              <li key={item.label} className="border-l-2 border-white/15 pl-4">
                <h4 className="text-[15px] font-semibold text-white/80">{item.label}</h4>
                <p className="mt-1.5 text-[13px] leading-relaxed text-white/50">{item.body}</p>
              </li>
            ))}
          </ul>
        </InkPanel>
      </motion.div>
    </Section>
  );
}
