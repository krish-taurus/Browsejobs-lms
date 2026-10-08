import type { Metadata } from "next";
import { Sora, Inter, IBM_Plex_Mono, Poppins, Nunito, Michroma } from "next/font/google";
import { Analytics } from "@/components/analytics/Analytics";
import { MotionProvider } from "@/components/argus/MotionProvider";
import { SceneHost } from "@/components/three/SceneHost";
import { SiteJsonLd } from "@/components/seo/SiteJsonLd";
import "./globals.css";

const sora = Sora({
  variable: "--font-sora",
  subsets: ["latin"],
  weight: ["400", "600", "800"],
  display: "swap",
  preload: false,
});

// Employer portal only (scoped via .bj-employer-dashboard in globals.css) —
// rounded, friendly faces: Poppins for headings and big numbers, Nunito for
// body text, chat and controls. Kept separate from --font-sora (used
// everywhere else as `.display`/`font-display`) so the rest of the site is
// untouched.
const poppins = Poppins({
  variable: "--font-poppins",
  subsets: ["latin"],
  weight: ["500", "600", "700"],
  style: ["normal", "italic"],
  display: "swap",
  preload: false,
});

const nunito = Nunito({
  variable: "--font-nunito",
  subsets: ["latin"],
  weight: ["400", "500", "600", "700", "800"],
  display: "swap",
  preload: false,
});

const inter = Inter({
  variable: "--font-inter",
  subsets: ["latin"],
  weight: ["400", "500", "600", "700"],
  display: "swap",
});

const michroma = Michroma({
  variable: "--font-wordmark",
  subsets: ["latin"],
  weight: "400",
  display: "swap",
  preload: false,
});

const plexMono = IBM_Plex_Mono({
  variable: "--font-plex-mono",
  subsets: ["latin"],
  weight: ["400", "500", "600"],
  display: "swap",
  preload: false,
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
  verification: {
    google: "QHbOv9CSjPuuSO0pOOixFp3JFqRO-u6ndy0Q9s4MijM",
  },
};

export default function RootLayout({
  children,
}: Readonly<{ children: React.ReactNode }>) {
  return (
    <html lang="en">
      <body
        className={`${sora.variable} ${inter.variable} ${plexMono.variable} ${poppins.variable} ${nunito.variable} ${michroma.variable} antialiased`}
      >
        <SiteJsonLd />
        <SceneHost />
        <MotionProvider>{children}</MotionProvider>
        <Analytics />
      </body>
    </html>
  );
}
