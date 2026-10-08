"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { Disclaimer } from "@/components/brand/Disclaimer";
import { SyllabusDownload } from "@/components/courses/SyllabusDownload";
import type { CourseDetail as CourseDetailData } from "@/content/courses";
import { CourseVisual } from "./CourseVisual";

export function CourseDetail({ course }: { course: CourseDetailData }) {
  const [open, setOpen] = useState(0);
  const [stuck, setStuck] = useState(false);

  useEffect(() => {
    const onScroll = () => setStuck(window.scrollY > 320);
    onScroll();
    window.addEventListener("scroll", onScroll, { passive: true });
    return () => window.removeEventListener("scroll", onScroll);
  }, []);

  return (
    <>
      <section className="argus-section argus-hero argus-detail-hero">
        <div className="argus-detail-copy">
          <p className="argus-kicker">
            {course.duration} · {course.format}
          </p>
          <h1 className="argus-h1">{course.headline ?? course.name}</h1>
          {course.headlinePayoff ? <p className="argus-detail-payoff">{course.headlinePayoff}</p> : null}
          <p className="argus-body argus-hero-sub">{course.hero}</p>
          <div className="argus-start">
            <Link
              href={`/courses/enquire?course=${course.slug}`}
              className={stuck ? "argus-btn argus-btn-primary argus-enquire-pill is-stuck" : "argus-btn argus-btn-primary argus-enquire-pill"}
            >
              Ask about this course
            </Link>
          </div>
        </div>
        <CourseVisual slug={course.slug} enlarged />
      </section>

      {course.modules.length > 0 ? (
        <section className="argus-section argus-syllabus">
          <p className="argus-kicker">Syllabus</p>
          <h2 className="argus-h2">
            {course.modules.length} modules. {course.duration}.
          </h2>
          <ol className="argus-timeline">
            {course.modules.map((module, index) => {
              const expanded = open === index;
              return (
                <li key={module.title} className={expanded ? "is-open" : undefined}>
                  <button type="button" aria-expanded={expanded} onClick={() => setOpen(expanded ? -1 : index)}>
                    <span>{String(index + 1).padStart(2, "0")}</span>
                    <strong>{module.title}</strong>
                  </button>
                  {expanded ? (
                    <div className="argus-timeline-body">
                      <p>{module.hook}</p>
                      <ul>
                        {module.topics.map((topic) => (
                          <li key={topic}>{topic}</li>
                        ))}
                      </ul>
                    </div>
                  ) : null}
                </li>
              );
            })}
          </ol>
          <Disclaimer tone="argus" />
          {course.hasSyllabus ? (
            <div className="argus-start">
              <SyllabusDownload courseSlug={course.slug} appearance="argus" />
            </div>
          ) : null}
        </section>
      ) : null}

      {course.tools.length > 0 ? (
        <section className="argus-section argus-course-tools">
          <p className="argus-kicker">Tools</p>
          <ul>
            {course.tools.map((tool) => (
              <li key={tool}>{tool}</li>
            ))}
          </ul>
        </section>
      ) : null}

      {course.projects.length > 0 ? (
        <section className="argus-section argus-course-projects">
          <p className="argus-kicker">{course.projectsLabel} projects</p>
          <ul>
            {course.projects.map((project) => (
              <li key={project.title}>
                <h2>{project.title}</h2>
                <p>{project.body}</p>
              </li>
            ))}
          </ul>
        </section>
      ) : null}
    </>
  );
}
