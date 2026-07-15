import type { Metadata } from "next";
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

export const metadata: Metadata = {
  metadataBase: new URL(siteUrl),
  title: {
    default: "BrowseJobs — Learn the skill. Prove it. Get placed.",
    template: "%s · BrowseJobs",
  },
  description:
    "Live, mentor-led tech bootcamps with an AI coach, real interview practice, and placement support. Pay-after-placement options. Proof, not promises.",
  keywords: [
    "tech bootcamp India",
    "data engineering course",
    "full stack developer course",
    "placement training",
    "AI interview practice",
  ],
  openGraph: {
    type: "website",
    siteName: "BrowseJobs",
    title: "BrowseJobs — Learn the skill. Prove it. Get placed.",
    description:
      "Live, mentor-led tech bootcamps with an AI coach and placement support.",
    url: siteUrl,
  },
  twitter: {
    card: "summary_large_image",
    title: "BrowseJobs — Learn the skill. Prove it. Get placed.",
    description:
      "Live, mentor-led tech bootcamps with an AI coach and placement support.",
  },
  robots: { index: true, follow: true },
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
      </body>
    </html>
  );
}
