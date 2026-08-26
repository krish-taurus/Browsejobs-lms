import { contact, courses, faqs } from "@/content/landing";

/**
 * JSON-LD (spec §11): EducationalOrganization + the live Courses + FAQPage.
 */
export function JsonLd() {
  const data = {
    "@context": "https://schema.org",
    "@graph": [
      {
        "@type": "EducationalOrganization",
        name: "BrowseJobs",
        legalName: contact.entity,
        url: "https://browsejobs.ai",
        telephone: contact.phone,
        email: contact.email,
        address: {
          "@type": "PostalAddress",
          addressLocality: "Bengaluru",
          addressRegion: "Karnataka",
          postalCode: "560066",
          addressCountry: "IN",
        },
        description:
          "AI-driven IT skilling & placement platform. The syllabus is reverse-engineered from real interviews.",
      },
      ...courses
        .filter((c) => c.live)
        .map((c) => ({
          "@type": "Course",
          name: c.name,
          description: c.tagline,
          url: `https://browsejobs.ai/${c.slug}`,
          provider: { "@type": "EducationalOrganization", name: "BrowseJobs" },
          offers: {
            "@type": "Offer",
            category: "Enrollment",
            price: "30000",
            priceCurrency: "INR",
          },
        })),
      {
        "@type": "FAQPage",
        mainEntity: faqs.map((f) => ({
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
      dangerouslySetInnerHTML={{ __html: JSON.stringify(data) }}
    />
  );
}
