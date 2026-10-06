import { courses, faqs } from "@/content/landing";
import { courseNode, faqNode, jsonLdGraph } from "@/lib/seo";

/**
 * Homepage graph: live Course entities plus FAQPage.
 * Organization, WebSite, and the founder Person are emitted once from the root layout.
 */
export function JsonLd() {
  const data = jsonLdGraph([
    ...courses.flatMap((course) => {
      if (!course.live) return [];
      const node = courseNode(course.slug);
      return node ? [node] : [];
    }),
    faqNode(faqs),
  ]);

  return (
    <script
      type="application/ld+json"
      dangerouslySetInnerHTML={{ __html: JSON.stringify(data) }}
    />
  );
}
