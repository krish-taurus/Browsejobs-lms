import Link from "next/link";
import { careerCourseCards } from "@/content/courses";
import { SITE_ORIGIN } from "@/lib/seo";

export function CareerCourses() {
  const cards = careerCourseCards();
  const itemList = {
    "@context": "https://schema.org",
    "@type": "ItemList",
    name: "Career-driven courses",
    itemListElement: cards.map((course, index) => ({
      "@type": "ListItem",
      position: index + 1,
      name: course.name,
      url: `${SITE_ORIGIN}${course.href}`,
    })),
  };

  return (
    <section id="career-courses" className="apple-ink apple-rise text-white">
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(itemList) }} />
      <div className="apple-tile text-center">
        <p className="text-[17px] font-medium text-[#a1a1a6]">Development plan</p>
        <h2 className="apple-display mx-auto mt-3 max-w-[14ch] text-[clamp(2.5rem,5vw,4.5rem)]">Career-driven courses.</h2>
        <p className="apple-sub mt-4 text-[#a1a1a6]">
          Courses focused on getting you hired. Real-world scenarios: projects, mock interviews, and the skills recruiters test for.
        </p>
        <ul className="mx-auto mt-12 grid max-w-[1080px] gap-5 text-left sm:grid-cols-2">
          {cards.map((course) => (
            <li key={course.slug} className="apple-card flex h-full flex-col p-7 md:p-8">
              <p className="text-[12px] font-medium tracking-[0.04em] text-[#6e6e73]">
                {course.duration} · {course.format}
              </p>
              <h3 className="mt-3 text-[28px] font-semibold tracking-[-0.03em] text-[#1d1d1f]">{course.name}</h3>
              <p className="mt-3 flex-1 text-[17px] leading-snug text-[#424245]">{course.line}</p>
              <div className="mt-6 flex flex-wrap items-center gap-x-5 gap-y-1">
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
        <div className="mt-12 flex flex-wrap items-center justify-center gap-x-7 gap-y-2">
          <Link href="/courses" className="apple-pill">
            Explore courses
          </Link>
          <Link href="#counselling" className="apple-more">
            Book free counselling
            <span aria-hidden>›</span>
          </Link>
          <Link href="/#interview-start" className="apple-more">
            Take the free AI interview
            <span aria-hidden>›</span>
          </Link>
        </div>
      </div>
    </section>
  );
}
