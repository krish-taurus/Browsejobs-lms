import type { Metadata } from "next";
import localFont from "next/font/local";
import { Analytics } from "@/components/analytics/Analytics";
import { MotionProvider } from "@/components/argus/MotionProvider";
import { SceneHost } from "@/components/three/SceneHost";
import { SiteJsonLd } from "@/components/seo/SiteJsonLd";
import "./globals.css";

// Latin woff2 files live in the repo (OFL alongside each family) so the
// production build never calls Google Fonts.

const sora = localFont({
  src: [
    { path: "../fonts/sora/sora-latin-400-normal.woff2", weight: "400", style: "normal" },
    { path: "../fonts/sora/sora-latin-600-normal.woff2", weight: "600", style: "normal" },
    { path: "../fonts/sora/sora-latin-800-normal.woff2", weight: "800", style: "normal" },
  ],
  variable: "--font-sora",
  display: "swap",
  preload: false,
});

// Employer portal only (scoped via .bj-employer-dashboard in globals.css) —
// rounded, friendly faces: Poppins for headings and big numbers, Nunito for
// body text, chat and controls. Kept separate from --font-sora (used
// everywhere else as `.display`/`font-display`) so the rest of the site is
// untouched.
const poppins = localFont({
  src: [
    { path: "../fonts/poppins/poppins-latin-500-normal.woff2", weight: "500", style: "normal" },
    { path: "../fonts/poppins/poppins-latin-500-italic.woff2", weight: "500", style: "italic" },
    { path: "../fonts/poppins/poppins-latin-600-normal.woff2", weight: "600", style: "normal" },
    { path: "../fonts/poppins/poppins-latin-600-italic.woff2", weight: "600", style: "italic" },
    { path: "../fonts/poppins/poppins-latin-700-normal.woff2", weight: "700", style: "normal" },
    { path: "../fonts/poppins/poppins-latin-700-italic.woff2", weight: "700", style: "italic" },
  ],
  variable: "--font-poppins",
  display: "swap",
  preload: false,
});

const nunito = localFont({
  src: [
    { path: "../fonts/nunito/nunito-latin-400-normal.woff2", weight: "400", style: "normal" },
    { path: "../fonts/nunito/nunito-latin-500-normal.woff2", weight: "500", style: "normal" },
    { path: "../fonts/nunito/nunito-latin-600-normal.woff2", weight: "600", style: "normal" },
    { path: "../fonts/nunito/nunito-latin-700-normal.woff2", weight: "700", style: "normal" },
    { path: "../fonts/nunito/nunito-latin-800-normal.woff2", weight: "800", style: "normal" },
  ],
  variable: "--font-nunito",
  display: "swap",
  preload: false,
});

const inter = localFont({
  src: [
    { path: "../fonts/inter/inter-latin-400-normal.woff2", weight: "400", style: "normal" },
    { path: "../fonts/inter/inter-latin-500-normal.woff2", weight: "500", style: "normal" },
    { path: "../fonts/inter/inter-latin-600-normal.woff2", weight: "600", style: "normal" },
    { path: "../fonts/inter/inter-latin-700-normal.woff2", weight: "700", style: "normal" },
  ],
  variable: "--font-inter",
  display: "swap",
});

const michroma = localFont({
  src: [{ path: "../fonts/michroma/michroma-latin-400-normal.woff2", weight: "400", style: "normal" }],
  variable: "--font-wordmark",
  display: "swap",
  preload: false,
});

const plexMono = localFont({
  src: [
    { path: "../fonts/ibm-plex-mono/ibm-plex-mono-latin-400-normal.woff2", weight: "400", style: "normal" },
    { path: "../fonts/ibm-plex-mono/ibm-plex-mono-latin-500-normal.woff2", weight: "500", style: "normal" },
    { path: "../fonts/ibm-plex-mono/ibm-plex-mono-latin-600-normal.woff2", weight: "600", style: "normal" },
  ],
  variable: "--font-plex-mono",
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
