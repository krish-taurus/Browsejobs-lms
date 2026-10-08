import { Disclaimer } from "@/components/brand/Disclaimer";
import { ScreenFrame } from "@/components/home/ScreenFrame";
import { ScrollReveal } from "@/components/motion/ScrollReveal";
import { CLAIM_INTERVIEW_CALL, CLAIM_RECRUITERS, screenSteps } from "@/content/home";

/** Candidate story: one claim, one sample round, then the three beats. */
export function HowItWorks() {
  return (
    <section id="how" className="scroll-mt-28 border-t border-white/10">
      <div className="mx-auto max-w-6xl px-5 py-28 md:py-40">
        <ScrollReveal>
          <p className="kicker text-trust">For candidates</p>
          <h2 className="display mt-6 max-w-4xl text-[clamp(2.75rem,7vw,6rem)] leading-[0.92] tracking-[-0.04em] text-fg">
            Clear it. Then HR sees you.
          </h2>
          <p className="mt-6 max-w-2xl text-lg leading-relaxed text-muted md:text-xl">
            You sit the interview first. You get a score. A course comes later, and only if you need it.
          </p>
        </ScrollReveal>

        <ScrollReveal className="mt-16 max-w-3xl">
          <p className="text-xl leading-relaxed text-fg md:text-2xl">{CLAIM_INTERVIEW_CALL}</p>
          <p className="mt-4 text-xl leading-relaxed text-fg md:text-2xl">{CLAIM_RECRUITERS}</p>
          <Disclaimer className="mt-4" />
        </ScrollReveal>

        <div className="mt-20 grid items-center gap-12 lg:grid-cols-2 lg:gap-20">
          <ScrollReveal>
            <ol className="space-y-10">
              {screenSteps.map((step) => (
                <li key={step.id}>
                  <p className="mono text-sm font-semibold text-trust">{step.n}</p>
                  <h3 className="display mt-2 text-3xl text-fg md:text-4xl">{step.title}</h3>
                  <p className="mt-3 max-w-md text-base leading-relaxed text-muted">{step.body}</p>
                </li>
              ))}
            </ol>
          </ScrollReveal>
          <ScrollReveal delay={0.08}>
            <ScreenFrame mode="interview" />
          </ScrollReveal>
        </div>
      </div>
    </section>
  );
}
