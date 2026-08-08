import { MonoCounter } from "@/components/motion/MonoCounter";
import { ScrollReveal } from "@/components/motion/ScrollReveal";
import { TiltCard } from "@/components/motion/TiltCard";
import { EmployerCta } from "@/components/employers/EmployerCta";
import { Aurora, InkPanel, Section } from "@/components/employers/ui";
import { freeOffer } from "@/content/employers";

/**
 * The launch offer.
 *
 * Green throughout, because the design system reserves green for
 * free/verified/kept-promise and this is the one section on the page that is
 * literally about something being free. No countdown, no struck-through
 * price that never existed — the offer is stated and left to stand.
 */
export function FreeOffer() {
  return (
    <Section id="free" className="overflow-hidden">
      <Aurora />

      <InkPanel accent="verify" className="p-7 md:p-12">
        <div className="grid gap-10 lg:grid-cols-[1fr_1.1fr] lg:items-center">
          <div>
            <p className="kicker text-verify">{freeOffer.kicker}</p>

            <h2 className="display mt-4 text-white">
              <span className="block text-5xl md:text-7xl">
                <MonoCounter value={freeOffer.months} suffix=" months" />
              </span>
              <span className="mt-1 block bg-gradient-to-r from-verify to-trust bg-clip-text text-5xl text-transparent md:text-7xl">
                ₹{freeOffer.price}
              </span>
            </h2>

            <p className="mt-6 max-w-lg text-[15px] leading-relaxed text-white/60">
              {freeOffer.sub}
            </p>

            <div className="mt-8">
              <EmployerCta>Start free for {freeOffer.months} months</EmployerCta>
            </div>
          </div>

          <div className="grid gap-3 sm:grid-cols-2">
            {freeOffer.included.map((item, i) => (
              <ScrollReveal key={item.title} delay={(i % 2) * 0.06} className="h-full">
                <TiltCard className="h-full">
                  <div className="h-full rounded-[14px] border border-verify/25 bg-verify/[0.06] p-5">
                    <p className="mono flex items-center gap-2 text-[10px] uppercase tracking-[0.14em] text-verify">
                      <span aria-hidden>✓</span>
                      Included
                    </p>
                    <h3 className="mt-3 text-[15px] font-semibold text-white">{item.title}</h3>
                    <p className="mt-1.5 text-[13px] leading-relaxed text-white/55">{item.body}</p>
                  </div>
                </TiltCard>
              </ScrollReveal>
            ))}
          </div>
        </div>

        <ScrollReveal delay={0.1}>
          <p className="mt-9 border-t border-white/[0.07] pt-7 text-[14px] leading-relaxed text-white/50">
            {freeOffer.closer}
          </p>
        </ScrollReveal>
      </InkPanel>
    </Section>
  );
}
