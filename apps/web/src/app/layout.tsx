import type { Metadata } from "next";
import Script from "next/script";
import { Sora, Inter, IBM_Plex_Mono } from "next/font/google";
import "./globals.css";

const sora = Sora({
  variable: "--font-sora",
  subsets: ["latin"],
  weight: ["400", "600", "800"],
  display: "swap",
});

const inter = Inter({
  variable: "--font-inter",
  subsets: ["latin"],
  weight: ["400", "500", "600", "700"],
  display: "swap",
});

const plexMono = IBM_Plex_Mono({
  variable: "--font-plex-mono",
  subsets: ["latin"],
  weight: ["400", "500", "600"],
  display: "swap",
});

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
  },
  twitter: {
    card: "summary_large_image",
    title: TITLE,
    description: DESCRIPTION,
  },
  robots: { index: true, follow: true },
  verification: {
    google: "lfmAeInA42ZA8RAzOxlyEz6A83lnwkQkgvnXlTOoL_E",
  },
};

export default function RootLayout({
  children,
}: Readonly<{ children: React.ReactNode }>) {
  return (
    <html lang="en">
      <body
        className={`${sora.variable} ${inter.variable} ${plexMono.variable} antialiased`}
      >
        {children}

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
