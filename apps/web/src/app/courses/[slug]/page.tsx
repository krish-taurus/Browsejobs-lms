import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { courseDetails, getCourseDetail } from "@/content/courses";
import CourseKeynote from "@/components/courses/CourseKeynote";
import { breadcrumbNode, canonical, courseNode, jsonLdGraph, webPageNode } from "@/lib/seo";

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
  const title = course.seoTitle ?? `${course.name} Course`;
  const url = canonical(`/courses/${course.slug}`);
  return {
    title,
    description: course.hero,
    alternates: { canonical: url },
    openGraph: { title, description: course.hero, url },
  };
}

export default async function CoursePage({
  params,
}: {
  params: Promise<{ slug: string }>;
}) {
  const course = getCourseDetail((await params).slug);
  if (!course || !course.live) notFound();

  const path = `/courses/${course.slug}`;
  const title = course.seoTitle ?? `${course.name} Course`;
  const node = courseNode(course.slug);
  const jsonLd = jsonLdGraph([
    webPageNode({ path, title, description: course.hero }),
    breadcrumbNode([
      { name: "Home", path: "/" },
      { name: "Courses", path: "/courses" },
      { name: course.name, path },
    ]),
    ...(node ? [node] : []),
  ]);

  return (
    <>
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd) }} />
      <CourseKeynote course={course} />
    </>
  );
}
