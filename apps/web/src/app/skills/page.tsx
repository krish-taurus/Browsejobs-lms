import type { Metadata } from "next";
import Link from "next/link";
import { MarketingShell } from "@/components/landing/MarketingShell";
import { ScrollReveal } from "@/components/motion/ScrollReveal";
import { Kicker } from "@/components/brand/Kicker";
import { skillPages } from "@/content/skills";

export const metadata: Metadata = {
  title: "Tech Skills in Demand in India — What Interviews Ask",
  description:
    "Which skills Indian tech interviews test right now — demand direction, real interview questions, hiring cities and the salaries behind SQL, Python, Spark, Kubernetes and more.",
};

/** /skills index — the engine's tracked skills as a browsable set. */
export default function SkillsIndex() {
  return (
    <MarketingShell>
      <section className="mx-auto max-w-6xl px-5 py-16 md:py-24">
        <ScrollReveal>
          <Kicker>Skill intelligence</Kicker>
          <h1 className="display mt-3 max-w-3xl text-3xl text-ink md:text-6xl">
            The skills interviews test right now
          </h1>
          <p className="mt-4 max-w-2xl text-lg text-ink2/70">
            Tracked by the engine across ~50 interviews a day — demand direction,
            the questions companies actually ask, and what the roles pay.
          </p>
        </ScrollReveal>
        <div className="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {skillPages.map((s, i) => (
            <ScrollReveal key={s.slug} delay={(i % 6) * 0.05} className="h-full">
              <Link
                href={`/skills/${s.slug}`}
                className="group flex h-full flex-col rounded-[14px] border border-line bg-white p-5 shadow-soft transition-all duration-300 hover:-translate-y-1 hover:border-trust/40"
              >
                <div className="flex items-baseline justify-between">
                  <span className="mono text-lg font-semibold text-ink">{s.name}</span>
                  <span className={`mono text-xs ${s.direction === "rising" ? "text-trust" : "text-muted"}`}>
                    {s.direction === "rising" ? "rising ↑" : "steady →"}
                  </span>
                </div>
                <p className="mt-2 flex-1 text-sm leading-relaxed text-ink2/70">{s.blurb}</p>
                <span className="mt-3 flex items-center gap-1.5 text-sm font-semibold text-trust">
                  Deep dive
                  <span className="transition-transform duration-300 group-hover:translate-x-1">→</span>
                </span>
              </Link>
            </ScrollReveal>
          ))}
        </div>
      </section>
    </MarketingShell>
  );
}
