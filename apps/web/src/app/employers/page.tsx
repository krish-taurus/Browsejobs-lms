import type { Metadata } from "next";
import { EmployersView } from "@/components/employers/EmployersView";
import { EMPLOYER_FAQ } from "@/content/employers";
import { contact } from "@/content/landing";
import { canonical } from "@/lib/seo";

const TITLE = "AI Interview Platform for Hiring Teams";
const DESCRIPTION =
  "AI interviews and a hiring pipeline for employers. Load a job description, rank candidates, run screening and interview rounds, and hand HR a written brief on every finalist. Free for the first six months.";

export const metadata: Metadata = {
  title: TITLE,
  description: DESCRIPTION,
  alternates: { canonical: canonical("/employers") },
  openGraph: {
    title: `${TITLE} · BrowseJobs`,
    description: DESCRIPTION,
    url: canonical("/employers"),
    type: "website",
  },
};

/** FAQPage schema — the employer questions are the page's richest SEO surface. */
function EmployersJsonLd() {
  const graph = {
    "@context": "https://schema.org",
    "@graph": [
      {
        "@type": "Service",
        name: "BrowseJobs for Employers",
        serviceType: "AI-assisted recruitment pipeline",
        provider: {
          "@type": "Organization",
          name: "BrowseJobs",
          email: contact.email,
          telephone: contact.phone,
          url: "https://browsejobs.ai",
        },
        areaServed: "IN",
        description:
          "AI hiring pipeline: JD structuring, candidate ranking, AI screening calls, L1/L2 and custom interview rounds, ATS tracking, high-level background verification and a written candidate brief for HR.",
      },
      {
        "@type": "FAQPage",
        mainEntity: EMPLOYER_FAQ.map((f) => ({
          "@type": "Question",
          name: f.q,
          acceptedAnswer: { "@type": "Answer", text: f.a },
        })),
      },
    ],
  };

  return (
    <script
      type="application/ld+json"
      dangerouslySetInnerHTML={{ __html: JSON.stringify(graph) }}
    />
  );
}

export default function EmployersPage() {
  return (
    <>
      <EmployersJsonLd />
      <EmployersView />
    </>
  );
}
