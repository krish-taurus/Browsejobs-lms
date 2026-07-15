import { ScrollReveal } from "@/components/motion/ScrollReveal";

export function Hero() {
  return (
    <section className="relative overflow-hidden">
      {/* Ambient premium glow — transform/opacity only, no scroll-jacking. */}
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
          <p className="kicker text-trust">Proof, not promises</p>
        </ScrollReveal>

        <ScrollReveal delay={0.06}>
          <h1 className="display mx-auto mt-5 max-w-4xl text-4xl text-ink sm:text-5xl md:text-6xl">
            Learn the skill. Prove it.{" "}
            <span className="text-trust">Get placed.</span>
          </h1>
        </ScrollReveal>

        <ScrollReveal delay={0.12}>
          <p className="mx-auto mt-6 max-w-2xl text-lg text-muted">
            Live, mentor-led tech bootcamps with a personal AI coach, real
            interview practice, and placement support — until you&apos;re placed.
          </p>
        </ScrollReveal>

        <ScrollReveal delay={0.18}>
          <div className="mt-9 flex flex-col items-center justify-center gap-3 sm:flex-row">
            <a
              href="#masterclass"
              className="w-full rounded-full bg-trust px-7 py-3.5 text-base font-semibold text-white shadow-md transition-all hover:-translate-y-0.5 hover:bg-deep sm:w-auto"
            >
              Join a free masterclass
            </a>
            <a
              href="#pricing"
              className="w-full rounded-full border border-line bg-white px-7 py-3.5 text-base font-semibold text-ink transition-colors hover:border-trust sm:w-auto"
            >
              See courses &amp; pricing
            </a>
          </div>
        </ScrollReveal>

        <ScrollReveal delay={0.24}>
          <p className="mono mt-6 text-xs text-muted">
            Free masterclass → free bootcamp → paid batch → placement
          </p>
        </ScrollReveal>
      </div>
    </section>
  );
}
