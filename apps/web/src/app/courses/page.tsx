import type { Metadata } from "next";
import Link from "next/link";
import { AppleShell } from "@/components/apple/AppleShell";
import { GoogleReviews } from "@/components/apple/GoogleReviews";
import { careerCourseCards, courseDetails } from "@/content/courses";
import { courses } from "@/content/landing";
import { seoMoneyLinks } from "@/content/seo-nav";
import { canonical, SITE_ORIGIN } from "@/lib/seo";

export const metadata: Metadata = {
  title: "Programs",
  description:
    "Career programs rebuilt monthly from real interviews — Data Engineering, DevOps & Cloud, Python Backend, Data Analytics, and more.",
  alternates: { canonical: canonical("/courses") },
};

export default function CoursesPage() {
  const live = careerCourseCards();
  const waitlist = courses.filter((course) => !course.live && !courseDetails.some((detail) => detail.slug === course.slug && detail.live));
  const list = {
    "@context": "https://schema.org",
    "@type": "ItemList",
    name: "Career-driven courses",
    itemListElement: live.map((course, index) => ({
      "@type": "ListItem",
      position: index + 1,
      name: course.name,
      url: `${SITE_ORIGIN}${course.href}`,
    })),
  };

  return (
    <AppleShell>
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(list) }} />
      <section className="bg-white px-5 pb-10 pt-12 text-center md:pt-16">
        <p className="text-[17px] font-medium text-[#6e6e73]">Programs</p>
        <h1 className="apple-display mx-auto mt-3 max-w-[16ch] text-[clamp(2.75rem,6vw,5rem)]">Career-driven courses.</h1>
        <p className="apple-sub mt-4 text-[#424245]">
          Rebuilt monthly from real interviews. Projects, mock interviews, and the skills recruiters test for.
        </p>
        <p className="mt-6">
          <Link href="/courses/enquire" className="apple-pill">
            Ask about a course
          </Link>
        </p>
      </section>

      <section className="apple-ink">
        <div className="apple-tile">
          <ul className="mx-auto grid max-w-[1080px] gap-5 sm:grid-cols-2">
            {live.map((course) => (
              <li key={course.slug} className="apple-card flex h-full flex-col p-7 md:p-8">
                <p className="text-[12px] font-medium tracking-[0.04em] text-[#6e6e73]">
                  {course.duration} · {course.format}
                </p>
                <h2 className="mt-3 text-[32px] font-semibold tracking-[-0.03em] text-[#1d1d1f]">{course.name}</h2>
                <p className="mt-3 flex-1 text-[17px] leading-snug text-[#424245]">{course.line}</p>
                <div className="mt-6 flex flex-wrap items-center gap-x-5">
                  <Link href={course.href} className="apple-more">
                    View course
                    <span aria-hidden>›</span>
                  </Link>
                  <Link href={`/courses/enquire?course=${course.slug}`} className="apple-more">
                    Enquire
                    <span aria-hidden>›</span>
                  </Link>
                </div>
              </li>
            ))}
          </ul>
        </div>
      </section>

      <section className="bg-white px-5 pb-4 text-center">
        <div className="mx-auto max-w-[1100px] pb-8">
          <GoogleReviews />
        </div>
      </section>

      <section className="bg-[#f5f5f7] px-5 py-16">
        <div className="mx-auto grid max-w-[1080px] gap-4 sm:grid-cols-3">
          {waitlist.map((course) => (
            <div key={course.code} className="rounded-[22px] border border-dashed border-black/15 bg-white/70 px-5 py-5">
              <p className="text-[21px] font-semibold tracking-[-0.02em] text-[#1d1d1f]">{course.name}</p>
              <p className="mt-1 text-[13px] uppercase tracking-[0.12em] text-[#6e6e73]">Waitlist</p>
            </div>
          ))}
        </div>
        <div className="mx-auto mt-16 max-w-[720px] border-t border-black/10 pt-12">
          <h2 className="apple-display text-[clamp(1.75rem,3vw,2.5rem)]">Read the fee, the city, and the switch before you book</h2>
          <ul className="mt-6 grid gap-2 sm:grid-cols-2">
            {seoMoneyLinks
              .filter((item) => item.path !== "/ai-hiring" && item.path !== "/ai-interview-platform")
              .map((item) => (
                <li key={item.path}>
                  <Link href={item.path} className="apple-more">
                    {item.footerLabel}
                  </Link>
                </li>
              ))}
            <li>
              <Link href="/answers" className="apple-more">
                Straight answers
              </Link>
            </li>
          </ul>
        </div>
      </section>
    </AppleShell>
  );
}
