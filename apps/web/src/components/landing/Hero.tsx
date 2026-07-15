import { ScrollReveal } from "@/components/motion/ScrollReveal";
import { DecodeText } from "@/components/motion/DecodeText";
import { BookCta } from "@/components/landing/BookCta";

export function Hero() {
  return (
    <section className="relative overflow-hidden">
      {/* Ambient glow — static gradient, zero layout cost. */}
      <div
        aria-hidden
        className="pointer-events-none absolute inset-0 -z-10"
        style={{
          background:
            "radial-gradient(60% 50% at 50% 0%, var(--bj-sky) 0%, transparent 70%)",
        }}
      />

      <div className="mx-auto max-w-6xl px-5 pt-20 pb-16 text-center md:pt-28">
        <ScrollReveal>
          <p className="kicker text-trust">Built from real interviews</p>
        </ScrollReveal>

        <ScrollReveal delay={0.06}>
          <h1 className="display mx-auto mt-5 max-w-4xl text-4xl text-ink sm:text-5xl md:text-6xl">
            This syllabus was not written.
            <br />
            <span className="text-trust">
              It was{" "}
              <DecodeText text="reverse-engineered." className="mono font-semibold" />
            </span>
          </h1>
        </ScrollReveal>

        <ScrollReveal delay={0.12}>
          <p className="mx-auto mt-6 max-w-2xl text-lg text-ink2/80">
            An AI system monitors up to ~50 real &amp; mock interviews a day,
            extracts the questions companies are asking this month, and rebuilds
            the syllabus around that live demand. You study only what&apos;s
            actually asked.
          </p>
        </ScrollReveal>

        <ScrollReveal delay={0.18}>
          <div className="mt-9 flex flex-col items-center justify-center gap-3 sm:flex-row">
            <BookCta className="w-full px-8 py-3.5 text-base sm:w-auto" />
            <a
              href="#fees"
              className="w-full rounded-full border border-line bg-white px-8 py-3.5 text-base font-semibold text-ink transition-colors hover:border-trust sm:w-auto"
            >
              See the fee structure
            </a>
          </div>
        </ScrollReveal>

        <ScrollReveal delay={0.24}>
          <p className="mono mt-7 text-xs tracking-[0.18em] text-muted">
            LISTEN&nbsp;&nbsp;→&nbsp;&nbsp;EXTRACT&nbsp;&nbsp;→&nbsp;&nbsp;REBUILD
          </p>
        </ScrollReveal>
      </div>
    </section>
  );
}
