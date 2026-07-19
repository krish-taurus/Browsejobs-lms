import { ScrollReveal } from "@/components/motion/ScrollReveal";
import { DecodeText } from "@/components/motion/DecodeText";
import { MaskReveal } from "@/components/motion/MaskReveal";
import { Parallax } from "@/components/motion/Parallax";
import { BookCta } from "@/components/landing/BookCta";
import { HeroEngine } from "@/components/landing/HeroEngine";
import { Kicker } from "@/components/brand/Kicker";

/**
 * S1 — the thesis. Copy carries the LCP headline (renders immediately); the engine
 * visual is decorative and lit beside it. Ambient gradient drift, one trust-blue CTA.
 */
export function Hero() {
  return (
    <section className="relative overflow-hidden">
      {/* Ambient drift — a single slow gradient, transform-only, reduced-motion safe. */}
      <div
        aria-hidden
        className="pointer-events-none absolute inset-0 -z-10 hero-drift"
        style={{
          background:
            "radial-gradient(55% 45% at 30% 0%, var(--bj-sky) 0%, transparent 65%), radial-gradient(45% 40% at 85% 10%, rgba(27,109,240,0.10) 0%, transparent 60%)",
        }}
      />

      <div className="mx-auto grid max-w-6xl items-center gap-12 px-5 pt-16 pb-14 md:grid-cols-[1.05fr_0.95fr] md:pt-24 md:pb-20">
        <div className="text-center md:text-left">
          <ScrollReveal>
            <Kicker>Built from real interviews</Kicker>
          </ScrollReveal>

          <h1 className="display mt-5 text-[clamp(2.5rem,7vw,4.75rem)] text-ink">
            <MaskReveal index={0}>This syllabus</MaskReveal>
            <MaskReveal index={1}>was not written.</MaskReveal>
            <MaskReveal index={2} className="text-trust">
              It was{" "}
              <DecodeText text="reverse-engineered." className="mono font-semibold" />
            </MaskReveal>
          </h1>

          <ScrollReveal delay={0.12}>
            <p className="mx-auto mt-6 max-w-xl text-lg leading-relaxed text-ink2/80 md:mx-0">
              An AI system monitors up to ~50 real &amp; mock interviews a day, extracts
              what companies are asking this month, and rebuilds the syllabus around it.
              You study only what&apos;s actually asked.
            </p>
          </ScrollReveal>

          <ScrollReveal delay={0.18}>
            <div className="mt-9 flex flex-col items-center gap-3 sm:flex-row md:items-start">
              <BookCta className="w-full px-8 py-3.5 text-base sm:w-auto" />
              <a
                href="#fees"
                className="w-full rounded-full border border-line bg-white px-8 py-3.5 text-center text-base font-semibold text-ink transition-colors hover:border-trust sm:w-auto"
              >
                See the fee structure
              </a>
            </div>
          </ScrollReveal>

          <ScrollReveal delay={0.24}>
            <p className="mono mt-8 text-xs tracking-[0.18em] text-muted">
              LISTEN&nbsp;&nbsp;→&nbsp;&nbsp;EXTRACT&nbsp;&nbsp;→&nbsp;&nbsp;REBUILD
            </p>
          </ScrollReveal>
        </div>

        <ScrollReveal delay={0.1} className="hidden md:block">
          <Parallax distance={36}>
            <HeroEngine />
          </Parallax>
        </ScrollReveal>
      </div>

      {/* Engine visual below the fold on mobile — kept out of the LCP path. */}
      <div className="px-5 pb-10 md:hidden">
        <HeroEngine />
      </div>
    </section>
  );
}
