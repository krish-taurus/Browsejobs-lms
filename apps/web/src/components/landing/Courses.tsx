import Link from "next/link";
import { ScrollReveal } from "@/components/motion/ScrollReveal";
import { Kicker } from "@/components/brand/Kicker";
import { courses } from "@/content/landing";

/**
 * S4 — Programs. Four big live tracks (staggered reveal, hover lift) with a tool row and
 * a CV-ready project count; three quieter waitlist cards below. No per-card blue button —
 * the one trust-blue CTA lives in the nav and sticky bar (semantic-colour law).
 */

const EXTRAS: Record<string, { tools: string[]; projects: string }> = {
  "data-engineering": { tools: ["Python", "SQL", "Spark", "AWS", "Databricks", "Airflow"], projects: "3 CV-ready projects" },
  "devops-cloud": { tools: ["Docker", "Kubernetes", "Terraform", "Jenkins", "AWS", "Ansible"], projects: "4 CV-ready projects" },
  "data-analytics": { tools: ["Excel", "SQL", "Python", "Power BI", "DAX", "Pandas"], projects: "5 CV-ready projects" },
  "python-backend": { tools: [], projects: "Detailed syllabus arriving" },
};

export function Courses() {
  const live = courses.filter((c) => c.live);
  const soon = courses.filter((c) => !c.live);

  return (
    <section id="courses" className="mx-auto max-w-6xl px-5 py-20 md:py-28">
      <ScrollReveal>
        <Kicker>Programs</Kicker>
        <h2 className="display mt-3 max-w-2xl text-3xl text-ink md:text-5xl">
          Four live tracks. Each one rebuilt monthly.
        </h2>
      </ScrollReveal>

      <div className="mt-12 grid gap-5 md:grid-cols-2">
        {live.map((c, i) => {
          const x = EXTRAS[c.slug];
          return (
            <ScrollReveal key={c.code} delay={i * 0.06}>
              <Link
                href={`/courses/${c.slug}`}
                className="group flex h-full flex-col rounded-[22px] border border-line bg-white p-8 transition-all duration-300 hover:-translate-y-1.5 hover:border-trust/40 hover:shadow-soft md:p-10"
              >
                <div className="flex items-center justify-between">
                  <span className="mono rounded-full bg-sky px-3 py-1 text-xs font-semibold text-deep">{c.code}</span>
                  <span className="flex items-center gap-1.5 text-xs font-semibold text-verify">
                    <span className="relative flex h-2 w-2">
                      <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-verify opacity-60 motion-reduce:animate-none" />
                      <span className="relative inline-flex h-2 w-2 rounded-full bg-verify" />
                    </span>
                    Live
                  </span>
                </div>

                <h3 className="display mt-6 text-3xl text-ink md:text-4xl">{c.name}</h3>
                <p className="mt-3 flex-1 text-ink2/70">{c.tagline}</p>

                {x?.tools.length ? (
                  <div className="mt-6 flex flex-wrap gap-1.5">
                    {x.tools.map((t) => (
                      <span key={t} className="mono rounded-full border border-line px-2.5 py-1 text-[11px] text-muted">
                        {t}
                      </span>
                    ))}
                  </div>
                ) : null}

                <div className="mt-7 flex items-center justify-between border-t border-line pt-5">
                  <span className="mono text-xs text-muted">{x?.projects}</span>
                  <span className="flex items-center gap-1.5 text-sm font-semibold text-trust">
                    View syllabus
                    <span className="transition-transform duration-300 group-hover:translate-x-1">→</span>
                  </span>
                </div>
              </Link>
            </ScrollReveal>
          );
        })}
      </div>

      {/* Waitlist — quiet, muted chip, never green or blue. */}
      <ScrollReveal delay={0.1}>
        <div className="mt-6 grid gap-4 sm:grid-cols-3">
          {soon.map((c) => (
            <Link
              key={c.code}
              href={`/courses/${c.slug}`}
              className="group flex items-center justify-between rounded-[14px] border border-dashed border-line bg-white/60 px-5 py-5 transition-colors hover:border-muted"
            >
              <span>
                <span className="display block text-lg text-ink">{c.name}</span>
                <span className="mono mt-1 block text-[10px] uppercase tracking-widest text-muted">Waitlist</span>
              </span>
              <span className="text-muted transition-transform duration-300 group-hover:translate-x-1">→</span>
            </Link>
          ))}
        </div>
      </ScrollReveal>
    </section>
  );
}
