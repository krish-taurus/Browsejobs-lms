import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { MarketingShell } from "@/components/landing/MarketingShell";
import { SalaryDetail } from "@/components/salaries/SalaryDetail";
import { getSalaryPage, salaryPages } from "@/content/salaries";
import { DISCLAIMER } from "@/content/landing";

/**
 * Programmatic salary SEO pages (/salaries/data-engineer-bengaluru):
 * statically rendered from the curated dataset with Occupation + FAQ
 * JSON-LD; the interactive body carries the motion system.
 */

export function generateStaticParams() {
  return salaryPages.map((p) => ({ slug: p.slug }));
}

export async function generateMetadata({ params }: { params: Promise<{ slug: string }> }): Promise<Metadata> {
  const page = getSalaryPage((await params).slug);
  if (!page) return {};
  const band = page.bands[page.bands.length - 1];
  const title = `${page.role} Salary in ${page.city} — ₹${band.p25}–${band.p75} LPA`;
  const description = `${page.role} pay in ${page.city}: entry offers around ₹${band.p25} LPA, median ₹${band.p50} LPA, strong offers ₹${band.p75} LPA. ${DISCLAIMER}`;
  return { title, description, openGraph: { title, description } };
}

export default async function SalaryPage({ params }: { params: Promise<{ slug: string }> }) {
  const page = getSalaryPage((await params).slug);
  if (!page) notFound();
  const band = page.bands[page.bands.length - 1];

  const jsonLd = {
    "@context": "https://schema.org",
    "@type": "Occupation",
    name: page.role,
    occupationLocation: { "@type": "City", name: page.city },
    estimatedSalary: {
      "@type": "MonetaryAmountDistribution",
      name: "base",
      currency: "INR",
      duration: "P1Y",
      percentile25: band.p25 * 100000,
      median: band.p50 * 100000,
      percentile75: band.p75 * 100000,
    },
    description: `${page.role} salary benchmarks in ${page.city}. ${DISCLAIMER}`,
    skills: page.skills.join(", "),
  };

  return (
    <>
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd) }} />
      <MarketingShell>
        <SalaryDetail page={page} />
      </MarketingShell>
    </>
  );
}
