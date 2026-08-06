import Link from "next/link";
import { ScrollReveal } from "@/components/motion/ScrollReveal";
import { MaskReveal } from "@/components/motion/MaskReveal";
import { EmployerCta } from "@/components/employers/EmployerCta";
import { LivePipeline } from "@/components/employers/LivePipeline";
import { Aurora, LiveDot } from "@/components/employers/ui";
import { employerHero, freeOffer } from "@/content/employers";

/**
 * Hero. The claim is deliberately narrow — "already interviewed" is a fact
 * about the product's mechanics, not a promise about outcomes, which the
 * brand rules forbid.
 *
 * The offer badge is green because green is the free/verified colour and
 * nothing else; a ₹0 launch offer is the one thing on the page it is
 * actually for.
 */
export function EmployerHero() {
  return (
    <section className="relative overflow-hidden px-5 pb-13 pt-16 md:pb-18 md:pt-24">
      <Aurora />

      <div className="relative mx-auto grid max-w-6xl items-center gap-12 lg:grid-cols-[1.05fr_1fr]">
        <div>
          <ScrollReveal>
            <span className="mono inline-flex items-center gap-2 rounded-full border border-verify/40 bg-verify/10 px-3 py-1.5 text-[11px] uppercase tracking-[0.14em] text-verify">
              <LiveDot />
              Free for {freeOffer.months} months · ₹{freeOffer.price}
            </span>
          </ScrollReveal>

          <h1 className="display mt-6 text-4xl text-white md:text-6xl">
            <MaskReveal index={0} className="block">
              {employerHero.title}
            </MaskReveal>
            <MaskReveal index={1} className="block">
              <span className="bg-gradient-to-r from-trust via-sky to-employer bg-clip-text text-transparent">
                {employerHero.highlight}
              </span>
            </MaskReveal>
          </h1>

          <ScrollReveal delay={0.08}>
            <p className="mt-6 max-w-xl text-lg leading-relaxed text-white/60">
              {employerHero.sub}
            </p>
          </ScrollReveal>

          <ScrollReveal delay={0.14}>
            <div className="mt-9 flex flex-wrap items-center gap-3">
              <EmployerCta>{employerHero.primary}</EmployerCta>
              <Link
                href="/employer"
                className="rounded-full border border-white/12 bg-white/[0.04] px-6 py-3 font-semibold text-white/85 transition-colors hover:border-white/30 hover:text-white"
              >
                {employerHero.secondary}
              </Link>
            </div>
            <p className="mono mt-5 text-[11px] uppercase tracking-[0.14em] text-white/35">
              Design the interview once · every applicant sits it, proctored · you read the evidence
            </p>
          </ScrollReveal>
        </div>

        <ScrollReveal delay={0.2}>
          <LivePipeline />
        </ScrollReveal>
      </div>
    </section>
  );
}
