import { getCourseDetail } from "@/content/courses";

/**
 * Renders the published brochure syllabus. Topics come from course content,
 * not from this file, so a brochure edit shows up here without a rewrite.
 */
export function CourseSyllabus({ slug }: { slug: "data-engineering" | "data-analytics" | "devops-cloud" }) {
  const course = getCourseDetail(slug);
  if (!course || !course.hasSyllabus) return null;

  return (
    <div className="not-prose mt-8 space-y-4">
      <ul className="flex flex-wrap gap-2">
        {course.tools.map((tool) => (
          <li key={tool} className="mono rounded-full border border-line bg-white px-3 py-1 text-[11px] text-ink2">
            {tool}
          </li>
        ))}
      </ul>
      <ol className="space-y-4">
        {course.modules.map((module, index) => (
          <li key={module.title} className="rounded-[14px] border border-line bg-white p-5">
            <p className="mono text-[11px] text-muted">Module {String(index + 1).padStart(2, "0")}</p>
            <h3 className="mt-1 text-lg font-semibold text-ink">{module.title}</h3>
            <p className="mt-2 text-[15px] leading-relaxed text-ink2">{module.hook}</p>
            <ul className="mt-3 list-disc space-y-1 pl-5 text-sm leading-relaxed text-ink2">
              {module.topics.map((topic) => (
                <li key={topic}>{topic}</li>
              ))}
            </ul>
          </li>
        ))}
      </ol>
      {course.projects.length > 0 && (
        <div>
          <h3 className="text-lg font-semibold text-ink">Projects published with this syllabus</h3>
          <ul className="mt-3 space-y-3">
            {course.projects.map((project) => (
              <li key={project.title} className="rounded-[14px] border border-line bg-sky/60 p-5">
                <p className="font-semibold text-ink">{project.title}</p>
                <p className="mt-1 text-sm leading-relaxed text-ink2">{project.body}</p>
              </li>
            ))}
          </ul>
        </div>
      )}
      {course.exploreLater && (
        <aside className="border-l-4 border-amber bg-amber/10 px-5 py-4">
          <p className="kicker text-amber">Coach note · not in the core syllabus</p>
          <p className="mt-2 text-sm leading-relaxed text-ink2">{course.exploreLater.note}</p>
          <ul className="mt-2 list-disc space-y-1 pl-5 text-sm text-ink2">
            {course.exploreLater.items.map((item) => (
              <li key={item}>{item}</li>
            ))}
          </ul>
        </aside>
      )}
    </div>
  );
}
