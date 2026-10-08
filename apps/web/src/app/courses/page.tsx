import type { Metadata } from "next";
import { ArgusFrame } from "@/components/argus/ArgusFrame";
import { CoursesHub } from "@/components/argus/courses/CoursesHub";
import { careerCourseCards } from "@/content/courses";
import { canonical, SITE_ORIGIN } from "@/lib/seo";

export const metadata: Metadata = {
  title: "Programs",
  description:
    "Career programs rebuilt monthly from real interviews — Data Engineering, DevOps & Cloud, Python Backend, Data Analytics, and more.",
  alternates: { canonical: canonical("/courses") },
};

export default function CoursesPage() {
  const live = careerCourseCards();
  const list = {
    "@context": "https://schema.org",
    "@type": "ItemList",
    name: "Career-driven courses",
    itemListElement: live.map((course, index) => ({
      "@type": "ListItem",
      position: index + 1,
      name: course.name,
      url: `${SITE_ORIGIN}${course.href}`,
    })),
  };

  return (
    <>
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(list) }} />
      <ArgusFrame className="argus-home argus-courses-page">
        <CoursesHub />
      </ArgusFrame>
    </>
  );
}
