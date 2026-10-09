import type { MetadataRoute } from "next";
import { answerPages, answerPath, priorityAnswerSlugs } from "@/content/answers";
import { courseDetails } from "@/content/courses";
import { courses } from "@/content/landing";
import { contentLastModified, PAGE_UPDATED } from "@/content/last-modified";
import { SALARIES_UPDATED, salaryPages } from "@/content/salaries";
import { seoMoneyLinks } from "@/content/seo-nav";
import { SKILLS_UPDATED, skillPages } from "@/content/skills";
import { SITE_ORIGIN } from "@/lib/seo";

/** Only URLs that both content sources agree are live, so the sitemap never lists a 404. */
const liveCourseSlugs = courses
  .filter((course) => course.live && courseDetails.some((detail) => detail.slug === course.slug && detail.live))
  .map((course) => course.slug);

export default function sitemap(): MetadataRoute.Sitemap {
  const dated = (path: string, priority: number, lastModified: string): MetadataRoute.Sitemap[number] => ({
    url: path === "/" ? SITE_ORIGIN : `${SITE_ORIGIN}${path}`,
    lastModified: contentLastModified(path, lastModified),
    priority,
  });

  return [
    dated("/", 1, PAGE_UPDATED["/"]),
    dated("/courses", 0.9, PAGE_UPDATED["/courses"]),
    ...liveCourseSlugs.map((slug) => {
      const detail = courseDetails.find((course) => course.slug === slug);
      if (!detail) {
        throw new Error(`Live course ${slug} is missing from courseDetails.`);
      }
      return dated(`/courses/${slug}`, 0.8, detail.updatedAt);
    }),
    dated("/masterclass", 0.9, PAGE_UPDATED["/masterclass"]),
    dated("/employers", 0.9, PAGE_UPDATED["/employers"]),
    dated("/get-hired", 0.9, PAGE_UPDATED["/get-hired"]),
    dated("/jobs", 0.8, PAGE_UPDATED["/jobs"]),
    dated("/brief", 0.8, PAGE_UPDATED["/brief"]),
    dated("/salaries", 0.8, SALARIES_UPDATED),
    ...salaryPages.map((page) => dated(`/salaries/${page.slug}`, 0.7, page.updatedAt ?? SALARIES_UPDATED)),
    dated("/skills", 0.8, SKILLS_UPDATED),
    ...skillPages.map((page) => dated(`/skills/${page.slug}`, 0.7, page.updatedAt ?? SKILLS_UPDATED)),
    dated("/reviews", 0.6, PAGE_UPDATED["/reviews"]),
    ...seoMoneyLinks.map((page) => dated(page.path, page.priority, page.updatedAt)),
    dated("/founder", 0.5, PAGE_UPDATED["/founder"]),
    dated("/answers", 0.75, PAGE_UPDATED["/answers"]),
    ...answerPages.map((page) =>
      dated(
        answerPath(page.slug),
        priorityAnswerSlugs.has(page.slug) ? 0.8 : 0.7,
        page.updatedAt ?? PAGE_UPDATED["/answers"],
      ),
    ),
  ];
}
