import Link from "next/link";
import type { ReactNode } from "react";
import { BookCta } from "@/components/landing/BookCta";
import { MarketingShell } from "@/components/landing/MarketingShell";
import { contact } from "@/content/landing";
import type { FaqItem } from "@/lib/seo";

/*
 * Building blocks for the SEO "money" pages, in the Apple-direction design
 * (components/ap). Same props as before, so every page using them changes
 * look without touching its content. Styles: .ap-* rules in components/ap/ap.css.
 */

export function TextLink({ href, children }: { href: string; children: ReactNode }) {
  return (
    <Link href={href} className="ap-link">
      {children}
    </Link>
  );
}

export function MoneyArticle({ jsonLd, children }: { jsonLd: unknown; children: ReactNode }) {
  return (
    <MarketingShell>
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd) }} />
      <article className="ap-article">{children}</article>
    </MarketingShell>
  );
}

export function MoneyHero({
  kicker,
  title,
  lede,
  crumbs,
  primary,
  secondary,
}: {
  kicker: string;
  kickerTone?: "trust" | "verify";
  title: string;
  lede: ReactNode;
  crumbs: readonly { name: string; path: string }[];
  primary: { kind: "masterclass"; label?: string } | { kind: "link"; href: string; label: string };
  secondary?: { href: string; label: string };
}) {
  return (
    <header className="ap-hero s-white">
      <div className="ap-narrow center">
        <nav aria-label="Breadcrumb" className="ap-crumbs">
          {crumbs.map((crumb, index) => (
            <span key={crumb.path}>
              {index > 0 && <span aria-hidden>{" › "}</span>}
              {index < crumbs.length - 1 ? <Link href={crumb.path}>{crumb.name}</Link> : <span aria-current="page">{crumb.name}</span>}
            </span>
          ))}
        </nav>
        <p className="eyebrow">{kicker}</p>
        <h1 className="h-hero">{title}</h1>
        <div className="ap-lede">{lede}</div>
        <div className="cta-row">
          {primary.kind === "masterclass" ? (
            <BookCta>{primary.label ?? "Book Free Masterclass"}</BookCta>
          ) : (
            <Link href={primary.href} className="btn btn-primary">
              {primary.label}
            </Link>
          )}
          {secondary && (
            <Link href={secondary.href} className="more">
              {secondary.label} <span className="chev" aria-hidden="true">›</span>
            </Link>
          )}
        </div>
      </div>
    </header>
  );
}

export function MoneySection({ id, kicker, heading, children }: { id: string; kicker?: string; heading: string; children: ReactNode }) {
  return (
    <section id={id} className="ap-sec">
      <div className="ap-narrow">
        {kicker && <p className="eyebrow">{kicker}</p>}
        <h2 className="h-section">{heading}</h2>
        <div className="ap-prose">{children}</div>
      </div>
    </section>
  );
}

export function MoneyFaq({ faqs, intro }: { faqs: readonly FaqItem[]; intro?: ReactNode }) {
  return (
    <section id="faq" className="ap-sec">
      <div className="ap-narrow">
        <p className="eyebrow">Questions</p>
        <h2 className="h-section">Asked before you book.</h2>
        {intro && <div className="ap-prose">{intro}</div>}
        <div className="faq" style={{ marginInline: 0 }}>
          {faqs.map((item) => (
            <details key={item.q}>
              <summary>
                <h3 className="q">{item.q}</h3>
              </summary>
              <p className="answer">{item.a}</p>
            </details>
          ))}
        </div>
      </div>
    </section>
  );
}

export function RelatedLinks({ links }: { links: readonly { href: string; label: string; note: string }[] }) {
  return (
    <section id="related" className="ap-sec">
      <div className="ap-narrow">
        <p className="eyebrow">Keep reading</p>
        <h2 className="h-section">Related pages.</h2>
        <ul className="rows" style={{ marginTop: 28 }}>
          {links.map((link) => (
            <li key={link.href}>
              <Link href={link.href}>
                <span>
                  {link.label}
                  <small className="ap-row-note">{link.note}</small>
                </span>
                <span className="chev" aria-hidden="true">
                  ›
                </span>
              </Link>
            </li>
          ))}
        </ul>
      </div>
    </section>
  );
}

export function ContactStrip() {
  return (
    <section className="ap-sec">
      <div className="ap-narrow center">
        <p className="eyebrow">Talk to us</p>
        <h2 className="h-section">Whitefield, Bengaluru.</h2>
        <p className="lead">
          {contact.entity}. {contact.address}. {contact.hours}.
        </p>
        <p className="ap-contact">
          {contact.phone} · {contact.email}
        </p>
        <p className="fine">Every promise in writing · Every call recorded &amp; AI-monitored.</p>
      </div>
    </section>
  );
}

export function Contents({ items }: { items: readonly { href: string; label: string }[] }) {
  return (
    <nav aria-label="On this page" className="ap-contents">
      <div className="ap-narrow">
        <p className="eyebrow-sm">On this page</p>
        <ol>
          {items.map((item, index) => (
            <li key={item.href}>
              <a href={item.href}>
                <span className="n">{String(index + 1).padStart(2, "0")}</span>
                {item.label}
              </a>
            </li>
          ))}
        </ol>
      </div>
    </nav>
  );
}
