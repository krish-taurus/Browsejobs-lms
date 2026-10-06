import type { Metadata } from "next";
import type {
  BreadcrumbList,
  Course,
  EducationalOrganization,
  FAQPage,
  Graph,
  Person,
  Thing,
  WebPage,
  WebSite,
} from "schema-dts";
import { getCourseDetail } from "@/content/courses";
import {
  LOGO_PATH,
  founder,
  founderProfileUrl,
  officialProfileUrls,
  organizationDescription,
  publishedAddress,
  publishedHours,
} from "@/content/entity";
import { contact, fees } from "@/content/landing";

export const SITE_ORIGIN = "https://browsejobs.ai";

export const ORGANIZATION_ID = `${SITE_ORIGIN}/#organization`;
export const WEBSITE_ID = `${SITE_ORIGIN}/#website`;
export const FOUNDER_ID = `${SITE_ORIGIN}/#founder`;

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

export function faqNode(faqs: readonly FaqItem[]): FAQPage {
  return {
    "@type": "FAQPage",
    mainEntity: faqs.map((f) => ({
      "@type": "Question",
      name: f.q,
      acceptedAnswer: { "@type": "Answer", text: f.a },
    })),
  };
}

export function breadcrumbNode(items: readonly { name: string; path: string }[]): BreadcrumbList {
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

export function webPageNode(page: {
  path: string;
  title: string;
  description: string;
  dateModified?: string;
}): WebPage {
  return {
    "@type": "WebPage",
    "@id": `${absoluteUrl(page.path)}#webpage`,
    url: absoluteUrl(page.path),
    name: page.title,
    description: page.description,
    inLanguage: "en-GB",
    isPartOf: { "@id": WEBSITE_ID },
    ...(page.dateModified ? { dateModified: page.dateModified } : {}),
  };
}

/** "6 months" → P6M. A range such as "5–6 months" stays prose. Never invent a single figure. */
export function isoDuration(duration: string): string | undefined {
  const match = /^(\d+) months$/.exec(duration.trim());
  if (!match) return undefined;
  return `P${match[1]}M`;
}

/**
 * Course entity for a live programme. Fee, mode and duration come from the
 * course file and the published registration fee. Fields we cannot source are omitted.
 */
export function courseNode(slug: string): Course | undefined {
  const course = getCourseDetail(slug);
  if (!course?.live) return undefined;
  if (!course.format.toLowerCase().includes("online")) return undefined;

  const workload = isoDuration(course.duration) ?? course.duration;
  const node = {
    "@type": "Course" as const,
    "@id": `${SITE_ORIGIN}/courses/${course.slug}#course`,
    name: course.headline ?? `${course.name} Course`,
    description: course.hero,
    url: `${SITE_ORIGIN}/courses/${course.slug}`,
    provider: {
      "@type": "EducationalOrganization" as const,
      "@id": ORGANIZATION_ID,
      name: "BrowseJobs",
      url: SITE_ORIGIN,
    },
    offers: {
      "@type": "Offer" as const,
      category: "Registration",
      price: String(fees.registration),
      priceCurrency: "INR",
      url: `${SITE_ORIGIN}/pay-after-placement`,
    },
    timeRequired: workload,
    hasCourseInstance: {
      "@type": "CourseInstance" as const,
      courseMode: "Online",
      courseWorkload: workload,
      inLanguage: "en",
    },
    ...(course.tools.length > 0 ? { teaches: course.tools } : {}),
  } satisfies Course;

  return node;
}

/** One Course entity, shared by the location pages and /courses/data-engineering. */
export function dataEngineeringCourseNode(): Course {
  const node = courseNode("data-engineering");
  if (!node) throw new Error("data-engineering course is missing");
  return node;
}

export function organizationNode(): EducationalOrganization {
  const node = {
    "@type": "EducationalOrganization" as const,
    "@id": ORGANIZATION_ID,
    name: "BrowseJobs",
    legalName: contact.entity,
    url: SITE_ORIGIN,
    logo: {
      "@type": "ImageObject" as const,
      url: absoluteUrl(LOGO_PATH),
      width: "512",
      height: "512",
    },
    image: absoluteUrl(LOGO_PATH),
    description: organizationDescription,
    email: contact.email,
    telephone: contact.phone,
    address: {
      "@type": "PostalAddress" as const,
      ...publishedAddress,
    },
    openingHoursSpecification: {
      "@type": "OpeningHoursSpecification" as const,
      dayOfWeek: publishedHours.dayOfWeek,
      opens: publishedHours.opens,
      closes: publishedHours.closes,
    },
    founder: { "@id": FOUNDER_ID },
    ...(officialProfileUrls.length > 0 ? { sameAs: officialProfileUrls } : {}),
  } satisfies EducationalOrganization;

  return node;
}

export function founderNode(): Person {
  return {
    "@type": "Person",
    "@id": FOUNDER_ID,
    name: founder.name,
    jobTitle: founder.jobTitle,
    worksFor: { "@id": ORGANIZATION_ID },
    ...(founderProfileUrl ? { url: founderProfileUrl } : {}),
  };
}

export function websiteNode(): WebSite {
  return {
    "@type": "WebSite",
    "@id": WEBSITE_ID,
    name: "BrowseJobs",
    url: SITE_ORIGIN,
    inLanguage: "en-GB",
    publisher: { "@id": ORGANIZATION_ID },
  };
}

/** Sitewide entity graph: organization, website, founder. Rendered from the root layout. */
export function siteJsonLd(): Graph {
  return jsonLdGraph([organizationNode(), websiteNode(), founderNode()]);
}

export function jsonLdGraph(nodes: readonly Thing[]): Graph {
  return {
    "@context": "https://schema.org",
    "@graph": nodes,
  };
}

/** Round-trip parse so a malformed graph fails a test instead of shipping. */
export function assertParsableJsonLd(value: unknown): { "@context": string; "@graph"?: { "@type"?: unknown }[]; "@type"?: unknown } {
  const parsed = JSON.parse(JSON.stringify(value)) as {
    "@context"?: string;
    "@graph"?: { "@type"?: unknown }[];
    "@type"?: unknown;
  };
  if (parsed["@context"] !== "https://schema.org") {
    throw new Error("JSON-LD is missing @context");
  }
  const nodes = parsed["@graph"] ?? [parsed];
  if (!Array.isArray(nodes) || nodes.length === 0) {
    throw new Error("JSON-LD has no nodes");
  }
  for (const node of nodes) {
    const type = node["@type"];
    const ok = typeof type === "string" || (Array.isArray(type) && type.every((item) => typeof item === "string"));
    if (!ok) throw new Error("JSON-LD node is missing @type");
  }
  if (JSON.stringify(parsed).includes("[Krish:")) {
    throw new Error("JSON-LD contains a placeholder");
  }
  return parsed as { "@context": string; "@graph"?: { "@type"?: unknown }[]; "@type"?: unknown };
}

/**
 * Absolute, self-referencing canonical. Query strings are dropped so tracking
 * variants (utm, gclid, fbclid) consolidate on the clean URL.
 */
export function canonical(path: string): string {
  if (path === "/" || path === "") return SITE_ORIGIN;
  const clean = path.startsWith("/") ? path : `/${path}`;
  return `${SITE_ORIGIN}${clean.split("?")[0]}`;
}
