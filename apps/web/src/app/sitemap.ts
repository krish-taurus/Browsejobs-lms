import type { MetadataRoute } from "next";
import { courseDetails } from "@/content/courses";
import { courses } from "@/content/landing";
import { salaryPages } from "@/content/salaries";
import { skillPages } from "@/content/skills";
import { SITE_ORIGIN } from "@/lib/seo";
import { seoMoneyLinks } from "@/content/seo-nav";

/** Only URLs that both content sources agree are live, so the sitemap never lists a 404. */
const liveCourseSlugs = courses
  .filter((course) => course.live && courseDetails.some((detail) => detail.slug === course.slug && detail.live))
  .map((course) => course.slug);

export default function sitemap(): MetadataRoute.Sitemap {
  const dated = (path: string, priority: number): MetadataRoute.Sitemap[number] => ({
    url: path === "/" ? SITE_ORIGIN : `${SITE_ORIGIN}${path}`,
    priority,
  });

  return [
    dated("/", 1),
    dated("/courses", 0.9),
    ...liveCourseSlugs.map((slug) => dated(`/courses/${slug}`, 0.8)),
    dated("/masterclass", 0.9),
    dated("/employers", 0.9),
    dated("/jobs", 0.8),
    dated("/brief", 0.8),
    dated("/salaries", 0.8),
    ...salaryPages.map((page) => dated(`/salaries/${page.slug}`, 0.7)),
    dated("/skills", 0.8),
    ...skillPages.map((page) => dated(`/skills/${page.slug}`, 0.7)),
    dated("/reviews", 0.6),
    ...seoMoneyLinks.map((page) => dated(page.path, page.priority)),
  ];
}
