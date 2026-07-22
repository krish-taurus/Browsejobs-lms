import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { MarketingShell } from "@/components/landing/MarketingShell";
import { BookCta } from "@/components/landing/BookCta";
import { Disclaimer } from "@/components/brand/Disclaimer";
import { ScrollReveal } from "@/components/motion/ScrollReveal";
import { SyllabusAccordion } from "@/components/courses/SyllabusAccordion";
import { SyllabusDownload } from "@/components/courses/SyllabusDownload";
import { CourseSocialProof } from "@/components/courses/CourseSocialProof";
import {
  careerServices,
  courseDetails,
  getCourseDetail,
  placementChannels,
  situationCards,
} from "@/content/courses";

export function generateStaticParams() {
  return courseDetails.filter((c) => c.live).map((c) => ({ slug: c.slug }));
}

export async function generateMetadata({
  params,
}: {
  params: Promise<{ slug: string }>;
}): Promise<Metadata> {
  const course = getCourseDetail((await params).slug);
  if (!course) return {};
  return {
    title: `${course.name} Course`,
    description: course.hero,
  };
}

export default async function CoursePage({
  params,
}: {
  params: Promise<{ slug: string }>;
}) {
  const course = getCourseDetail((await params).slug);
  if (!course || !course.live) notFound();

  const facts = [
    { label: "Duration", value: course.duration },
    { label: "Format", value: course.format },
    { label: "Access", value: course.access },
    { label: "Projects", value: course.projectsLabel },
  ];

  const jsonLd = {
    "@context": "https://schema.org",
    "@type": "Course",
    name: course.name,
    description: course.hero,
    provider: { "@type": "EducationalOrganization", name: "BrowseJobs" },
    offers: { "@type": "Offer", category: "Registration", price: "30000", priceCurrency: "INR" },
  };

  return (
    <>
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd) }} />
      <MarketingShell>
        {/* Hero */}
        <section className="relative overflow-hidden">
          <div
            aria-hidden
            className="pointer-events-none absolute inset-0 -z-10"
            style={{ background: "radial-gradient(60% 50% at 50% 0%, var(--bj-sky) 0%, transparent 70%)" }}
          />
          <div className="mx-auto max-w-6xl px-5 pt-16 pb-12 md:pt-24">
            <ScrollReveal>
              <p className="kicker text-trust">
                Career program · {course.code} ·{" "}
                <Link href="/courses" className="hover:underline">all programs</Link>
              </p>
              <h1 className="display mt-4 max-w-3xl text-4xl text-ink md:text-5xl">{course.name}</h1>
              <p className="mt-5 max-w-2xl text-lg text-ink2/80">{course.hero}</p>
            </ScrollReveal>

            <ScrollReveal delay={0.1}>
              <div className="mt-8 flex flex-wrap gap-3">
                {facts.map((f) => (
                  <div key={f.label} className="rounded-full border border-line bg-white px-4 py-2">
                    <span className="mono text-xs uppercase tracking-widest text-muted">{f.label}</span>{" "}
                    <span className="mono ml-1.5 text-sm font-semibold text-ink">{f.value}</span>
                  </div>
                ))}
              </div>
            </ScrollReveal>

            <ScrollReveal delay={0.16}>
              <div className="mt-8 flex flex-col gap-3 sm:flex-row">
                <BookCta courseSlug={course.slug} className="px-8 py-3.5" />
                <BookCta variant="counselling" courseSlug={course.slug} ghost className="px-8 py-3.5">
                  Free counselling + career report
                </BookCta>
              </div>
            </ScrollReveal>
          </div>
        </section>

        {course.hasSyllabus ? (
          <>
            {/* Outcomes + roles */}
            <section className="bg-white">
              <div className="mx-auto max-w-6xl px-5 py-16">
                <div className="grid gap-10 lg:grid-cols-[1.2fr_1fr]">
                  <ScrollReveal>
                    <p className="kicker text-trust">What you&apos;ll walk out able to do</p>
                    <ul className="mt-5 space-y-3.5">
                      {course.outcomes.map((o) => (
                        <li key={o} className="flex gap-3 text-ink">
                          <svg viewBox="0 0 20 20" className="mt-1 h-5 w-5 shrink-0" fill="none">
                            <circle cx="10" cy="10" r="10" fill="var(--bj-verify)" opacity="0.14" />
                            <path d="M6 10.5l2.5 2.5L14 7.5" stroke="var(--bj-verify)" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
                          </svg>
                          <span>{o}</span>
                        </li>
                      ))}
                    </ul>
                  </ScrollReveal>
                  <ScrollReveal delay={0.08}>
                    <p className="kicker text-trust">Roles this prepares you for</p>
                    <div className="mt-5 flex flex-wrap gap-2.5">
                      {course.roles.map((r) => (
                        <span key={r} className="rounded-full bg-sky px-4 py-2 text-sm font-medium text-deep">
                          {r}
                        </span>
                      ))}
                    </div>
                    <div className="mt-8 space-y-4">
                      {situationCards.map((s) => (
                        <div key={s.kicker} className="rounded-[14px] border border-line bg-paper p-5">
                          <p className="kicker text-verify">{s.kicker}</p>
                          <p className="mt-2 text-sm text-ink2/85">{s.body}</p>
                        </div>
                      ))}
                    </div>
                  </ScrollReveal>
                </div>
              </div>
            </section>

            {/* Syllabus */}
            <section className="mx-auto max-w-6xl px-5 py-16">
              <ScrollReveal>
                <p className="kicker text-trust">The live syllabus — rebuilt monthly</p>
                <h2 className="display mt-3 max-w-2xl text-3xl text-ink md:text-4xl">
                  {course.modules.length} modules. Zero filler.
                </h2>
                <p className="mt-3 max-w-2xl text-muted">
                  Every topic below appeared in a monitored interview for this
                  role — that is the only reason it is here.
                </p>
              </ScrollReveal>
              <ScrollReveal delay={0.1}>
                <div className="mt-8">
                  <SyllabusAccordion modules={course.modules} />
                </div>
              </ScrollReveal>

              {course.exploreLater && (
                <ScrollReveal delay={0.12}>
                  {/* Coach note — amber left rule (amber is reserved for coach notes). */}
                  <div className="mt-8 rounded-[14px] border border-line border-l-4 border-l-amber bg-white p-6">
                    <p className="kicker text-ink2">Worth exploring later — optional, not part of the core program</p>
                    <p className="mt-2 text-sm text-muted">{course.exploreLater.note}</p>
                    <ul className="mt-4 grid gap-2 sm:grid-cols-2">
                      {course.exploreLater.items.map((item) => (
                        <li key={item} className="flex gap-2.5 text-sm text-ink2/85">
                          <span className="mt-2 h-1 w-1 shrink-0 rounded-full bg-amber" />
                          {item}
                        </li>
                      ))}
                    </ul>
                  </div>
                </ScrollReveal>
              )}

              <ScrollReveal delay={0.14}>
                <div className="mt-8">
                  <p className="kicker text-trust">Tools &amp; technologies you&apos;ll work with</p>
                  <div className="mt-4 flex flex-wrap gap-2.5">
                    {course.tools.map((t) => (
                      <span key={t} className="mono rounded-full border border-line bg-white px-3.5 py-1.5 text-xs text-ink">
                        {t}
                      </span>
                    ))}
                  </div>
                </div>
              </ScrollReveal>
            </section>

            {/* Projects */}
            <section className="bg-white">
              <div className="mx-auto max-w-6xl px-5 py-16">
                <ScrollReveal>
                  <p className="kicker text-trust">Real projects you&apos;ll build</p>
                  <h2 className="display mt-3 max-w-2xl text-3xl text-ink md:text-4xl">These go on your CV.</h2>
                  <p className="mt-3 max-w-2xl text-muted">
                    Not toy exercises — industry-grade work you deliver, deploy
                    and can walk an interviewer through line by line. Fully
                    genuine, so every candidate clears background verification.
                  </p>
                </ScrollReveal>
                <div className="mt-10 grid gap-5 md:grid-cols-2">
                  {course.projects.map((p, i) => (
                    <ScrollReveal key={p.title} delay={i * 0.06}>
                      <article className="h-full rounded-[14px] border border-line bg-paper p-7">
                        <p className="mono text-xs uppercase tracking-widest text-trust">
                          Project {String(i + 1).padStart(2, "0")}
                        </p>
                        <h3 className="display mt-2 text-xl text-ink">{p.title}</h3>
                        <p className="mt-2 text-sm text-muted">{p.body}</p>
                        <ul className="mt-4 grid grid-cols-2 gap-2">
                          {p.points.map((pt) => (
                            <li key={pt} className="flex gap-2 text-xs text-ink2/85">
                              <span className="mt-1.5 h-1 w-1 shrink-0 rounded-full bg-trust" />
                              {pt}
                            </li>
                          ))}
                        </ul>
                      </article>
                    </ScrollReveal>
                  ))}
                </div>
              </div>
            </section>

            {/* Placement engine */}
            <section className="mx-auto max-w-6xl px-5 py-16">
              <ScrollReveal>
                <p className="kicker text-trust">The placement engine</p>
                <h2 className="display mt-3 max-w-2xl text-3xl text-ink md:text-4xl">
                  You don&apos;t apply into a black hole. You get presented.
                </h2>
              </ScrollReveal>
              <div className="mt-10 grid gap-5 md:grid-cols-3">
                {placementChannels.map((ch, i) => (
                  <ScrollReveal key={ch.kicker} delay={i * 0.06}>
                    <div className="h-full rounded-[14px] border border-line bg-white p-6">
                      <p className="kicker text-trust">{ch.kicker}</p>
                      <h3 className="display mt-3 text-lg text-ink">{ch.title}</h3>
                      <p className="mt-2 text-sm text-muted">{ch.body}</p>
                    </div>
                  </ScrollReveal>
                ))}
              </div>
              <ScrollReveal delay={0.1}>
                <div className="mt-8 overflow-hidden rounded-[14px] border border-line bg-white">
                  <div className="divide-y divide-line">
                    {careerServices.map((row) => (
                      <div key={row.service} className="grid gap-1 px-6 py-4 sm:grid-cols-[220px_1fr] sm:gap-6">
                        <span className="font-semibold text-ink">{row.service}</span>
                        <span className="text-sm text-muted">{row.what}</span>
                      </div>
                    ))}
                  </div>
                </div>
                <p className="mt-5 max-w-3xl text-sm text-muted">
                  Readiness is decided on data. We send you to interviews when
                  your mock scores say you&apos;re ready — not when a sales target
                  says so.
                </p>
                <div className="mt-3">
                  <Disclaimer />
                </div>
              </ScrollReveal>
            </section>
          </>
        ) : (
          <section className="mx-auto max-w-6xl px-5 py-16">
            <ScrollReveal>
              <div className="rounded-[22px] border border-line bg-white p-10 text-center">
                <p className="kicker text-trust">Syllabus</p>
                <h2 className="display mx-auto mt-3 max-w-xl text-2xl text-ink">
                  The full module-by-module syllabus is shared in your free
                  counselling session
                </h2>
                <p className="mx-auto mt-3 max-w-xl text-muted">
                  Like every BrowseJobs program, it is rebuilt monthly from
                  monitored interviews — so we walk you through the current
                  edition live.
                </p>
                <div className="mt-7">
                  <BookCta variant="counselling" courseSlug={course.slug} className="px-8 py-3.5">
                    Book free counselling
                  </BookCta>
                </div>
              </div>
            </ScrollReveal>
          </section>
        )}

        {/* Social proof — placement stories, interview-questions popup, reviews */}
        <CourseSocialProof slug={course.slug} />

        {/* CTA band */}
        <section className="mx-auto max-w-6xl px-5 pb-16">
          <ScrollReveal>
            <div className="rounded-[22px] bg-ink px-8 py-12 text-center text-white shadow-soft">
              <p className="kicker text-sky/70">Three free steps first</p>
              <h2 className="display mx-auto mt-3 max-w-2xl text-3xl">
                Judge the teaching before you pay a rupee
              </h2>
              <div className="mt-7 flex flex-wrap items-center justify-center gap-3">
                <BookCta courseSlug={course.slug} className="px-8 py-3.5" />
                <SyllabusDownload courseSlug={course.slug} />
              </div>
            </div>
          </ScrollReveal>
        </section>
      </MarketingShell>
    </>
  );
}
