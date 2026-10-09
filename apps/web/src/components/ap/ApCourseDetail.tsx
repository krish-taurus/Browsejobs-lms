import Link from "next/link";
import Image from "next/image";
import { SyllabusDownload } from "@/components/courses/SyllabusDownload";
import { SStories } from "@/components/ap/generated/course";
import type { CourseDetail } from "@/content/courses";

const DISCLAIMER =
  "Based on BrowseJobs internal data. Historical figures — not a promise of individual outcome. Hiring depends on the live market and your performance.";
const COVERS = new Set(["data-engineering", "devops-cloud", "data-analytics", "python-backend"]);

/** Plain-language course FAQ, built from the course's own facts (also used for FAQPage JSON-LD). */
export function courseFaqs(course: CourseDetail) {
  const modules = course.modules.map((m) => m.title);
  const list = modules.length > 1 ? `${modules.slice(0, -1).join(", ")} and ${modules[modules.length - 1]}` : modules[0] ?? "";
  return [
    {
      q: `How long is the BrowseJobs ${course.name} course?`,
      a: `${course.duration}. ${course.format}.`,
    },
    {
      q: `What will I learn in the ${course.name} course?`,
      a: `${list}. You finish with ${course.projects.length} projects for your CV.`,
    },
    {
      q: "Do I pay before I get a job?",
      a: "Registration is ₹30,000, paid after three free steps. The placement fee is your first three months' CTC (salary), due only after you accept a job offer.",
    },
    {
      q: "Is a job guaranteed after the course?",
      a: "No. Nobody can honestly guarantee a job, because the job market decides. What we put in writing is the process.",
    },
  ];
}

/** A live course page in the approved Apple-direction design, driven by content/courses.ts. */
export function ApCourseDetail({ course }: { course: CourseDetail }) {
  const enquire = `/courses/enquire?course=${course.slug}`;
  const faqs = courseFaqs(course);
  return (
    <>
      <nav className="lnav" aria-label={course.name}>
        <div className="lnav-inner">
          <span className="lnav-title">{course.name}</span>
          <div className="lnav-right">
            <ul className="lnav-links">
              <li>
                <a href="#overview">Overview</a>
              </li>
              <li>
                <a href="#syllabus">Syllabus</a>
              </li>
              <li>
                <a href="#tools">Tools</a>
              </li>
              <li>
                <a href="#projects">Projects</a>
              </li>
              <li>
                <a href="#faq">FAQ</a>
              </li>
            </ul>
            <a className="btn btn-primary" href={enquire}>
              Ask about this course
            </a>
          </div>
        </div>
      </nav>

      <section className="s-white course-hero" id="overview">
        <div className="wrap center">
          <p className="eyebrow">
            {course.duration} · {course.format}
          </p>
          <h1 className="h-hero" style={{ fontSize: "clamp(40px,6.8vw,80px)" }}>
            {course.name} Course with Placement <span className="payoff">Pay after you&apos;re hired.</span>
          </h1>
          <p className="lead">{course.hero}</p>
          <ul className="tools" style={{ marginTop: 22 }}>
            <li>
              <b>{course.duration}</b>
            </li>
            <li>{course.format}</li>
            <li>{course.modules.length} modules</li>
            <li>{course.projects.length} CV-ready projects</li>
          </ul>
          <div className="cta-row">
            <a className="btn btn-primary" href={enquire}>
              Ask about this course
            </a>
            <a className="more" href="#syllabus">
              See the syllabus <span className="chev" aria-hidden="true">›</span>
            </a>
          </div>
        </div>
        {COVERS.has(course.slug) && (
          <div className="wrap">
            <div className="course-cover" style={{ maxWidth: 760, margin: "8px auto 0" }}>
              <Image src={`/media/courses/${course.slug}.webp`} alt="" width={960} height={716} sizes="(min-width: 834px) 760px, 92vw" priority />
            </div>
          </div>
        )}
      </section>

      <section className="s-white chapter" id="syllabus">
        <div className="wrap center">
          <p className="eyebrow">Syllabus</p>
          <h2 className="h-section">
            {course.modules.length} modules. {course.duration}.
          </h2>
          <ol className="syllabus" style={{ textAlign: "left" }}>
            {course.modules.map((m, i) => (
              <li key={m.title}>
                <details open={i === 0}>
                  <summary>
                    <span className="n">{String(i + 1).padStart(2, "0")}</span>
                    <span className="t">
                      {m.title}
                      <small>{m.hook}</small>
                    </span>
                  </summary>
                  <ul>
                    {m.topics.map((t) => (
                      <li key={t}>{t}</li>
                    ))}
                  </ul>
                </details>
              </li>
            ))}
          </ol>
          <p className="fine">{DISCLAIMER}</p>
          {course.hasSyllabus && (
            <div className="cta-row" id="syllabus-download">
              <SyllabusDownload courseSlug={course.slug} appearance="ap" />
            </div>
          )}
        </div>
      </section>

      <section className="s-paper chapter" id="tools">
        <div className="wrap center">
          <p className="eyebrow">Tools</p>
          <h2 className="h-section">The tools you will use.</h2>
          <ul className="tools">
            {course.tools.map((t) => (
              <li key={t}>{t}</li>
            ))}
          </ul>
        </div>
      </section>

      <section className="s-white chapter" id="projects">
        <div className="wrap center">
          <p className="eyebrow">{course.projects.length} CV-ready projects</p>
          <h2 className="h-section">Deployed, not just coded.</h2>
          <div className="tiles-3" style={{ marginTop: "clamp(32px,4vw,52px)", textAlign: "left" }} data-reveal-kids="">
            {course.projects.map((p) => (
              <article key={p.title} className="tile tile-pad project">
                <h3 className="h-card">{p.title}</h3>
                <p className="body">{p.body}</p>
                {p.points.length > 0 && (
                  <div className="flowline" aria-hidden="true">
                    {p.points.slice(0, 4).map((pt, i) => (
                      <span key={pt} style={{ display: "contents" }}>
                        {i > 0 && <i>→</i>}
                        <span>{pt}</span>
                      </span>
                    ))}
                  </div>
                )}
              </article>
            ))}
          </div>
        </div>
      </section>

      <SStories />

      <section className="s-paper chapter" id="faq">
        <div className="wrap center">
          <h2 className="h-section">Questions about this course.</h2>
          <div className="faq">
            {faqs.map((f) => (
              <details key={f.q}>
                <summary>
                  <h3 className="q">{f.q}</h3>
                </summary>
                <p className="answer">{f.a}</p>
              </details>
            ))}
          </div>
          <div className="cta-row">
            <a className="btn btn-primary" href={enquire}>
              Ask about this course
            </a>
            <Link className="more" href="/#top">
              Take the free AI interview first <span className="chev" aria-hidden="true">›</span>
            </Link>
          </div>
        </div>
      </section>

      <div className="sticky-enquire">
        <a className="btn btn-primary" href={enquire}>
          Ask about this course
        </a>
      </div>
    </>
  );
}
