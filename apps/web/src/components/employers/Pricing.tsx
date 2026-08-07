"use client";

import { useState } from "react";
import { motion, useReducedMotion } from "framer-motion";
import { durations, ease } from "@/lib/motion";
import { ScrollReveal } from "@/components/motion/ScrollReveal";
import { EmployerCta } from "@/components/employers/EmployerCta";
import { Aurora, InkPanel, Section, SectionHead } from "@/components/employers/ui";
import { pricing } from "@/content/employers";

/**
 * What the platform costs once the free six months are up.
 *
 * The comparator is here because the two models cross over: per-interview is
 * cheaper for a wide funnel, per-hire for a tight one, and a page that only
 * printed both rates would leave every visitor doing that arithmetic in their
 * head — or assuming the more expensive one applies to them. It runs on the
 * visitor's own numbers and says plainly that it is an estimate.
 */

const RUPEE = new Intl.NumberFormat("en-IN", {
  style: "currency",
  currency: "INR",
  maximumFractionDigits: 0,
});

/** A labelled range input. Native, so it is keyboard- and screen-reader-usable. */
function Dial({
  id,
  label,
  value,
  display,
  min,
  max,
  step = 1,
  onChange,
}: {
  id: string;
  label: string;
  value: number;
  display: string;
  min: number;
  max: number;
  step?: number;
  onChange: (n: number) => void;
}) {
  return (
    <div>
      <div className="flex items-baseline justify-between gap-3">
        <label htmlFor={id} className="text-[13px] text-white/70">
          {label}
        </label>
        <span className="mono text-[13px] font-semibold text-white">{display}</span>
      </div>
      <input
        id={id}
        type="range"
        min={min}
        max={max}
        step={step}
        value={value}
        onChange={(e) => onChange(Number(e.target.value))}
        className="mt-2 h-1.5 w-full cursor-pointer appearance-none rounded-full bg-white/10 accent-[var(--bj-trust)]"
      />
    </div>
  );
}

export function Pricing() {
  const reduce = useReducedMotion();
  const [hires, setHires] = useState<number>(pricing.defaults.hires);
  const [perHire, setPerHire] = useState<number>(pricing.defaults.interviewsPerHire);
  const [ctcLakh, setCtcLakh] = useState<number>(pricing.defaults.ctcLakh);

  const interviewCost = hires * perHire * pricing.perInterview;
  const hireCost = Math.round(hires * ctcLakh * 100_000 * (pricing.hirePct / 100));
  const peak = Math.max(interviewCost, hireCost, 1);
  const cheaper = interviewCost === hireCost ? null : interviewCost < hireCost ? "saas" : "agency";

  return (
    <Section id="pricing" className="overflow-hidden">
      <Aurora />

      <SectionHead
        kicker={pricing.kicker}
        title={pricing.title}
        highlight={pricing.highlight}
        sub={pricing.sub}
        tone="employer"
      />

      <div className="mt-12 grid gap-4 lg:grid-cols-2">
        {pricing.models.map((m, i) => (
          <ScrollReveal key={m.key} delay={i * 0.06} className="h-full">
            <InkPanel accent={m.accent} className="h-full p-6 md:p-8">
              <div className="flex items-center justify-between gap-3">
                <p
                  className={`kicker ${m.accent === "employer" ? "text-employer" : "text-trust"}`}
                >
                  {m.name}
                </p>
                <span className="mono rounded-full border border-white/12 px-3 py-1 text-[10px] uppercase tracking-[0.14em] text-white/45">
                  {m.tag}
                </span>
              </div>

              <p className="display mt-5 text-white">
                <span className="mono text-4xl font-bold md:text-5xl">
                  {m.key === "agency" ? `${m.amount}%` : RUPEE.format(m.amount)}
                </span>
              </p>
              <p className="mono mt-2 text-[11px] uppercase tracking-[0.14em] text-white/40">
                {m.key === "agency" ? "of CTC, flat" : m.unit}
              </p>

              <p className="mt-5 text-[14px] leading-relaxed text-white/60">{m.body}</p>

              <div className="mt-5 space-y-4 border-t border-white/[0.07] pt-5">
                <p className="text-[13px] leading-relaxed text-white/50">
                  <span className="mono block text-[10px] uppercase tracking-[0.14em] text-white/35">
                    On cost
                  </span>
                  {m.good}
                </p>
                <p className="text-[13px] leading-relaxed text-white/50">
                  <span className="mono block text-[10px] uppercase tracking-[0.14em] text-white/35">
                    On risk
                  </span>
                  {m.risk}
                </p>
              </div>
            </InkPanel>
          </ScrollReveal>
        ))}
      </div>

      {/* Comparator ---------------------------------------------------- */}
      <ScrollReveal delay={0.12}>
        <InkPanel accent="trust" className="mt-4 p-6 md:p-8">
          <p className="kicker text-trust">Work it out on your numbers</p>
          {/* Not "which one is cheaper" — the panel underneath says the
              per-interview model almost always is, and a heading that teases
              a toss-up the numbers do not support is the kind of small
              dishonesty this page exists to avoid. */}
          <h3 className="display mt-3 text-xl text-white md:text-2xl">
            What each model would cost you
          </h3>

          <div className="mt-8 grid gap-8 lg:grid-cols-2 lg:gap-12">
            <div className="space-y-6">
              <Dial
                id="price-hires"
                label="Hires you expect"
                value={hires}
                display={String(hires)}
                min={1}
                max={25}
                onChange={setHires}
              />
              <Dial
                id="price-per-hire"
                label="Interviews per hire"
                value={perHire}
                display={String(perHire)}
                min={3}
                max={60}
                onChange={setPerHire}
              />
              <Dial
                id="price-ctc"
                label="Average CTC"
                value={ctcLakh}
                display={`₹${ctcLakh}L`}
                min={3}
                max={40}
                onChange={setCtcLakh}
              />
            </div>

            <div className="space-y-5">
              {[
                {
                  key: "saas",
                  name: "Per interview",
                  cost: interviewCost,
                  detail: `${hires * perHire} interviews × ₹${pricing.perInterview}`,
                  bar: "bg-trust",
                },
                {
                  key: "agency",
                  name: "Per hire",
                  cost: hireCost,
                  detail: `${hires} × ₹${ctcLakh}L × ${pricing.hirePct}%`,
                  bar: "bg-employer",
                },
              ].map((row) => (
                <div key={row.key}>
                  <div className="flex items-baseline justify-between gap-3">
                    <span className="flex items-baseline gap-2 text-[13px] text-white/70">
                      {row.name}
                      {cheaper === row.key && (
                        <span className="mono rounded-full border border-verify/40 bg-verify/10 px-2 py-0.5 text-[9px] uppercase tracking-[0.12em] text-verify">
                          lower cost
                        </span>
                      )}
                    </span>
                    <span className="mono text-lg font-semibold text-white">
                      {RUPEE.format(row.cost)}
                    </span>
                  </div>
                  <span className="mt-2 block h-2 overflow-hidden rounded-full bg-white/[0.07]">
                    <motion.span
                      className={`block h-full rounded-full ${row.bar}`}
                      animate={{ width: `${(row.cost / peak) * 100}%` }}
                      initial={false}
                      transition={reduce ? { duration: 0 } : { duration: durations.slow, ease }}
                    />
                  </span>
                  <p className="mono mt-1.5 text-[10px] uppercase tracking-[0.1em] text-white/30">
                    {row.detail}
                  </p>
                </div>
              ))}

              <p className="border-t border-white/[0.07] pt-5 text-[13px] leading-relaxed text-white/55">
                {pricing.honesty}
              </p>
              <p className="text-[12px] leading-relaxed text-white/40">{pricing.note}</p>
            </div>
          </div>

          <div className="mt-8 flex flex-wrap items-center gap-4 border-t border-white/[0.07] pt-8">
            <EmployerCta>Start free for 6 months</EmployerCta>
            <p className="text-[13px] text-white/45">
              You choose a model at the end of the six months. Neither one starts on its own.
            </p>
          </div>
        </InkPanel>
      </ScrollReveal>
    </Section>
  );
}
