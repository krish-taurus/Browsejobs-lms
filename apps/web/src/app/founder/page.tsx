import type { Metadata } from "next";
import Link from "next/link";
import { Kicker } from "@/components/brand/Kicker";
import { MoneyArticle } from "@/components/seo/MoneyArticle";
import { founder, founderSameAs, pressCoverage } from "@/content/entity";
import { FOUNDER_ID, breadcrumbNode, jsonLdGraph, moneyMetadata, webPageNode } from "@/lib/seo";

/** Bio last changed 2026-10-06. Bump FOUNDER_UPDATED in content/entity.ts when this page or the press list changes. */

const page = {
  path: "/founder",
  title: "Dr Krish Bharggav",
  description:
    "Dr Krish Bharggav is the founder of BrowseJobs. A short bio from published press profiles, with links to those articles.",
};

export const metadata: Metadata = moneyMetadata(page);

export default function FounderPage() {
  const crumbs = [
    { name: "Home", path: "/" },
    { name: "Dr Krish Bharggav", path: "/founder" },
  ];
  const jsonLd = jsonLdGraph([
    {
      ...webPageNode(page),
      mainEntity: { "@id": FOUNDER_ID },
    },
    breadcrumbNode(crumbs),
  ]);

  return (
    <MoneyArticle jsonLd={jsonLd}>
      <header className="mx-auto max-w-3xl px-5 pb-4 pt-16 md:pt-24">
        <nav aria-label="Breadcrumb" className="mono text-[11px] text-muted">
          <Link href="/" className="hover:text-ink">
            Home
          </Link>
          <span aria-hidden>{" / "}</span>
          <span className="text-ink2">Dr Krish Bharggav</span>
        </nav>
        <Kicker className="mt-6">Founder</Kicker>
        <h1 className="display mt-3 text-4xl text-ink md:text-5xl">{founder.name}</h1>
      </header>

      <div className="mx-auto max-w-3xl space-y-5 px-5 pb-10 text-[17px] leading-relaxed text-ink2">
        <p>
          Dr Krish Bharggav is the founder of BrowseJobs. Published profiles describe a career that started in
          banking in the United Kingdom, including work as a Bank Manager at Lloyds Bank, London Bridge. Later work
          has been in technology, consulting, data science, and entrepreneurship.
        </p>
        <p>
          Those profiles report a PhD in Data Science, a Master’s in Entrepreneurship from London Business School,
          and a Master’s in Blockchain from the University of Oxford.
        </p>
        <p>
          They describe BrowseJobs as a technology and talent-development company, established in India, working on
          technical training, interview preparation, and career support. He also hosts The Offer Letter, a podcast
          on careers, hiring, leadership, and entrepreneurship. Bhaskar Digital reports that he has spoken at TEDx
          and at industry events on technology, entrepreneurship, education, and careers.
        </p>
        <p>Nobody can guarantee employment. The market decides.</p>
        <ul className="space-y-2 text-base">
          <li>
            <a href={founderSameAs[0]} className="font-semibold text-trust underline decoration-trust/30 underline-offset-4" rel="noopener noreferrer">
              LinkedIn
            </a>
          </li>
          <li>
            <a href={founderSameAs[1]} className="font-semibold text-trust underline decoration-trust/30 underline-offset-4" rel="noopener noreferrer">
              The Offer Letter on Instagram
            </a>
          </li>
          <li>
            <a href={founderSameAs[2]} className="font-semibold text-trust underline decoration-trust/30 underline-offset-4" rel="noopener noreferrer">
              The Offer Letter on YouTube
            </a>
          </li>
        </ul>
      </div>

      <section className="mx-auto max-w-3xl px-5 pb-20" aria-labelledby="in-the-news">
        <h2 id="in-the-news" className="text-xl font-semibold text-ink">
          Dr Krish Bharggav in the news
        </h2>
        <ul className="mt-4 flex flex-wrap gap-2">
          {pressCoverage.map((article) => (
            <li key={article.url}>
              <a
                href={article.url}
                className="inline-flex rounded-full border border-line bg-white px-4 py-2 text-sm font-medium text-ink2 hover:border-trust/40 hover:text-ink"
                rel="noopener noreferrer"
              >
                {article.publication}
              </a>
            </li>
          ))}
        </ul>
      </section>
    </MoneyArticle>
  );
}
