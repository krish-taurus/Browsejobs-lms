import Link from "next/link";
import { ScrollReveal } from "@/components/motion/ScrollReveal";
import { EmployerCta } from "@/components/employers/EmployerCta";
import { InkLabel, InkPanel } from "@/components/employers/ui";
import { employerHero } from "@/content/employers";

/** Stage rail shown in the hero's product frame — the portal's real stages. */
const RAIL = [
  { label: "Applied", count: 261, width: "w-full" },
  { label: "Graded", count: 261, width: "w-full" },
  { label: "Shortlisted", count: 34, width: "w-1/2" },
  { label: "L1", count: 18, width: "w-1/3" },
  { label: "Offer", count: 4, width: "w-1/12" },
];

const ROWS = [
  { name: "Ananya R.", role: "SQL · Python · Airflow", score: 78, tone: "text-trust" },
  { name: "Rahul M.", role: "Spark · dbt · Kafka", score: 91, tone: "text-verify" },
  { name: "Sneha K.", role: "PostgreSQL · Docker", score: 64, tone: "text-white/70" },
];

/**
 * Hero. The claim is deliberately narrow — "already interviewed" is a fact
 * about the product's mechanics, not a promise about outcomes, which the
 * brand rules forbid.
 */
export function EmployerHero() {
  return (
    <section className="relative overflow-hidden px-5 pb-13 pt-16 md:pb-18 md:pt-24">
      {/* Ambient wash: the module's two hues, transform-free and decorative. */}
      <div aria-hidden className="pointer-events-none absolute inset-0 -z-10">
        <div className="hero-drift absolute -left-40 -top-56 h-[520px] w-[620px] rounded-full bg-trust/15 blur-[150px]" />
        <div className="absolute -right-32 top-24 h-[420px] w-[520px] rounded-full bg-employer/10 blur-[150px]" />
      </div>

      <div className="mx-auto grid max-w-6xl items-center gap-12 lg:grid-cols-[1.05fr_1fr]">
        <div>
          <ScrollReveal>
            <p className="kicker text-trust">{employerHero.kicker}</p>
            <h1 className="display mt-4 text-4xl text-ink md:text-6xl">
              {employerHero.title}
              <br />
              <span className="bg-gradient-to-r from-trust via-deep to-employer bg-clip-text text-transparent">
                {employerHero.highlight}
              </span>
            </h1>
            <p className="mt-6 max-w-xl text-lg leading-relaxed text-ink2/75">
              {employerHero.sub}
            </p>
          </ScrollReveal>

          <ScrollReveal delay={0.1}>
            <div className="mt-9 flex flex-wrap items-center gap-3">
              <EmployerCta>{employerHero.primary}</EmployerCta>
              <Link
                href="/employer"
                className="rounded-full border border-line bg-white px-6 py-3 font-semibold text-ink transition-colors hover:border-trust hover:text-trust"
              >
                {employerHero.secondary}
              </Link>
            </div>
            <p className="mono mt-5 text-[11px] uppercase tracking-[0.14em] text-muted">
              Design the interview once · every applicant sits it · you read the evidence
            </p>
          </ScrollReveal>
        </div>

        {/* Product frame — the pipeline, in the workspace's own colours. */}
        <ScrollReveal delay={0.16}>
          <InkPanel accent="employer" className="p-5 md:p-6">
            <div className="flex items-center justify-between">
              <InkLabel>Pipeline · Data Engineer</InkLabel>
              <span className="mono text-[10px] uppercase tracking-[0.14em] text-white/30">
                live
              </span>
            </div>

            <ul className="mt-5 space-y-3">
              {RAIL.map((s) => (
                <li key={s.label} className="flex items-center gap-3">
                  <span className="w-24 shrink-0 text-[12px] text-white/55">{s.label}</span>
                  <span className="h-1.5 flex-1 overflow-hidden rounded-full bg-white/[0.07]">
                    <span
                      className={`block h-full rounded-full bg-gradient-to-r from-trust to-employer ${s.width}`}
                    />
                  </span>
                  <span className="mono w-9 shrink-0 text-right text-[12px] text-white/70">
                    {s.count}
                  </span>
                </li>
              ))}
            </ul>

            <div className="mt-6 space-y-2 border-t border-white/[0.07] pt-5">
              {ROWS.map((r) => (
                <div
                  key={r.name}
                  className="flex items-center justify-between rounded-2xl border border-white/[0.08] bg-white/[0.03] px-4 py-3"
                >
                  <div className="min-w-0">
                    <p className="truncate text-[13px] font-semibold text-white">{r.name}</p>
                    <p className="mono truncate text-[10px] uppercase tracking-[0.1em] text-white/35">
                      {r.role}
                    </p>
                  </div>
                  <span className={`mono shrink-0 text-lg font-semibold ${r.tone}`}>{r.score}</span>
                </div>
              ))}
            </div>

            <p className="mt-5 text-[11px] leading-relaxed text-white/35">
              Illustration of the workspace. Names and scores are fictional.
            </p>
          </InkPanel>
        </ScrollReveal>
      </div>
    </section>
  );
}
