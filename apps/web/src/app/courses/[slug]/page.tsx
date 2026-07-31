import type { Metadata } from "next";
import { cmsMetadata, getSiteContent } from "@/lib/site-content";
import { notFound } from "next/navigation";
import { courseDetails, getCourseDetail } from "@/content/courses";
import CourseKeynote from "@/components/courses/CourseKeynote";

/**
 * Course detail page — keynote template (approved from the /v3 preview):
 * hero → tools → live market demand → interactive module journey → smart
 * systems on this course → interview intel + downloadable question bank →
 * projects → reviews → free-first closer. SEO metadata + Course JSON-LD kept
 * from the previous page.
 */

export function generateStaticParams() {
  return courseDetails.filter((c) => c.live).map((c) => ({ slug: c.slug }));
}

export async function generateMetadata({
  params,
}: {
  params: Promise<{ slug: string }>;
}): Promise<Metadata> {
  const slug = (await params).slug;
  const course = getCourseDetail(slug);
  if (!course) return {};

  // CRM-managed override for this course's path, else the course content.
  return cmsMetadata(`/courses/${slug}`, {
    title: `${course.name} Course`,
    description: course.hero,
  });
}

export default async function CoursePage({
  params,
}: {
  params: Promise<{ slug: string }>;
}) {
  const slug = (await params).slug;
  const base = getCourseDetail(slug);
  if (!base || !base.live) notFound();

  // CRM-managed copy overrides (Website Content → Course pages): only string
  // fields that already exist on the course are merged, so structured data
  // (modules, tools, projects) can never be corrupted from the editor.
  const content = await getSiteContent();
  const override = (content[`course:${slug}`] ?? {}) as Record<string, unknown>;
  const course = { ...base };
  for (const [key, value] of Object.entries(override)) {
    if (typeof value === "string" && value.trim() !== "" && typeof (base as Record<string, unknown>)[key] === "string") {
      (course as Record<string, unknown>)[key] = value;
    }
  }

  const jsonLd = {
    "@context": "https://schema.org",
    "@type": "Course",
    name: course.name,
    description: course.hero,
    provider: { "@type": "EducationalOrganization", name: "BrowseJobs" },
    offers: { "@type": "Offer", category: "Registration", price: "30000", priceCurrency: "INR" },
  };

  return (
    <>
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd) }} />
      <CourseKeynote course={course} />
    </>
  );
}
