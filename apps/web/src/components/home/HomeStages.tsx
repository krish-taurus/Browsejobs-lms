import { ScrollReveal } from "@/components/motion/ScrollReveal";
import { hiringStages } from "@/content/home";

/** Plain list of the pipeline. Not-live stages say so. The offer always needs a person. */
export function HomeStages() {
  return (
    <section id="stages" className="scroll-mt-28 border-t border-white/10">
      <div className="mx-auto max-w-6xl px-5 py-28 md:py-40">
        <ScrollReveal>
          <p className="kicker text-trust">The stages</p>
          <h2 className="display mt-6 max-w-4xl text-[clamp(2.75rem,7vw,6rem)] leading-[0.92] tracking-[-0.04em] text-fg">
            The path we run with you.
          </h2>
          <p className="mt-6 max-w-2xl text-lg leading-relaxed text-muted md:text-xl">
            From the AI interview through joining. A person always releases the offer letter.
          </p>
        </ScrollReveal>
        <ol className="mt-16 divide-y divide-white/10 border-y border-white/10">
          {hiringStages.map((stage, index) => (
            <ScrollReveal as="li" key={stage.name} delay={index * 0.04}>
              <div className="grid gap-3 py-8 md:grid-cols-[8rem_minmax(0,1fr)] md:items-baseline md:gap-10 md:py-10">
                <p className="mono text-sm font-semibold text-trust">{String(index + 1).padStart(2, "0")}</p>
                <div>
                  <h3 className="display text-3xl text-fg md:text-4xl">{stage.name}</h3>
                  <p className="mt-2 max-w-xl text-base leading-relaxed text-muted">{stage.body}</p>
                </div>
              </div>
            </ScrollReveal>
          ))}
        </ol>
      </div>
    </section>
  );
}
