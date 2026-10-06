import type { Metadata } from "next";
import Link from "next/link";
import { Kicker } from "@/components/brand/Kicker";
import { MoneyArticle } from "@/components/seo/MoneyArticle";
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
      <header className="mx-auto max-w-3xl px-5 pb-8 pt-16 md:pt-24">
        <nav aria-label="Breadcrumb" className="mono text-[11px] text-muted">
          <Link href="/" className="hover:text-ink">
            Home
          </Link>
          <span aria-hidden>{" / "}</span>
          <span className="text-ink2">Answers</span>
        </nav>
        <Kicker className="mt-6">Answers</Kicker>
        <h1 className="display mt-3 text-4xl text-ink md:text-5xl">{page.title}</h1>
        <p className="mono mt-4 text-xs text-muted">
          Updated <time dateTime={ANSWERS_UPDATED}>6 Oct 2026</time>
        </p>
        <p className="mt-6 text-lg leading-relaxed text-ink2">
          Each page starts with the answer. Take the free AI interview and read the score. Employers can see you
          when you finish. A course comes only if you still need one. Hiring teams have two pages of their own,
          further down.
        </p>
        <Link
          href="/register"
          className="mt-8 inline-flex items-center justify-center rounded-full bg-trust px-6 py-3 font-semibold text-white shadow-[0_6px_24px_rgba(27,109,240,0.35)] transition-colors hover:bg-deep"
        >
          Take the free AI interview
        </Link>
      </header>
      <ul className="mx-auto grid max-w-3xl gap-4 px-5 pb-16">
        {answerPages.map((item) => (
          <li key={item.slug}>
            <Link
              href={answerPath(item.slug)}
              className="block rounded-[14px] border border-line bg-white px-5 py-5 transition-colors hover:border-trust/40"
            >
              <h2 className="text-lg font-semibold text-ink">{item.title}</h2>
              <p className="mt-2 text-[15px] leading-relaxed text-ink2">{item.directAnswer}</p>
            </Link>
          </li>
        ))}
      </ul>
    </MoneyArticle>
  );
}
