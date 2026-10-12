import Link from "next/link";
import {
  ContactStrip,
  Contents,
  MoneyArticle,
  MoneyFaq,
  MoneySection,
  RelatedLinks,
} from "@/components/seo/MoneyArticle";
import { RichText } from "@/components/seo/RichText";
import { ANSWERS_UPDATED, answerPath, type AnswerPage } from "@/content/answers";

const UPDATED_LABEL = "6 Oct 2026";

function formatUpdated(iso: string): string {
  if (iso === "2026-10-06") return UPDATED_LABEL;
  return iso;
}

export function AnswerView({
  page,
  jsonLd,
}: {
  page: AnswerPage;
  jsonLd: unknown;
}) {
  const crumbs = [
    { name: "Home", path: "/" },
    { name: "Answers", path: "/answers" },
    { name: page.title, path: answerPath(page.slug) },
  ];
  const primary =
    page.audience === "employer"
      ? { href: "/employers", label: "Talk to us about a role" }
      : { href: "/register", label: "Take the free AI interview" };

  return (
    <MoneyArticle jsonLd={jsonLd}>
      <header className="ap-hero s-white">
        <div className="ap-narrow center">
          <nav aria-label="Breadcrumb" className="ap-crumbs">
            {crumbs.map((crumb, index) => (
              <span key={crumb.path}>
                {index > 0 && <span aria-hidden>{" › "}</span>}
                {index < crumbs.length - 1 ? (
                  <Link href={crumb.path}>{crumb.name}</Link>
                ) : (
                  <span aria-current="page">{crumb.name}</span>
                )}
              </span>
            ))}
          </nav>
          <p className="eyebrow">{page.kicker}</p>
          <h1 className="h-hero">{page.title}</h1>
          <p className="fine">
            Updated <time dateTime={ANSWERS_UPDATED}>{formatUpdated(ANSWERS_UPDATED)}</time>
          </p>
          <div className="ap-lede">
            <p data-direct-answer>{page.directAnswer}</p>
          </div>
          <div className="cta-row">
            <Link href={primary.href} className="btn btn-primary">
              {primary.label}
            </Link>
            <Link href={page.secondary.href} className="more">
              {page.secondary.label} <span className="chev" aria-hidden="true">›</span>
            </Link>
          </div>
          {page.audience === "employer" && (
            <p className="fine">
              Looking for a job instead?{" "}
              <Link href="/register" className="ap-link">
                Take the free AI interview
              </Link>
              .
            </p>
          )}
        </div>
      </header>
      <Contents items={page.sections.map((section) => ({ href: `#${section.id}`, label: section.heading }))} />
      {page.sections.map((section) => (
        <MoneySection key={section.id} id={section.id} heading={section.heading}>
          {section.paragraphs.map((paragraph) => (
            <p key={paragraph.slice(0, 48)}>
              <RichText text={paragraph} />
            </p>
          ))}
        </MoneySection>
      ))}
      <MoneyFaq faqs={page.faqs} />
      <RelatedLinks links={page.related} />
      <ContactStrip />
    </MoneyArticle>
  );
}
