import Image from "next/image";
import { careerCourseCards } from "@/content/courses";

const COVERS = new Set(["data-engineering", "devops-cloud", "data-analytics", "python-backend"]);

/** The four live course tiles, with the Higgsfield studio renders as covers. */
export function ApCourseTiles() {
  const cards = careerCourseCards();
  return (
    <div className="tiles-2" data-reveal-kids="">
      {cards.map((course) => (
        <article key={course.slug} className="tile course-tile" data-tilt="">
          <div className="tile-pad">
            <p className="eyebrow-sm">
              {course.duration} · {course.format}
            </p>
            <h3 className="h-tile">{course.name}</h3>
            <p className="body">{course.line}</p>
            <div className="cta-row">
              <a className="more" href={course.href}>
                View course <span className="chev" aria-hidden="true">›</span>
              </a>
              <a className="more" href={`/courses/enquire?course=${course.slug}`}>
                Enquire <span className="chev" aria-hidden="true">›</span>
              </a>
            </div>
          </div>
          {COVERS.has(course.slug) && (
            <div className="course-cover">
              <Image src={`/media/courses/${course.slug}.webp`} alt="" width={960} height={716} sizes="(min-width: 834px) 520px, 92vw" />
            </div>
          )}
        </article>
      ))}
    </div>
  );
}
