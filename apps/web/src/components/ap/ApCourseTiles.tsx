import Image from "next/image";
import { careerCourseCards } from "@/content/courses";

const COVERS = new Set(["data-engineering", "devops-cloud", "data-analytics", "python-backend"]);

const PUBLISHED = [
  {
    slug: "agentic-ai-course",
    name: "Agentic AI",
    line: "Python, large language models, RAG, and AI agents. Six months. ₹30,000 covers the course.",
    duration: "6 months",
    format: "Live online + Whitefield",
    href: "/courses/agentic-ai-course",
  },
  {
    slug: "gen-ai",
    name: "Gen AI",
    line: "Python, LLMs, RAG, and AI agents. Six months in Bangalore. ₹30,000 covers the course.",
    duration: "6 months",
    format: "Live online + Whitefield",
    href: "/courses/gen-ai",
  },
];

/** Live course tiles, plus Agentic AI and Gen AI on the published course URL. */
export function ApCourseTiles() {
  const cards = [...careerCourseCards(), ...PUBLISHED];
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
