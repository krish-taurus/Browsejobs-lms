import type { Metadata } from "next";
import type {
  BreadcrumbList,
  Course,
  EducationalOrganization,
  FAQPage,
  Graph,
  NewsArticle,
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
  founderSameAs,
  organizationDescription,
  organizationSameAs,
  pressCoverage,
  publishedAddress,
  publishedHours,
} from "@/content/entity";
import { contact, fees } from "@/content/landing";
import { OFFICE_PHONE } from "@/content/phones";

export const SITE_ORIGIN = "https://browsejobs.ai";

export const ORGANIZATION_ID = `${SITE_ORIGIN}/#organization`;
export const WEBSITE_ID = `${SITE_ORIGIN}/#website`;
export const FOUNDER_ID = `${SITE_ORIGIN}/#founder`;
export const BANGALORE_PLACE_ID = `${SITE_ORIGIN}/#bangalore`;

/**
 * Share image for pages that set their own `openGraph`. Next.js replaces the
 * layout's openGraph object wholesale, so a page that omits `images` shares
 * with no picture at all.
 */
export const OG_IMAGES = [{ url: "/og.png", width: 1200, height: 630, alt: "BrowseJobs" }];

export function absoluteUrl(path: string): string {
  if (path === "/") return `${SITE_ORIGIN}/`;
  return `${SITE_ORIGIN}${path.startsWith("/") ? path : `/${path}`}`;
}

/** Self-referencing canonical. Query strings are never part of the URL. */
export function moneyMetadata(page: {
  path: string;
  title: string;
  description: string;
  /** When set, this URL is the canonical. The page path can still be indexed as an alias. */
  canonicalPath?: string;
  /** Keep `title` exactly, including when it must not gain the layout's "· BrowseJobs" suffix. */
  absoluteTitle?: boolean;
}): Metadata {
  const url = absoluteUrl(page.canonicalPath ?? page.path);
  const title = page.absoluteTitle || page.title.includes("· BrowseJobs") ? { absolute: page.title } : page.title;
  return {
    title,
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
  const online = {
    "@type": "CourseInstance" as const,
    courseMode: "Online",
    courseWorkload: workload,
    inLanguage: "en",
  };
  const onsite = {
    "@type": "CourseInstance" as const,
    courseMode: "Onsite",
    courseWorkload: workload,
    inLanguage: "en",
    location: { "@id": BANGALORE_PLACE_ID },
  };
  const classroom = course.slug === "data-engineering" || course.slug === "data-analytics";
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
      category: "Paid",
      price: String(fees.registration),
      priceCurrency: "INR",
      url: `${SITE_ORIGIN}/courses/${course.slug}`,
    },
    timeRequired: workload,
    hasCourseInstance: classroom ? [onsite, online] : online,
    ...(course.tools.length > 0 ? { teaches: course.tools } : {}),
  } satisfies Course;

  return node;
}

/** Course node for a programme that is not in the live LMS catalogue (Agentic AI). */
export function programmeCourseNode(input: {
  idPath: string;
  name: string;
  description: string;
  urlPath: string;
  teaches: readonly string[];
}): Course {
  const workload = "P6M";
  return {
    "@type": "Course",
    "@id": `${SITE_ORIGIN}${input.idPath}#course`,
    name: input.name,
    description: input.description,
    url: `${SITE_ORIGIN}${input.urlPath}`,
    inLanguage: "en",
    provider: { "@id": ORGANIZATION_ID },
    timeRequired: workload,
    offers: {
      "@type": "Offer",
      price: String(fees.registration),
      priceCurrency: "INR",
      category: "Paid",
      url: `${SITE_ORIGIN}${input.urlPath}`,
    },
    teaches: [...input.teaches],
    hasCourseInstance: [
      {
        "@type": "CourseInstance",
        courseMode: "Onsite",
        courseWorkload: workload,
        location: { "@id": BANGALORE_PLACE_ID },
      },
      {
        "@type": "CourseInstance",
        courseMode: "Online",
        courseWorkload: workload,
      },
    ],
  };
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
    telephone: OFFICE_PHONE.tel,
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
    sameAs: [...organizationSameAs],
  } satisfies EducationalOrganization;

  return node;
}

export function founderNode(): Person {
  const subjectOf: NewsArticle[] = pressCoverage.map((article) => ({
    "@type": "NewsArticle",
    headline: article.headline,
    url: article.url,
    ...(article.datePublished ? { datePublished: article.datePublished } : {}),
    publisher: {
      "@type": "Organization",
      name: article.publication,
    },
  }));

  return {
    "@type": "Person",
    "@id": FOUNDER_ID,
    name: founder.name,
    jobTitle: founder.jobTitle,
    url: absoluteUrl(founderProfileUrl),
    worksFor: { "@id": ORGANIZATION_ID },
    sameAs: [...founderSameAs],
    subjectOf,
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

/**
 * Whitefield centre. Hours are the hours already published on the site.
 * Geo coordinates stay off until they are confirmed.
 */
export function bangalorePlaceNode(): EducationalOrganization {
  return {
    "@type": ["EducationalOrganization", "LocalBusiness"],
    "@id": BANGALORE_PLACE_ID,
    name: "BrowseJobs",
    url: SITE_ORIGIN,
    telephone: OFFICE_PHONE.tel,
    address: {
      "@type": "PostalAddress",
      ...publishedAddress,
    },
    openingHoursSpecification: {
      "@type": "OpeningHoursSpecification",
      dayOfWeek: publishedHours.dayOfWeek,
      opens: publishedHours.opens,
      closes: publishedHours.closes,
    },
    founder: {
      "@type": "Person",
      name: founder.name,
      url: absoluteUrl(founderProfileUrl),
    },
    parentOrganization: { "@id": ORGANIZATION_ID },
  } as unknown as EducationalOrganization;
}

/** Sitewide entity graph: organization, Whitefield centre, website, founder. */
export function siteJsonLd(): Graph {
  return jsonLdGraph([organizationNode(), bangalorePlaceNode(), websiteNode(), founderNode()]);
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
