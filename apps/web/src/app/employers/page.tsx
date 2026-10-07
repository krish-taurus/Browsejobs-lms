import { EmployerProduct } from "@/components/apple/EmployerProduct";
import { AppleShell } from "@/components/apple/AppleShell";
import { EMPLOYER_FAQ, EMPLOYER_META } from "@/content/employer-landing";
import { contact } from "@/content/landing";
import { breadcrumbNode, faqNode, jsonLdGraph, webPageNode } from "@/lib/seo";

const SHORT_FAQ = EMPLOYER_FAQ.filter((_, index) => index === 0 || index === 1 || index === 5);

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
      serviceType: "Hiring with the BrowseJobs AI Recruiter",
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
    faqNode(SHORT_FAQ),
  ]);

  return (
    <>
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd) }} />
      <AppleShell dark>
        <EmployerProduct />
      </AppleShell>
    </>
  );
}
