import type { Metadata } from "next";
import Link from "next/link";
import { MoneyArticle } from "@/components/seo/MoneyArticle";
import "@/components/ap/pages/marketing.css";
import { founder, founderSameAs, pressCoverage } from "@/content/entity";
import { FOUNDER_ID, breadcrumbNode, jsonLdGraph, moneyMetadata, webPageNode } from "@/lib/seo";

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
      <header className="ap-hero s-white">
        <div className="ap-narrow center">
          <nav aria-label="Breadcrumb" className="ap-crumbs">
            <Link href="/">Home</Link>
            <span aria-hidden>{" › "}</span>
            <span aria-current="page">Dr Krish Bharggav</span>
          </nav>
          <p className="eyebrow">Founder</p>
          <h1 className="h-hero">{founder.name}</h1>
        </div>
      </header>

      <section className="ap-sec" style={{ paddingTop: 0 }}>
        <div className="ap-narrow ap-prose">
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
          <ul className="rows founder-links">
            <li>
              <a href={founderSameAs[0]} rel="noopener noreferrer">
                LinkedIn <span className="chev" aria-hidden="true">›</span>
              </a>
            </li>
            <li>
              <a href={founderSameAs[1]} rel="noopener noreferrer">
                The Offer Letter on Instagram <span className="chev" aria-hidden="true">›</span>
              </a>
            </li>
            <li>
              <a href={founderSameAs[2]} rel="noopener noreferrer">
                The Offer Letter on YouTube <span className="chev" aria-hidden="true">›</span>
              </a>
            </li>
          </ul>
        </div>
      </section>

      <section className="ap-sec" aria-labelledby="in-the-news">
        <div className="ap-narrow center">
          <p className="eyebrow">Press</p>
          <h2 id="in-the-news" className="h-section">
            Dr Krish Bharggav in the news
          </h2>
          <ul className="pg-chips is-center founder-press">
            {pressCoverage.map((article) => (
              <li key={article.url}>
                <a href={article.url} rel="noopener noreferrer">
                  {article.publication}
                </a>
              </li>
            ))}
          </ul>
        </div>
      </section>
    </MoneyArticle>
  );
}
