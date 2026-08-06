import { ScrollReveal } from "@/components/motion/ScrollReveal";
import { Section, SectionHead } from "@/components/employers/ui";
import { capabilities } from "@/content/employers";

/** Tone → the hairline that runs down the left of a tile. */
const RULE = {
  trust: "before:bg-trust",
  employer: "before:bg-employer",
  verify: "before:bg-verify",
} as const;

/**
 * What is actually in the workspace. One tile per capability, no tile
 * claiming an outcome — each says what the feature does and stops.
 */
export function Capabilities() {
  return (
    <Section id="capabilities" className="bg-paper">
      <SectionHead
        kicker="Inside the workspace"
        title="Everything the hiring team"
        highlight="works out of"
        sub="One place for the role, the assessment, the rounds, the pipeline and the evidence behind every decision in it."
      />

      <div className="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {capabilities.map((c, i) => (
          <ScrollReveal key={c.title} delay={(i % 3) * 0.05} className="h-full">
            <article
              className={`relative flex h-full flex-col overflow-hidden rounded-[14px] border border-line bg-white p-6 shadow-soft transition-all duration-300 before:absolute before:inset-y-0 before:left-0 before:w-[3px] before:content-[''] hover:-translate-y-1 hover:border-trust/40 ${RULE[c.tone]}`}
            >
              <h3 className="display text-lg text-ink">{c.title}</h3>
              <p className="mt-2.5 text-[14px] leading-relaxed text-ink2/70">{c.body}</p>
            </article>
          </ScrollReveal>
        ))}
      </div>
    </Section>
  );
}
