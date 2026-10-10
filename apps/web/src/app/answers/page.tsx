import type { Metadata } from "next";
import Link from "next/link";
import { MoneyArticle } from "@/components/seo/MoneyArticle";
import "@/components/ap/pages/marketing.css";
import { ANSWERS_UPDATED, answerPages, answerPath } from "@/content/answers";
import { breadcrumbNode, faqNode, jsonLdGraph, moneyMetadata, webPageNode } from "@/lib/seo";

const page = {
  path: "/answers",
  title: "Straight answers",
  description:
    "Direct answers on data engineering courses in India and Bangalore, a non-IT switch, pay after placement, and how BrowseJobs interviews and shortlists.",
};

export const metadata: Metadata = moneyMetadata(page);

export default function AnswersHubPage() {
  const crumbs = [
    { name: "Home", path: "/" },
    { name: "Answers", path: "/answers" },
  ];
  const jsonLd = jsonLdGraph([
    webPageNode({ ...page, dateModified: ANSWERS_UPDATED }),
    breadcrumbNode(crumbs),
    faqNode(answerPages.map((item) => ({ q: item.title, a: item.directAnswer }))),
    {
      "@type": "ItemList",
      name: "BrowseJobs answers",
      itemListElement: answerPages.map((item, index) => ({
        "@type": "ListItem",
        position: index + 1,
        name: item.title,
        url: `https://browsejobs.ai${answerPath(item.slug)}`,
      })),
    },
  ]);

  return (
    <MoneyArticle jsonLd={jsonLd}>
      <header className="ap-hero s-white">
        <div className="ap-narrow center">
          <nav aria-label="Breadcrumb" className="ap-crumbs">
            <Link href="/">Home</Link>
            <span aria-hidden>{" › "}</span>
            <span aria-current="page">Answers</span>
          </nav>
          <p className="eyebrow">Answers</p>
          <h1 className="h-hero">{page.title}</h1>
          <p className="fine">
            Updated <time dateTime={ANSWERS_UPDATED}>6 Oct 2026</time>
          </p>
          <div className="ap-lede">
            <p>
              Each page starts with the answer. Take the free AI interview and read the score. A score of 75% or more
              counts as clear and puts you in front of HR. A course comes only if you still need one. Hiring teams
              have two pages of their own, further down.
            </p>
          </div>
          <div className="cta-row">
            <Link href="/register" className="btn btn-primary">
              Take the free AI interview
            </Link>
          </div>
        </div>
      </header>
      <section className="ap-sec ans-list">
        <div className="wrap">
          <ul className="pg-tiles is-2" data-reveal-kids="">
            {answerPages.map((item) => (
              <li key={item.slug}>
                <Link href={answerPath(item.slug)} className="pg-tile">
                  <h2 className="h-card">{item.title}</h2>
                  <p className="body">{item.directAnswer}</p>
                  <span className="more">
                    Read the answer <span className="chev" aria-hidden="true">›</span>
                  </span>
                </Link>
              </li>
            ))}
          </ul>
        </div>
      </section>
    </MoneyArticle>
  );
}
