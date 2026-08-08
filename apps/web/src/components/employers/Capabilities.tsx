import { ScrollReveal } from "@/components/motion/ScrollReveal";
import { TiltCard } from "@/components/motion/TiltCard";
import { Aurora, Section, SectionHead } from "@/components/employers/ui";
import { capabilities } from "@/content/employers";

/** Tone → the hairline that runs down the left of a tile. */
const RULE = {
  trust: "before:bg-trust",
  employer: "before:bg-employer",
  verify: "before:bg-verify",
} as const;

const GLOW = {
  trust: "group-hover:shadow-[0_0_60px_-12px_var(--bj-trust)]",
  employer: "group-hover:shadow-[0_0_60px_-12px_var(--bj-employer)]",
  verify: "group-hover:shadow-[0_0_60px_-12px_var(--bj-verify)]",
} as const;

/**
 * What is actually in the workspace. One tile per capability, no tile
 * claiming an outcome — each says what the feature does and stops. The tiles
 * tilt and bloom toward the cursor; on touch and under reduced motion they
 * are flat cards and lose nothing.
 */
export function Capabilities() {
  return (
    <Section id="capabilities" className="overflow-hidden">
      <Aurora />

      <SectionHead
        kicker="Inside the workspace"
        title="Everything the hiring team"
        highlight="works out of"
        sub="One place for the role, the assessment, the rounds, the pipeline and the evidence behind every decision in it."
        tone="employer"
      />

      <div className="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {capabilities.map((c, i) => (
          <ScrollReveal key={c.title} delay={(i % 3) * 0.05} className="h-full">
            <TiltCard className="group h-full">
              <article
                className={`relative flex h-full flex-col overflow-hidden rounded-[14px] border border-white/10 bg-deck-card p-6 transition-all duration-300 before:absolute before:inset-y-0 before:left-0 before:w-[3px] before:content-[''] group-hover:border-white/20 ${RULE[c.tone]} ${GLOW[c.tone]}`}
              >
                <h3 className="display text-lg text-white">{c.title}</h3>
                <p className="mt-2.5 text-[14px] leading-relaxed text-white/55">{c.body}</p>
              </article>
            </TiltCard>
          </ScrollReveal>
        ))}
      </div>
    </Section>
  );
}
