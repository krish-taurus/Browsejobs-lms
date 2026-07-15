"use client";

import { useState } from "react";
import { motion, useReducedMotion } from "framer-motion";
import { durations, ease } from "@/lib/motion";
import { included, pricing } from "@/content/landing";

const rupee = (n: number) =>
  "₹" + Math.round(n).toLocaleString("en-IN");

export function Pricing() {
  const [emi, setEmi] = useState(false);
  const reduce = useReducedMotion();

  const perMonth = Math.ceil(pricing.fee / pricing.emiMonths);
  const schedule = emi
    ? Array.from({ length: pricing.emiMonths }, (_, i) => ({
        label: i === 0 ? "Today" : `Month ${i + 1}`,
        amount: perMonth,
      }))
    : [{ label: "Today", amount: pricing.fee }];

  return (
    <section id="pricing" className="bg-white">
      <div className="mx-auto max-w-6xl px-5 py-20">
        <p className="kicker text-trust">Transparent pricing</p>
        <h2 className="display mt-3 max-w-2xl text-3xl text-ink md:text-4xl">
          One fee. Shown in full. Pay once or over months.
        </h2>

        <div className="mt-10 grid gap-8 lg:grid-cols-[1fr_1.1fr]">
          {/* Price card + toggle + schedule */}
          <div className="rounded-3xl border border-line bg-paper p-8">
            <div className="inline-flex rounded-full border border-line bg-white p-1">
              <button
                onClick={() => setEmi(false)}
                className={`rounded-full px-4 py-1.5 text-sm font-semibold transition-colors ${
                  !emi ? "bg-ink text-white" : "text-muted"
                }`}
              >
                Single payment
              </button>
              <button
                onClick={() => setEmi(true)}
                className={`rounded-full px-4 py-1.5 text-sm font-semibold transition-colors ${
                  emi ? "bg-ink text-white" : "text-muted"
                }`}
              >
                EMI · {pricing.emiMonths} months
              </button>
            </div>

            <div className="mt-7">
              <div className="mono text-4xl font-semibold text-ink">
                {emi ? rupee(perMonth) : rupee(pricing.fee)}
                {emi && <span className="text-lg text-muted"> / mo</span>}
              </div>
              <p className="mt-1 text-sm text-muted">
                {emi
                  ? `${pricing.emiMonths} instalments · ${rupee(
                      pricing.fee,
                    )} total · no extra charge`
                  : "Full course · one-time · GST included"}
              </p>
            </div>

            <div className="mt-7 space-y-2">
              <p className="kicker text-muted">Payment schedule</p>
              {schedule.map((row, i) => (
                <motion.div
                  key={row.label}
                  initial={reduce ? false : { opacity: 0, x: -8 }}
                  animate={{ opacity: 1, x: 0 }}
                  transition={{ duration: durations.base, ease, delay: i * 0.05 }}
                  className="flex items-center justify-between rounded-lg border border-line bg-white px-4 py-2.5"
                >
                  <span className="text-sm text-muted">{row.label}</span>
                  <span className="mono text-sm font-semibold text-ink">
                    {rupee(row.amount)}
                  </span>
                </motion.div>
              ))}
            </div>

            <a
              href="#masterclass"
              className="mt-7 block rounded-full bg-trust px-6 py-3 text-center font-semibold text-white transition-colors hover:bg-deep"
            >
              Start with a free masterclass
            </a>
          </div>

          {/* Included vs paid table */}
          <div className="rounded-3xl border border-line bg-paper p-8">
            <p className="kicker text-verify">What&apos;s included</p>
            <ul className="mt-5 divide-y divide-line">
              {included.map((row) => (
                <li
                  key={row.feature}
                  className="flex items-center justify-between py-3.5"
                >
                  <span className="text-ink">{row.feature}</span>
                  {row.plan === true ? (
                    <span className="rounded-full bg-verify/10 px-3 py-1 text-xs font-semibold text-verify">
                      Included
                    </span>
                  ) : (
                    <span className="mono text-xs text-muted">{row.plan}</span>
                  )}
                </li>
              ))}
            </ul>
          </div>
        </div>
      </div>
    </section>
  );
}
