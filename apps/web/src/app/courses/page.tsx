import type { Metadata } from "next";
import { ApShell } from "@/components/ap/ApShell";
import { SRead, SSection1, SSoon, SStories, STop } from "@/components/ap/generated/courses";
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
      <ApShell current="courses">
        <STop />
        <SSection1 />
        <SStories />
        <SSoon />
        <SRead />
      </ApShell>
    </>
  );
}
