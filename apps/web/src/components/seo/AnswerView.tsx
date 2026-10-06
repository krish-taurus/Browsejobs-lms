import Link from "next/link";
import { Kicker } from "@/components/brand/Kicker";
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
      <header className="mx-auto max-w-3xl px-5 pb-12 pt-16 md:pt-24">
        <nav aria-label="Breadcrumb" className="mono text-[11px] text-muted">
          {crumbs.map((crumb, index) => (
            <span key={crumb.path}>
              {index > 0 && <span aria-hidden>{" / "}</span>}
              {index < crumbs.length - 1 ? (
                <Link href={crumb.path} className="hover:text-ink">
                  {crumb.name}
                </Link>
              ) : (
                <span className="text-ink2">{crumb.name}</span>
              )}
            </span>
          ))}
        </nav>
        <Kicker className="mt-6">{page.kicker}</Kicker>
        <h1 className="display mt-3 text-4xl text-ink md:text-5xl">{page.title}</h1>
        <p className="mono mt-4 text-xs text-muted">
          Updated <time dateTime={ANSWERS_UPDATED}>{formatUpdated(ANSWERS_UPDATED)}</time>
        </p>
        <p data-direct-answer className="mt-6 text-lg leading-relaxed text-ink">
          {page.directAnswer}
        </p>
        <div className="mt-8 flex flex-col items-start gap-4 sm:flex-row sm:items-center">
          <Link
            href={primary.href}
            className="inline-flex items-center justify-center rounded-full bg-trust px-6 py-3 font-semibold text-white shadow-[0_6px_24px_rgba(27,109,240,0.35)] transition-colors hover:bg-deep"
          >
            {primary.label}
          </Link>
          <Link href={page.secondary.href} className="text-sm font-semibold text-ink hover:text-trust">
            {page.secondary.label}
          </Link>
        </div>
        {page.audience === "employer" && (
          <p className="mt-4 text-sm text-muted">
            Looking for a job instead?{" "}
            <Link href="/register" className="font-semibold text-trust hover:underline">
              Take the free AI interview
            </Link>
            .
          </p>
        )}
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
