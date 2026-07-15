/**
 * JSON-LD structured data for the landing page: Organization + a Course entry
 * so the funnel is eligible for rich results (PRD §6.23 SEO requirement).
 */
export function JsonLd() {
  const data = {
    "@context": "https://schema.org",
    "@graph": [
      {
        "@type": "Organization",
        name: "BrowseJobs",
        legalName: "iBrowseJobs Technologies Pvt Ltd",
        url: "https://browsejobs.ai",
        description:
          "Live, mentor-led tech bootcamps with an AI coach and placement support.",
      },
      {
        "@type": "Course",
        name: "BrowseJobs Career Tracks",
        description:
          "Live mentor-led bootcamps in Data Engineering, Full-Stack, Data Science and more, with a personal AI coach and placement support.",
        provider: {
          "@type": "Organization",
          name: "BrowseJobs",
          sameAs: "https://browsejobs.ai",
        },
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
