import { MonoCounter } from "@/components/motion/MonoCounter";
import { ScrollReveal } from "@/components/motion/ScrollReveal";
import { proofStats } from "@/content/landing";

/** The ink-navy Proof Engine panel with animated mono counters (CLAUDE.md). */
export function ProofEngine() {
  return (
    <section className="mx-auto max-w-6xl px-5 py-10">
      <ScrollReveal>
        <div className="rounded-3xl bg-ink px-6 py-12 text-white shadow-xl md:px-12">
          <p className="kicker text-center text-sky/80">The Proof Engine</p>
          <div className="mt-8 grid grid-cols-2 gap-8 md:grid-cols-4">
            {proofStats.map((s) => (
              <div key={s.label} className="text-center">
                <div className="display text-3xl text-white md:text-4xl">
                  <MonoCounter
                    value={s.value}
                    prefix={"prefix" in s ? s.prefix : ""}
                    suffix={"suffix" in s ? s.suffix : ""}
                    decimals={"decimals" in s ? s.decimals : 0}
                  />
                </div>
                <p className="mt-2 text-sm text-sky/70">{s.label}</p>
              </div>
            ))}
          </div>
          <p className="mx-auto mt-9 max-w-2xl text-center text-xs text-sky/50">
            Figures are aggregate outcomes across cohorts to date and are not a
            guarantee of individual results. Detailed methodology available on
            request.
          </p>
        </div>
      </ScrollReveal>
    </section>
  );
}
