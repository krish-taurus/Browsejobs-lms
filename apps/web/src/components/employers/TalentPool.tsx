import { ScrollReveal } from "@/components/motion/ScrollReveal";
import { EmployerCta } from "@/components/employers/EmployerCta";
import { InkLabel, InkPanel, SampleStamp, Section } from "@/components/employers/ui";
import { sampleTalentPool } from "@/content/employers";

/**
 * The talent pool — the part of the pipeline that is ours rather than the
 * open market. Each card shows what the matcher returns, including the
 * skills a candidate is MISSING, because a match card that only lists hits
 * is a sales sheet and not a matcher.
 *
 * The heading avoids "hyperskilled" as a claim about people and uses it the
 * way the platform can defend it: candidates trained in a named programme,
 * with a mock history and a readiness index behind the number.
 */
export function TalentPool() {
  return (
    <Section id="talent-pool" className="bg-white">
      <div className="rounded-[22px] bg-deck p-6 md:p-12">
        <ScrollReveal>
          <p className="kicker text-employer">Trained candidates</p>
          <h2 className="display mt-3 max-w-3xl text-3xl text-white md:text-[2.75rem]">
            Hire from the pool we{" "}
            <span className="bg-gradient-to-r from-trust to-employer bg-clip-text text-transparent">
              trained ourselves
            </span>
          </h2>
          <p className="mt-4 max-w-2xl text-[15px] leading-relaxed text-white/55">
            Open a JD&apos;s talent pool and you are matched against BrowseJobs candidates who
            already carry a built CV, a mastery record and a mock history. You can invite them to
            apply whether or not they were looking. What an external board cannot show you is on
            every card: the skills they are missing.
          </p>
        </ScrollReveal>

        <div className="mt-10 grid gap-4 md:grid-cols-3">
          {sampleTalentPool.map((c, i) => (
            <ScrollReveal key={c.candidate_id} delay={i * 0.06} className="h-full">
              <InkPanel accent={i === 0 ? "employer" : "trust"} className="h-full p-5 md:p-6">
                <div className="flex items-start justify-between gap-3">
                  <div>
                    <p className="text-[15px] font-semibold text-white">{c.name}</p>
                    <p className="mono mt-1 text-[10px] uppercase tracking-[0.12em] text-white/35">
                      {c.training?.course} ·{" "}
                      {c.training?.status === "completed" ? "completed" : "in progress"}
                    </p>
                  </div>
                  <div className="text-right">
                    <p className="mono text-2xl font-semibold leading-none text-white">
                      {c.readiness_index}
                    </p>
                    <p className="mono mt-1 text-[9px] uppercase tracking-[0.12em] text-white/35">
                      readiness
                    </p>
                  </div>
                </div>

                <dl className="mt-5 grid grid-cols-2 gap-3 border-t border-white/[0.07] pt-4">
                  <div>
                    <dt className="mono text-[9px] uppercase tracking-[0.12em] text-white/30">
                      Skill match
                    </dt>
                    <dd className="mono mt-1 text-[13px] text-white/80">{c.skill_match_pct}%</dd>
                  </div>
                  <div>
                    <dt className="mono text-[9px] uppercase tracking-[0.12em] text-white/30">
                      Mock average
                    </dt>
                    <dd className="mono mt-1 text-[13px] text-white/80">
                      {c.mock_average}
                      <span className="text-white/35"> / {c.mock_attempts} sat</span>
                    </dd>
                  </div>
                </dl>

                <div className="mt-4">
                  <InkLabel>Matched</InkLabel>
                  <div className="mt-2 flex flex-wrap gap-1.5">
                    {c.matched_skills.map((s) => (
                      <span
                        key={s}
                        className="mono rounded-full border border-verify/40 bg-verify/10 px-2.5 py-1 text-[10px] text-verify"
                      >
                        {s}
                      </span>
                    ))}
                  </div>
                </div>

                <div className="mt-3.5">
                  <InkLabel>Missing</InkLabel>
                  <div className="mt-2 flex flex-wrap gap-1.5">
                    {c.missing_skills.map((s) => (
                      <span
                        key={s}
                        className="mono rounded-full border border-white/10 px-2.5 py-1 text-[10px] text-white/45"
                      >
                        {s}
                      </span>
                    ))}
                  </div>
                </div>

                <p className="mono mt-5 border-t border-white/[0.07] pt-4 text-[10px] uppercase tracking-[0.12em] text-white/40">
                  {c.cv_ready ? "CV ready" : "CV still being built"}
                </p>
              </InkPanel>
            </ScrollReveal>
          ))}
        </div>

        <ScrollReveal delay={0.14}>
          <div className="mt-8 flex flex-col items-start gap-4 border-t border-white/[0.07] pt-8 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex flex-wrap items-center gap-3">
              <SampleStamp onInk />
              <p className="text-[12px] text-white/40">
                Real pool depth depends on the role and the cohort in training.
              </p>
            </div>
            <EmployerCta>Ask for a matched shortlist</EmployerCta>
          </div>
        </ScrollReveal>
      </div>
    </Section>
  );
}
