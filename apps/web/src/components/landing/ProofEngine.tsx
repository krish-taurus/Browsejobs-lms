import { MonoCounter } from "@/components/motion/MonoCounter";
import { ScrollReveal } from "@/components/motion/ScrollReveal";
import { Disclaimer } from "@/components/brand/Disclaimer";
import { ProofLoop } from "@/components/landing/ProofLoop";
import { stats } from "@/content/landing";

/**
 * The signature ink Proof Engine panel (spec §2.3): the AI loop
 * LISTEN → EXTRACT → REBUILD plus the 4-up mono stat band, ALWAYS followed by
 * the mandatory disclaimer.
 */
export function ProofEngine() {
  return (
    <section id="proof" className="mx-auto max-w-6xl px-5 py-10">
      <ScrollReveal>
        <div className="rounded-[22px] bg-ink px-6 py-12 text-white shadow-soft md:px-12">
          <p className="kicker text-sky/80">The Syllabus Engine — rebuilt monthly</p>
          <h2 className="display mt-3 max-w-2xl text-2xl text-white md:text-3xl">
            We listen to the market so you study only what&apos;s asked
          </h2>

          <div className="mt-8">
            <ProofLoop />
          </div>

          {/* Stat band (spec §2.3) */}
          <div className="mt-10 grid grid-cols-2 gap-8 border-t border-white/10 pt-9 md:grid-cols-4">
            {stats.map((s) => (
              <div key={s.label} className="text-center">
                <div className="display text-3xl text-white md:text-4xl">
                  <MonoCounter
                    value={s.value}
                    prefix={"prefix" in s ? s.prefix : ""}
                    suffix={"suffix" in s ? s.suffix : ""}
                  />
                </div>
                <p className="mt-2 text-sm text-sky/70">{s.label}</p>
              </div>
            ))}
          </div>

          <div className="mx-auto mt-8 max-w-2xl text-center">
            <Disclaimer className="text-sky/50" />
          </div>
        </div>
      </ScrollReveal>
    </section>
  );
}
