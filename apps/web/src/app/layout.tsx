import type { Metadata } from "next";
import Script from "next/script";
import { BrandingProvider } from "@/lib/branding";
import "./globals.css";

/* Fonts are self-hosted (public/fonts + fonts.css) — next/font/google needs
 * network access at compile time and breaks offline builds. Preload the latin
 * files used above the fold so first paint doesn't wait on CSS discovery. */
const PRELOAD_FONTS = [
  "/fonts/sora-latin-400.woff2",
  "/fonts/inter-latin-400.woff2",
  "/fonts/plex-mono-latin-500.woff2",
];

const siteUrl = process.env.NEXT_PUBLIC_SITE_URL ?? "https://browsejobs.ai";

const TITLE = "BrowseJobs — Built from real interviews.";
const DESCRIPTION =
  "This syllabus was not written. It was reverse-engineered — from up to ~50 real & mock interviews monitored daily. Three free steps before you pay anything; placement fee only after you accept an offer.";

export const metadata: Metadata = {
  metadataBase: new URL(siteUrl),
  title: {
    default: TITLE,
    template: "%s · BrowseJobs",
  },
  description: DESCRIPTION,
  keywords: [
    "IT skilling India",
    "data engineering course",
    "devops cloud course",
    "python backend course",
    "data analytics course",
    "placement support training",
  ],
  openGraph: {
    type: "website",
    siteName: "BrowseJobs",
    title: TITLE,
    description: DESCRIPTION,
    url: siteUrl,
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
    title: TITLE,
    description: DESCRIPTION,
    images: ["/og.png"],
  },
  robots: { index: true, follow: true },
  // Google Search Console ownership proofs — each renders as a
  // google-site-verification meta tag on every page.
  verification: {
    google: [
      "lfmAeInA42ZA8RAzOxlyEz6A83lnwkQkgvnXlTOoL_E",
      "QHbOv9CSjPuuSO0pOOixFp3JFqRO-u6ndy0Q9s4MijM",
    ],
  },
};

export default function RootLayout({
  children,
}: Readonly<{ children: React.ReactNode }>) {
  return (
    <html lang="en">
      <body className="antialiased">
        {PRELOAD_FONTS.map((href) => (
          <link
            key={href}
            rel="preload"
            href={href}
            as="font"
            type="font/woff2"
            crossOrigin="anonymous"
          />
        ))}
        <BrandingProvider>{children}</BrandingProvider>

        {/* Google Analytics */}
        <Script
          src="https://www.googletagmanager.com/gtag/js?id=G-WGGL1MS701"
          strategy="afterInteractive"
        />
        <Script id="google-analytics" strategy="afterInteractive">
          {`window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
gtag('config', 'G-WGGL1MS701');`}
        </Script>

        {/* Microsoft Clarity */}
        <Script id="microsoft-clarity" strategy="afterInteractive">
          {`(function(c,l,a,r,i,t,y){
c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
})(window, document, "clarity", "script", "xiomzak7ll");`}
        </Script>

        {/* Organization schema */}
        <Script
          id="organization-schema"
          type="application/ld+json"
          dangerouslySetInnerHTML={{
            __html: JSON.stringify({
              "@context": "https://schema.org",
              "@type": "EducationalOrganization",
              "@id": "https://browsejobs.ai/#organization",
              name: "Browsejobs",
              alternateName: "BrowseJobs",
              url: "https://browsejobs.ai",
              logo: "https://browsejobs.ai/logo.png",
              image: "https://browsejobs.ai/logo.png",
              description:
                "Browsejobs is a career development platform focused on Data Engineering. We empower aspiring data engineers with industry-aligned training, real-world projects, interview preparation, resume building, LinkedIn optimization, and placement support to help them build successful careers in data engineering.",
              knowsAbout: [
                "Data Engineering",
                "Python",
                "SQL",
                "PySpark",
                "Apache Spark",
                "ETL",
                "Data Warehousing",
                "Azure Data Factory",
                "Azure Databricks",
                "Azure Data Lake",
                "AWS Glue",
                "Amazon S3",
                "Amazon Redshift",
                "Apache Airflow",
                "Snowflake",
                "Interview Preparation",
                "Resume Building",
                "Career Development",
              ],
              sameAs: [
                "https://www.linkedin.com/company/browsejobsconsultants/posts/?feedView=all",
                "https://www.instagram.com/browsejobs_6666/",
                "https://www.facebook.com/people/BrowseJobs-Transform-Your-Career/61573831853161/",
                "https://www.youtube.com/@Browsejobs",
              ],
            }),
          }}
        />
      </body>
    </html>
  );
}
