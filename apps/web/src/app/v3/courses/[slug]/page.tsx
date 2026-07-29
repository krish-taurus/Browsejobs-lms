import { notFound } from "next/navigation";
import { courseDetails, getCourseDetail } from "@/content/courses";
import CourseKeynote from "@/components/courses/CourseKeynote";

/** Preview alias of the keynote course template (the live route is /courses/[slug]). */

export function generateStaticParams() {
  return courseDetails.filter((c) => c.live).map((c) => ({ slug: c.slug }));
}

export default async function CourseKeynotePreview({
  params,
}: {
  params: Promise<{ slug: string }>;
}) {
  const course = getCourseDetail((await params).slug);
  if (!course || !course.live) notFound();
  return <CourseKeynote course={course} />;
}
