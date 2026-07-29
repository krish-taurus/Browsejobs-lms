import type { Metadata } from "next";
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
  const course = getCourseDetail((await params).slug);
  if (!course) return {};
  return {
    title: `${course.name} Course`,
    description: course.hero,
  };
}

export default async function CoursePage({
  params,
}: {
  params: Promise<{ slug: string }>;
}) {
  const course = getCourseDetail((await params).slug);
  if (!course || !course.live) notFound();

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
