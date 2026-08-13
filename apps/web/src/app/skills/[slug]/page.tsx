import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { MarketingShell } from "@/components/landing/MarketingShell";
import { SkillDetail } from "@/components/skills/SkillDetail";
import { getSkillPage, skillPages } from "@/content/skills";
import { pageMetadata } from "@/lib/seo";

export function generateStaticParams() {
  return skillPages.map((p) => ({ slug: p.slug }));
}

export async function generateMetadata({ params }: { params: Promise<{ slug: string }> }): Promise<Metadata> {
  const page = getSkillPage((await params).slug);
  if (!page) return {};
  const title = `${page.name} — Demand, Interview Questions & Salaries in India`;
  const description = `${page.blurb} Where ${page.name} is hiring (${page.cities.join(", ")}), what interviews ask, and what the roles pay.`;
  return pageMetadata({ title, description, path: `/skills/${page.slug}` });
}

export default async function SkillPage({ params }: { params: Promise<{ slug: string }> }) {
  const page = getSkillPage((await params).slug);
  if (!page) notFound();

  const jsonLd = {
    "@context": "https://schema.org",
    "@type": "DefinedTerm",
    name: page.name,
    description: page.blurb,
    inDefinedTermSet: "https://browsejobs.ai/skills",
  };

  return (
    <>
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd) }} />
      <MarketingShell>
        <SkillDetail page={page} />
      </MarketingShell>
    </>
  );
}
