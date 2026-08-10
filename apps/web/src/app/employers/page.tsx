import type { Metadata } from "next";
import { EmployersView } from "@/components/employers/EmployersView";
import { EMPLOYER_FAQ } from "@/content/employers";
import { contact } from "@/content/landing";

export const metadata: Metadata = {
  title: "BrowseJobs for Employers — AI Hiring Pipeline, Free for 6 Months",
  description:
    "Load a JD and the AI structures it, ranks candidates, places the screening call, runs L1/L2 or custom rounds, tracks everything in an ATS, runs high-level BGV, and hands HR a written brief on every finalist. Free for the first six months.",
  alternates: { canonical: "https://browsejobs.ai/employers" },
  openGraph: {
    title: "BrowseJobs for Employers — Interview the shortlist, not the inbox",
    description:
      "An AI hiring pipeline that screens, interviews and background-checks candidates before your panel spends an hour. Free for six months.",
    url: "https://browsejobs.ai/employers",
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
