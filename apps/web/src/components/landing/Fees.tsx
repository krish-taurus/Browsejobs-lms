"use client";

import { useState } from "react";
import { motion, useReducedMotion } from "framer-motion";
import { Disclaimer } from "@/components/brand/Disclaimer";
import { Kicker } from "@/components/brand/Kicker";
import { BookCta } from "@/components/landing/BookCta";
import { ScrollReveal } from "@/components/motion/ScrollReveal";
import { durations, ease } from "@/lib/motion";
import { fees } from "@/content/landing";

const INCLUDED = [
  "Live instructor-led classes",
  "All class recordings, one year",
  "AI tutor, unlimited",
  "MCQ tests & mastery tracking",
  "Your first comprehensive CV",
  "Base mock-interview quota",
  "Student support desk",
];

const EXTRAS = [
  { label: "Extra CV credits", price: "₹99 / 3" },
  { label: "Voice mock interview", price: "₹249 · ₹599 / 3" },
  { label: "Extra 1:1 mentor session", price: "₹499" },
  { label: "Career+ (post-placement)", price: "₹499 / mo" },
];

const rupee = (n: number) => "₹" + Math.round(n).toLocaleString("en-IN");

/**
 * The fee model, rendered exactly per spec §3.6. Two parts: registration
 * (₹30,000, optional 3×₹10,000 EMI) and the placement fee (first 3 months'
 * CTC, 6 EMIs, ₹30k adjusted) with an interactive worked example. Figures are
 * illustrative — real amounts are always server-owned at checkout.
 */
export function Fees() {
  const [emi, setEmi] = useState(false);
  const [lpa, setLpa] = useState(12);
  const reduce = useReducedMotion();

  const annual = lpa * 100000;
  const threeMonths = annual / 4; // 3/12 of CTC
  const afterAdjustment = Math.max(0, threeMonths - fees.placement.adjusted);
  const perEmi = afterAdjustment / fees.placement.emis;

  const schedule = emi
    ? Array.from({ length: fees.registrationEmi.months }, (_, i) => ({
        label: i === 0 ? "Today" : `Month ${i + 1}`,
        amount: fees.registrationEmi.amount,
      }))
    : [{ label: "Today", amount: fees.registration }];

  return (
    <section id="fees" className="bg-white">
      <div className="mx-auto max-w-6xl px-5 py-20">
        <ScrollReveal>
          <Kicker>The fee structure</Kicker>
          <h2 className="display mt-3 max-w-3xl text-3xl text-ink md:text-5xl">
            Two fees. Both in writing. One only after you&apos;re hired.
          </h2>
        </ScrollReveal>

        <div className="mt-10 grid gap-6 lg:grid-cols-2">
          {/* Registration */}
          <ScrollReveal>
            <div className="flex h-full flex-col rounded-[22px] border border-line bg-paper p-8">
              <p className="kicker text-trust">01 · Registration</p>
              <div className="mt-4 flex items-baseline gap-3">
                <span className="mono text-4xl font-semibold text-ink">
                  {emi ? rupee(fees.registrationEmi.amount) : rupee(fees.registration)}
                </span>
                {emi && <span className="text-muted">× {fees.registrationEmi.months} months</span>}
              </div>
              <p className="mt-2 text-sm text-muted">
                Payable only <span className="font-semibold text-ink">after</span>{" "}
                the free masterclass and the free 7-hour bootcamp.
              </p>

              <div className="mt-5 inline-flex self-start rounded-full border border-line bg-white p-1">
                <button
                  onClick={() => setEmi(false)}
                  className={`rounded-full px-4 py-1.5 text-sm font-semibold transition-colors ${
                    !emi ? "bg-ink text-white" : "text-muted"
                  }`}
                >
                  One payment
                </button>
                <button
                  onClick={() => setEmi(true)}
                  className={`rounded-full px-4 py-1.5 text-sm font-semibold transition-colors ${
                    emi ? "bg-ink text-white" : "text-muted"
                  }`}
                >
                  EMI · {fees.registrationEmi.months} × {rupee(fees.registrationEmi.amount)}
                </button>
              </div>

              <div className="mt-5 space-y-2">
                {schedule.map((row, i) => (
                  <motion.div
                    key={row.label}
                    initial={reduce ? false : { opacity: 0, x: -8 }}
                    animate={{ opacity: 1, x: 0 }}
                    transition={{ duration: durations.base, ease, delay: i * 0.05 }}
                    className="flex items-center justify-between rounded-[10px] border border-line bg-white px-4 py-2.5"
                  >
                    <span className="text-sm text-muted">{row.label}</span>
                    <span className="mono text-sm font-semibold text-ink">{rupee(row.amount)}</span>
                  </motion.div>
                ))}
              </div>

              <div className="mt-5 flex items-center gap-2 rounded-[10px] bg-verify-bg px-4 py-3">
                <svg viewBox="0 0 20 20" className="h-5 w-5 shrink-0" fill="none">
                  <circle cx="10" cy="10" r="9" stroke="var(--bj-verify)" strokeWidth="1.6" />
                  <path d="M6.5 10.5l2.3 2.3L13.5 8" stroke="var(--bj-verify)" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" />
                </svg>
                <p className="text-sm text-ink">
                  <span className="font-semibold">{fees.guaranteeDays}-day money-back guarantee</span>{" "}
                  — any reason, full refund, in writing.
                </p>
              </div>
            </div>
          </ScrollReveal>

          {/* Placement fee + worked example */}
          <ScrollReveal delay={0.08}>
            <div className="flex h-full flex-col rounded-[22px] border border-line bg-paper p-8">
              <p className="kicker text-trust">02 · Placement fee</p>
              <p className="mt-4 text-ink">
                Your first{" "}
                <span className="mono font-semibold">{fees.placement.monthsOfCtc} months&apos; CTC</span>
                , due <span className="font-semibold">only after you accept an offer</span> — paid as{" "}
                <span className="mono font-semibold">{fees.placement.emis} monthly EMIs</span> from your
                new salary. Your {rupee(fees.placement.adjusted)} registration is adjusted inside it.
              </p>

              <div className="mt-6 rounded-[14px] border border-line bg-white p-5">
                <div className="flex items-center justify-between">
                  <p className="kicker text-muted">Worked example</p>
                  <span className="mono text-sm font-semibold text-ink">
                    ₹{lpa} LPA offer
                  </span>
                </div>
                <input
                  type="range"
                  min={4}
                  max={30}
                  step={1}
                  value={lpa}
                  onChange={(e) => setLpa(Number(e.target.value))}
                  aria-label="Example offer CTC in lakhs per annum"
                  className="mt-3 w-full accent-[var(--bj-trust)]"
                />
                <div className="mono mt-4 space-y-2 text-sm">
                  <div className="flex justify-between text-muted">
                    <span>{fees.placement.monthsOfCtc} months&apos; CTC</span>
                    <span>{rupee(threeMonths)}</span>
                  </div>
                  <div className="flex justify-between text-muted">
                    <span>− registration already paid</span>
                    <span>− {rupee(fees.placement.adjusted)}</span>
                  </div>
                  <div className="flex justify-between border-t border-line pt-2 font-semibold text-ink">
                    <span>Placement fee</span>
                    <span>{rupee(afterAdjustment)}</span>
                  </div>
                  <div className="flex justify-between text-trust">
                    <span>= {fees.placement.emis} EMIs of</span>
                    <span className="font-semibold">{rupee(perEmi)} / month</span>
                  </div>
                </div>
              </div>

              <div className="mt-4">
                <Disclaimer />
              </div>

              <div className="mt-auto pt-6">
                <BookCta className="w-full py-3 text-center" />
              </div>
            </div>
          </ScrollReveal>
        </div>

        {/* Included vs paid — the freemium guardrail, in the open. */}
        <ScrollReveal delay={0.05}>
          <div className="mt-6 grid gap-6 rounded-[22px] border border-line bg-paper p-8 md:grid-cols-2 md:p-10">
            <div>
              <Kicker tone="verify">Included, forever</Kicker>
              <ul className="mt-5 space-y-3">
                {INCLUDED.map((f) => (
                  <li key={f} className="flex items-center gap-2.5 text-sm text-ink">
                    <span className="grid h-4 w-4 shrink-0 place-items-center rounded-full bg-verify-bg">
                      <span className="h-1.5 w-1.5 rounded-full bg-verify" />
                    </span>
                    {f}
                  </li>
                ))}
              </ul>
            </div>
            <div className="md:border-l md:border-line md:pl-10">
              <Kicker tone="muted">Optional extras</Kicker>
              <ul className="mt-5 divide-y divide-line">
                {EXTRAS.map((e) => (
                  <li key={e.label} className="flex items-center justify-between py-2.5 text-sm">
                    <span className="text-ink">{e.label}</span>
                    <span className="mono text-muted">{e.price}</span>
                  </li>
                ))}
              </ul>
              <p className="mt-4 text-xs text-muted">
                Everything needed for the placement promise is included. Charges apply only to
                extras beyond generous quotas.
              </p>
            </div>
          </div>
        </ScrollReveal>
      </div>
    </section>
  );
}
