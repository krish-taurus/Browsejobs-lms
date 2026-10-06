import { EmployersPage } from "@/components/employers/EmployersPage";
import { MarketingShell } from "@/components/landing/MarketingShell";
import { EMPLOYER_FAQ, EMPLOYER_META } from "@/content/employer-landing";
import { contact } from "@/content/landing";
import { breadcrumbNode, faqNode, jsonLdGraph, webPageNode } from "@/lib/seo";

const employerNav = [
  { href: "#bots", label: "The bots" },
  { href: "#journey", label: "Journey" },
  { href: "#report", label: "Sample report" },
  { href: "#faq", label: "FAQ" },
  { href: "/", label: "For candidates" },
] as const;

export default function EmployersRoute() {
  const jsonLd = jsonLdGraph([
    webPageNode(EMPLOYER_META),
    breadcrumbNode([
      { name: "Home", path: "/" },
      { name: "Employers", path: EMPLOYER_META.path },
    ]),
    {
      "@type": "Service",
      name: "BrowseJobs hiring",
      serviceType: "Hiring on WhatsApp bots",
      url: "https://browsejobs.ai/employers",
      provider: {
        "@type": "Organization",
        name: "BrowseJobs",
        email: contact.email,
        telephone: contact.phone,
        url: "https://browsejobs.ai",
      },
      areaServed: "IN",
      description: EMPLOYER_META.description,
    },
    faqNode(EMPLOYER_FAQ),
  ]);

  return (
    <>
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd) }} />
      <MarketingShell links={employerNav} ctaLabel="Hire with BrowseJobs" ctaHref="#work-with-us">
        <EmployersPage />
      </MarketingShell>
    </>
  );
}
