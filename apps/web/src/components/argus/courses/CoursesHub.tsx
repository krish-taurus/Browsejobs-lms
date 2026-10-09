"use client";

import { useState } from "react";
import Link from "next/link";
import "@/components/apple/apple.css";
import { SuccessStories } from "@/components/apple/SuccessStories";
import { ArgusButton } from "@/components/argus/ui";
import { SplitHeading } from "@/components/argus/SplitHeading";
import { careerCourseCards } from "@/content/courses";
import { courses } from "@/content/landing";
import { seoMoneyLinks } from "@/content/seo-nav";
import { CourseVisual } from "./CourseVisual";

const READS = [
  ...seoMoneyLinks
    .filter((item) => item.path !== "/ai-hiring" && item.path !== "/ai-interview-platform" && !item.path.startsWith("/taurusai"))
    .map((item) => ({ href: item.path, label: item.footerLabel })),
  { href: "/answers", label: "Straight answers" },
];

export function CoursesHub() {
  const live = careerCourseCards();
  const waitlist = courses.filter((course) => !course.live);

  return (
    <>
      <section id="programs" className="argus-section argus-hero argus-courses-hero" data-scene="sphere" data-anchor="aside">
        <div className="argus-courses-copy">
          <p className="argus-kicker">Programs</p>
          <SplitHeading as="h1" className="argus-h1" text="Career-driven courses." />
          <p className="argus-body argus-hero-sub">
            Rebuilt monthly from real interviews. Projects, mock interviews, and the skills recruiters test for.
          </p>
          <div className="argus-start">
            <ArgusButton href="/courses/enquire">Ask about a course</ArgusButton>
          </div>
        </div>
      </section>

      <section id="course-cards" className="argus-section argus-course-board">
        <ul className="argus-course-stage">
          {live.map((course) => (
            <CourseCard key={course.slug} course={course} />
          ))}
        </ul>
      </section>

      <SuccessStories tone="argus" />

      <section id="course-waitlist" className="argus-section argus-wait-section">
        <p className="argus-kicker">Coming soon</p>
        <ul className="argus-wait-grid">
          {waitlist.map((course) => (
            <li key={course.slug}>
              <article className="argus-wait">
                <p className="argus-kicker">Coming soon</p>
                <h2>{course.name}</h2>
                <p>{course.tagline}</p>
                <span className="argus-wait-pill">Waitlist</span>
              </article>
            </li>
          ))}
        </ul>
      </section>

      <section id="course-reading" className="argus-section argus-read-section">
        <h2 className="argus-h2">Read the fee, the city, and the switch before you book</h2>
        <ul className="argus-link-rows">
          {READS.map((item) => (
            <li key={item.href}>
              <Link href={item.href}>
                <span>{item.label}</span>
                <i aria-hidden>›</i>
              </Link>
            </li>
          ))}
        </ul>
      </section>
    </>
  );
}

function CourseCard({
  course,
}: {
  course: { slug: string; name: string; line: string; duration: string; format: string; href: string };
}) {
  const [hover, setHover] = useState(false);
  return (
    <li
      className="argus-tilt"
      onMouseMove={(event) => {
        if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) return;
        const box = event.currentTarget.getBoundingClientRect();
        const x = (event.clientX - box.left) / box.width - 0.5;
        const y = (event.clientY - box.top) / box.height - 0.5;
        event.currentTarget.style.transform = `rotateX(${(-y * 6).toFixed(2)}deg) rotateY(${(x * 6).toFixed(2)}deg)`;
      }}
      onMouseEnter={() => setHover(true)}
      onMouseLeave={(event) => {
        event.currentTarget.style.transform = "";
        setHover(false);
      }}
    >
      <article className="argus-card argus-course-card">
        <CourseVisual slug={course.slug} playing={hover} />
        <p className="argus-kicker">
          {course.duration} · {course.format}
        </p>
        <h2>{course.name}</h2>
        <p>{course.line}</p>
        <div className="argus-row">
          <Link href={course.href} className="argus-more">
            View course
            <i aria-hidden>›</i>
          </Link>
          <Link href={`/courses/enquire?course=${course.slug}`} className="argus-more is-quiet">
            Enquire
            <i aria-hidden>›</i>
          </Link>
        </div>
      </article>
    </li>
  );
}
