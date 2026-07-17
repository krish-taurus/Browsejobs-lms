import { ScrollReveal } from "@/components/motion/ScrollReveal";
import { StatBand } from "@/components/brand/StatBand";
import { Kicker } from "@/components/brand/Kicker";
import { ProofSequence } from "@/components/landing/ProofSequence";
import { stats } from "@/content/landing";

/**
 * S2 — the signature ink Proof Engine panel: the scroll-scrubbed machine
 * LISTEN → EXTRACT → REBUILD, the 4-up mono stat band, and the mandatory disclaimer
 * (rendered by StatBand).
 */
export function ProofEngine() {
  return (
    <section id="proof" className="mx-auto max-w-6xl px-5 py-12 md:py-16">
      <ScrollReveal>
        <div className="rounded-[22px] bg-ink px-6 py-12 text-white shadow-soft md:px-12 md:py-16">
          <Kicker tone="sky">The Syllabus Engine — rebuilt monthly</Kicker>
          <h2 className="display mt-3 max-w-2xl text-2xl text-white md:text-4xl">
            We listen to the market so you study only what&apos;s asked
          </h2>

          <div className="mt-10">
            <ProofSequence />
          </div>

          <StatBand stats={stats} tone="dark" className="mt-12" />
        </div>
      </ScrollReveal>
    </section>
  );
}
