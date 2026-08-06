import { ScrollReveal } from "@/components/motion/ScrollReveal";
import { Section, SectionHead } from "@/components/employers/ui";
import { useCases } from "@/content/employers";

/**
 * Four hiring situations, each in three beats: what you are looking at, what
 * goes wrong, and what the platform does about it. The "what goes wrong" beat
 * is left in neutral ink rather than red — red is reserved for a refused
 * promise, and a market problem is not one.
 */
export function UseCases() {
  return (
    <Section id="use-cases" className="bg-paper">
      <SectionHead
        kicker="Where it earns its place"
        title="Four situations this was"
        highlight="actually built for"
        sub="Not every hire needs a designed assessment. These are the ones that do — and what changes when the screening happens before the CV reaches you."
      />

      <div className="mt-12 grid gap-5 lg:grid-cols-2">
        {useCases.map((u, i) => (
          <ScrollReveal key={u.id} delay={(i % 2) * 0.06} className="h-full">
            <article className="group flex h-full flex-col rounded-[22px] border border-line bg-white p-6 shadow-soft transition-all duration-300 hover:-translate-y-1 hover:border-trust/40 md:p-8">
              <div className="flex items-center gap-3">
                <span className="mono rounded-full bg-employer-soft px-3 py-1 text-[10px] uppercase tracking-[0.14em] text-employer">
                  {u.tag}
                </span>
                <span aria-hidden className="h-px flex-1 bg-line" />
              </div>

              <h3 className="display mt-5 text-xl text-ink md:text-2xl">{u.title}</h3>
              <p className="mt-3 text-[15px] leading-relaxed text-ink2/75">{u.situation}</p>

              <div className="mt-6 space-y-5 border-t border-line pt-6">
                <div className="border-l-2 border-line pl-4">
                  <p className="mono text-[10px] uppercase tracking-[0.16em] text-muted">
                    What goes wrong
                  </p>
                  <p className="mt-1.5 text-[14px] leading-relaxed text-ink2/70">{u.problem}</p>
                </div>
                <div className="border-l-2 border-trust pl-4">
                  <p className="mono text-[10px] uppercase tracking-[0.16em] text-trust">
                    What BrowseJobs does
                  </p>
                  <p className="mt-1.5 text-[14px] leading-relaxed text-ink2">{u.solution}</p>
                </div>
              </div>

              <ul className="mt-6 flex flex-wrap gap-1.5">
                {u.features.map((f) => (
                  <li
                    key={f}
                    className="mono rounded-full border border-line px-2.5 py-1 text-[10px] text-muted"
                  >
                    {f}
                  </li>
                ))}
              </ul>
            </article>
          </ScrollReveal>
        ))}
      </div>
    </Section>
  );
}
