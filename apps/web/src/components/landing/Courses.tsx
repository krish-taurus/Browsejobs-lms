import Link from "next/link";
import { ScrollReveal } from "@/components/motion/ScrollReveal";
import { BookCta } from "@/components/landing/BookCta";
import { courses } from "@/content/landing";

/** 7 programs: 4 live + 3 waitlist (spec §6.1). */
export function Courses() {
  const live = courses.filter((c) => c.live);
  const soon = courses.filter((c) => !c.live);

  return (
    <section id="courses" className="mx-auto max-w-6xl px-5 py-20">
      <ScrollReveal>
        <p className="kicker text-trust">Programs</p>
        <h2 className="display mt-3 max-w-2xl text-3xl text-ink md:text-4xl">
          Four live tracks. Each one rebuilt monthly.
        </h2>
      </ScrollReveal>

      <div className="mt-10 grid gap-5 sm:grid-cols-2">
        {live.map((c, i) => (
          <ScrollReveal key={c.code} delay={i * 0.05}>
            <article className="group flex h-full flex-col rounded-[14px] border border-line bg-white p-7 transition-all hover:-translate-y-1 hover:border-trust/40 hover:shadow-soft">
              <div className="flex items-center justify-between">
                <span className="mono rounded-full bg-sky px-3 py-1 text-xs font-semibold text-deep">
                  {c.code}
                </span>
                <span className="flex items-center gap-1.5 text-xs font-semibold text-verify">
                  <span className="relative flex h-2 w-2">
                    <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-verify opacity-60 motion-reduce:animate-none" />
                    <span className="relative inline-flex h-2 w-2 rounded-full bg-verify" />
                  </span>
                  Live
                </span>
              </div>
              <h3 className="display mt-4 text-2xl text-ink">{c.name}</h3>
              <p className="mt-2 flex-1 text-muted">{c.tagline}</p>
              <div className="mt-5 flex flex-wrap items-center gap-3">
                <Link
                  href={`/courses/${c.slug}`}
                  className="rounded-full border border-line bg-white px-5 py-2.5 text-sm font-semibold text-ink transition-colors hover:border-trust"
                >
                  View syllabus →
                </Link>
                <BookCta courseSlug={c.slug} className="px-5 py-2.5 text-sm">
                  Book Free Masterclass
                </BookCta>
              </div>
            </article>
          </ScrollReveal>
        ))}
      </div>

      <ScrollReveal delay={0.1}>
        <div className="mt-8 rounded-[14px] border border-dashed border-line bg-white/60 p-6">
          <p className="kicker text-muted">Opening soon — join the waitlist</p>
          <div className="mt-4 flex flex-wrap gap-3">
            {soon.map((c) => (
              <BookCta
                key={c.code}
                variant="waitlist"
                courseSlug={c.slug}
                ghost
                className="px-5 py-2.5 text-sm"
              >
                {c.name} <span className="mono ml-1.5 text-xs text-muted">→ waitlist</span>
              </BookCta>
            ))}
          </div>
        </div>
      </ScrollReveal>
    </section>
  );
}
