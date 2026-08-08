import { ScrollReveal } from "@/components/motion/ScrollReveal";
import { Spotlight } from "@/components/motion/Spotlight";
import { Aurora, InkPanel, Section, SectionHead } from "@/components/employers/ui";
import { useCases } from "@/content/employers";

/**
 * Four hiring situations, each in three beats: what you are looking at, what
 * goes wrong, and what the platform does about it. The "what goes wrong" beat
 * stays neutral rather than red — red is reserved for a refused promise, and
 * a market problem is not one.
 */
export function UseCases() {
  return (
    <Section id="use-cases" className="overflow-hidden">
      <Aurora />

      <SectionHead
        kicker="Where it earns its place"
        title="Four situations this was"
        highlight="actually built for"
        sub="Not every hire needs a designed assessment. These are the ones that do — and what changes when the screening happens before the CV reaches you."
      />

      <div className="mt-12 grid gap-4 lg:grid-cols-2">
        {useCases.map((u, i) => (
          <ScrollReveal key={u.id} delay={(i % 2) * 0.06} className="h-full">
            <Spotlight className="h-full">
              <InkPanel
                accent={i % 2 === 0 ? "trust" : "employer"}
                className="h-full p-6 transition-colors duration-300 hover:border-white/20 md:p-8"
              >
                <div className="flex items-center gap-3">
                  <span className="mono rounded-full border border-employer/40 bg-employer/10 px-3 py-1 text-[10px] uppercase tracking-[0.14em] text-employer">
                    {u.tag}
                  </span>
                  <span aria-hidden className="h-px flex-1 bg-white/[0.08]" />
                  <span className="mono text-[10px] text-white/20">
                    {String(i + 1).padStart(2, "0")}
                  </span>
                </div>

                <h3 className="display mt-5 text-xl text-white md:text-2xl">{u.title}</h3>
                <p className="mt-3 text-[15px] leading-relaxed text-white/60">{u.situation}</p>

                <div className="mt-6 space-y-5 border-t border-white/[0.07] pt-6">
                  <div className="border-l-2 border-white/15 pl-4">
                    <p className="mono text-[10px] uppercase tracking-[0.16em] text-white/40">
                      What goes wrong
                    </p>
                    <p className="mt-1.5 text-[14px] leading-relaxed text-white/55">{u.problem}</p>
                  </div>
                  <div className="border-l-2 border-trust pl-4">
                    <p className="mono text-[10px] uppercase tracking-[0.16em] text-trust">
                      What BrowseJobs does
                    </p>
                    <p className="mt-1.5 text-[14px] leading-relaxed text-white/75">{u.solution}</p>
                  </div>
                </div>

                <ul className="mt-6 flex flex-wrap gap-1.5">
                  {u.features.map((f) => (
                    <li
                      key={f}
                      className="mono rounded-full border border-white/10 px-2.5 py-1 text-[10px] text-white/45"
                    >
                      {f}
                    </li>
                  ))}
                </ul>
              </InkPanel>
            </Spotlight>
          </ScrollReveal>
        ))}
      </div>
    </Section>
  );
}
