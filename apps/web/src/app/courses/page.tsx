import type { Metadata } from "next";
import Link from "next/link";
import { MarketingShell } from "@/components/landing/MarketingShell";
import { Kicker } from "@/components/brand/Kicker";
import { ScrollReveal } from "@/components/motion/ScrollReveal";
import { courseDetails } from "@/content/courses";
import { courses } from "@/content/landing";

export const metadata: Metadata = {
  title: "Programs",
  description:
    "Career programs rebuilt monthly from real interviews — Data Engineering, DevOps & Cloud, Python Backend, Data Analytics, and more.",
};

export default function CoursesPage() {
  const detailSlugs = new Set(courseDetails.filter((c) => c.live).map((c) => c.slug));
  const live = courses.filter((c) => c.live);
  const soon = courses.filter((c) => !c.live);

  return (
    <MarketingShell>
      <div className="mx-auto max-w-6xl px-5 py-16 md:py-24">
        <ScrollReveal>
          <Kicker>Programs</Kicker>
          <h1 className="display mt-3 max-w-3xl text-4xl text-ink md:text-6xl">
            Every program, rebuilt monthly from real interviews
          </h1>
        </ScrollReveal>

        <div className="mt-12 grid gap-5 md:grid-cols-2">
          {live.map((c, i) => {
            const detail = courseDetails.find((d) => d.slug === c.slug);
            const hasDetail = detailSlugs.has(c.slug);
            const Card = (
              <article className="group flex h-full flex-col rounded-[22px] border border-line bg-white p-8 transition-all duration-300 hover:-translate-y-1.5 hover:border-trust/40 hover:shadow-soft md:p-10">
                <div className="flex items-center justify-between">
                  <span className="mono rounded-full bg-sky px-3 py-1 text-xs font-semibold text-deep">{c.code}</span>
                  {detail && <span className="mono text-xs text-muted">{detail.duration}</span>}
                </div>
                <h2 className="display mt-6 text-3xl text-ink md:text-4xl">{c.name}</h2>
                <p className="mt-3 flex-1 text-ink2/70">{c.tagline}</p>
                <div className="mt-7 flex items-center justify-between border-t border-line pt-5">
                  <span className="mono text-xs text-muted">{detail?.projectsLabel ?? "Live program"}</span>
                  {hasDetail && (
                    <span className="flex items-center gap-1.5 text-sm font-semibold text-trust">
                      View syllabus
                      <span className="transition-transform duration-300 group-hover:translate-x-1">→</span>
                    </span>
                  )}
                </div>
              </article>
            );
            return (
              <ScrollReveal key={c.code} delay={i * 0.06}>
                {hasDetail ? <Link href={`/courses/${c.slug}`}>{Card}</Link> : Card}
              </ScrollReveal>
            );
          })}
        </div>

        <ScrollReveal delay={0.1}>
          <div className="mt-6 grid gap-4 sm:grid-cols-3">
            {soon.map((c) => (
              <div key={c.code} className="flex items-center justify-between rounded-[14px] border border-dashed border-line bg-white/60 px-5 py-5">
                <span>
                  <span className="display block text-lg text-ink">{c.name}</span>
                  <span className="mono mt-1 block text-[10px] uppercase tracking-widest text-muted">Waitlist</span>
                </span>
              </div>
            ))}
          </div>
        </ScrollReveal>
      </div>
    </MarketingShell>
  );
}
