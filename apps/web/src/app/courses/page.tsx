import type { Metadata } from "next";
import Link from "next/link";
import { Nav } from "@/components/landing/Nav";
import { Footer } from "@/components/landing/Footer";
import { LeadModal } from "@/components/landing/LeadModal";
import { StickyCta } from "@/components/landing/StickyCta";
import { BookCta } from "@/components/landing/BookCta";
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
    <>
      <Nav />
      <main className="mx-auto max-w-6xl px-5 py-16">
        <ScrollReveal>
          <p className="kicker text-trust">Programs</p>
          <h1 className="display mt-3 max-w-3xl text-4xl text-ink md:text-5xl">
            Every program, rebuilt monthly from real interviews
          </h1>
        </ScrollReveal>

        <div className="mt-12 grid gap-5 sm:grid-cols-2">
          {live.map((c, i) => {
            const detail = courseDetails.find((d) => d.slug === c.slug);
            return (
              <ScrollReveal key={c.code} delay={i * 0.05}>
                <article className="flex h-full flex-col rounded-[14px] border border-line bg-white p-7 transition-all hover:-translate-y-1 hover:border-trust/40 hover:shadow-soft">
                  <div className="flex items-center justify-between">
                    <span className="mono rounded-full bg-sky px-3 py-1 text-xs font-semibold text-deep">{c.code}</span>
                    {detail && (
                      <span className="mono text-xs text-muted">{detail.duration}</span>
                    )}
                  </div>
                  <h2 className="display mt-4 text-2xl text-ink">{c.name}</h2>
                  <p className="mt-2 flex-1 text-muted">{c.tagline}</p>
                  <div className="mt-6 flex flex-wrap items-center gap-3">
                    {detailSlugs.has(c.slug) && (
                      <Link
                        href={`/courses/${c.slug}`}
                        className="rounded-full border border-line bg-white px-5 py-2.5 text-sm font-semibold text-ink transition-colors hover:border-trust"
                      >
                        View full syllabus →
                      </Link>
                    )}
                    <BookCta courseSlug={c.slug} className="px-5 py-2.5 text-sm" />
                  </div>
                </article>
              </ScrollReveal>
            );
          })}
        </div>

        <ScrollReveal delay={0.1}>
          <div className="mt-10 rounded-[14px] border border-dashed border-line bg-white/60 p-6">
            <p className="kicker text-muted">Opening soon — join the waitlist</p>
            <div className="mt-4 flex flex-wrap gap-3">
              {soon.map((c) => (
                <BookCta key={c.code} variant="waitlist" courseSlug={c.slug} ghost className="px-5 py-2.5 text-sm">
                  {c.name} <span className="mono ml-1.5 text-xs text-muted">→ waitlist</span>
                </BookCta>
              ))}
            </div>
          </div>
        </ScrollReveal>
      </main>
      <Footer />
      <LeadModal />
      <StickyCta />
    </>
  );
}
