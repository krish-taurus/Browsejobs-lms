import type { Metadata } from "next";
import { contact } from "@/content/landing";
import { getCourseDetail } from "@/content/courses";

export const SITE_ORIGIN = "https://browsejobs.ai";

export function absoluteUrl(path: string): string {
  if (path === "/") return `${SITE_ORIGIN}/`;
  return `${SITE_ORIGIN}${path.startsWith("/") ? path : `/${path}`}`;
}

/** Self-referencing canonical. Query strings are never part of the URL. */
export function moneyMetadata(page: {
  path: string;
  title: string;
  description: string;
}): Metadata {
  const url = absoluteUrl(page.path);
  return {
    title: page.title,
    description: page.description,
    alternates: { canonical: url },
    openGraph: {
      title: page.title,
      description: page.description,
      url,
      type: "website",
      siteName: "BrowseJobs",
      images: [
        {
          url: "/og.png",
          width: 1200,
          height: 630,
          alt: "BrowseJobs.ai — This syllabus was not written. It was reverse-engineered.",
        },
      ],
    },
    twitter: {
      card: "summary_large_image",
      title: page.title,
      description: page.description,
      images: ["/og.png"],
    },
    robots: { index: true, follow: true },
  };
}

export type FaqItem = { readonly q: string; readonly a: string };

export function faqNode(faqs: readonly FaqItem[]) {
  return {
    "@type": "FAQPage",
    mainEntity: faqs.map((f) => ({
      "@type": "Question",
      name: f.q,
      acceptedAnswer: { "@type": "Answer", text: f.a },
    })),
  };
}

export function breadcrumbNode(items: readonly { name: string; path: string }[]) {
  return {
    "@type": "BreadcrumbList",
    itemListElement: items.map((item, index) => ({
      "@type": "ListItem",
      position: index + 1,
      name: item.name,
      item: absoluteUrl(item.path),
    })),
  };
}

export function webPageNode(page: { path: string; title: string; description: string }) {
  return {
    "@type": "WebPage",
    "@id": `${absoluteUrl(page.path)}#webpage`,
    url: absoluteUrl(page.path),
    name: page.title,
    description: page.description,
    inLanguage: "en-GB",
    isPartOf: { "@type": "WebSite", name: "BrowseJobs", url: SITE_ORIGIN },
  };
}

/** One Course entity, shared by the location pages and /courses/data-engineering. */
export function dataEngineeringCourseNode() {
  const course = getCourseDetail("data-engineering");
  return {
    "@type": "Course",
    "@id": `${SITE_ORIGIN}/courses/data-engineering#course`,
    name: course?.name ?? "Data Engineering",
    description: course?.hero,
    url: `${SITE_ORIGIN}/courses/data-engineering`,
    provider: {
      "@type": "EducationalOrganization",
      name: "BrowseJobs",
      url: SITE_ORIGIN,
      telephone: contact.phone,
      email: contact.email,
      address: {
        "@type": "PostalAddress",
        addressLocality: "Bengaluru",
        addressRegion: "Karnataka",
        postalCode: "560066",
        addressCountry: "IN",
      },
    },
    offers: {
      "@type": "Offer",
      category: "Registration",
      price: "30000",
      priceCurrency: "INR",
      url: `${SITE_ORIGIN}/pay-after-placement`,
    },
    hasCourseInstance: {
      "@type": "CourseInstance",
      courseMode: "Online",
      courseWorkload: "P6M",
      inLanguage: "en",
      location: {
        "@type": "Place",
        name: "BrowseJobs, Whitefield",
        address: {
          "@type": "PostalAddress",
          addressLocality: "Bengaluru",
          addressRegion: "Karnataka",
          postalCode: "560066",
          addressCountry: "IN",
        },
      },
    },
    teaches: course?.tools,
  };
}

export function jsonLdGraph(nodes: readonly object[]) {
  return {
    "@context": "https://schema.org",
    "@graph": nodes,
  };
}
