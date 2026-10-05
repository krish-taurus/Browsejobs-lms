import Link from "next/link";
import type { ReactNode } from "react";
import { Kicker } from "@/components/brand/Kicker";
import { BookCta } from "@/components/landing/BookCta";
import { MarketingShell } from "@/components/landing/MarketingShell";
import { contact } from "@/content/landing";
import type { FaqItem } from "@/lib/seo";

const linkClass =
  "font-semibold text-trust underline decoration-trust/30 underline-offset-4 hover:decoration-trust";

export function TextLink({ href, children }: { href: string; children: ReactNode }) {
  return (
    <Link href={href} className={linkClass}>
      {children}
    </Link>
  );
}

export function MoneyArticle({
  jsonLd,
  children,
}: {
  jsonLd: unknown;
  children: ReactNode;
}) {
  return (
    <MarketingShell>
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd) }} />
      <article className="bg-paper">{children}</article>
    </MarketingShell>
  );
}

export function MoneyHero({
  kicker,
  kickerTone = "trust",
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
      <Kicker tone={kickerTone} className="mt-6">
        {kicker}
      </Kicker>
      <h1 className="display mt-3 text-4xl text-ink md:text-5xl">{title}</h1>
      <div className="mt-6 space-y-4 text-lg leading-relaxed text-ink2">{lede}</div>
      <div className="mt-8 flex flex-col items-start gap-4 sm:flex-row sm:items-center">
        {primary.kind === "masterclass" ? (
          <BookCta>{primary.label ?? "Book Free Masterclass"}</BookCta>
        ) : (
          <Link
            href={primary.href}
            className="inline-flex items-center justify-center rounded-full bg-trust px-6 py-3 font-semibold text-white shadow-[0_6px_24px_rgba(27,109,240,0.35)] transition-colors hover:bg-deep"
          >
            {primary.label}
          </Link>
        )}
        {secondary && (
          <Link href={secondary.href} className="text-sm font-semibold text-ink hover:text-trust">
            {secondary.label}
          </Link>
        )}
      </div>
    </header>
  );
}

export function MoneySection({
  id,
  kicker,
  heading,
  children,
}: {
  id: string;
  kicker?: string;
  heading: string;
  children: ReactNode;
}) {
  return (
    <section id={id} className="mx-auto max-w-3xl border-t border-line px-5 py-16 md:py-[72px]">
      {kicker && <Kicker>{kicker}</Kicker>}
      <h2 className={`display text-3xl text-ink md:text-4xl ${kicker ? "mt-3" : ""}`}>{heading}</h2>
      <div className="mt-6 space-y-4 text-[17px] leading-[1.7] text-ink2">{children}</div>
    </section>
  );
}

export function MoneyFaq({
  faqs,
  intro,
}: {
  faqs: readonly FaqItem[];
  intro?: ReactNode;
}) {
  return (
    <section id="faq" className="mx-auto max-w-3xl border-t border-line px-5 py-16 md:py-[72px]">
      <Kicker>Questions</Kicker>
      <h2 className="display mt-3 text-3xl text-ink md:text-4xl">Asked before you book</h2>
      {intro && <div className="mt-6 text-[17px] leading-[1.7] text-ink2">{intro}</div>}
      <dl className="mt-8 divide-y divide-line border-y border-line">
        {faqs.map((item) => (
          <div key={item.q} className="py-6">
            <dt>
              <h3 className="text-lg font-semibold text-ink">{item.q}</h3>
            </dt>
            <dd className="mt-3 text-[17px] leading-[1.7] text-ink2">{item.a}</dd>
          </div>
        ))}
      </dl>
    </section>
  );
}

export function RelatedLinks({
  links,
}: {
  links: readonly { href: string; label: string; note: string }[];
}) {
  return (
    <section id="related" className="mx-auto max-w-3xl border-t border-line px-5 py-16 md:py-[72px]">
      <Kicker>Keep reading</Kicker>
      <h2 className="display mt-3 text-3xl text-ink md:text-4xl">Related pages</h2>
      <ul className="mt-8 grid gap-4">
        {links.map((link) => (
          <li key={link.href}>
            <Link
              href={link.href}
              className="block rounded-[14px] border border-line bg-white px-5 py-4 transition-colors hover:border-trust/40"
            >
              <span className="font-semibold text-trust">{link.label}</span>
              <span className="mt-1 block text-sm text-muted">{link.note}</span>
            </Link>
          </li>
        ))}
      </ul>
    </section>
  );
}

export function ContactStrip() {
  return (
    <section className="border-t border-line bg-white">
      <div className="mx-auto max-w-3xl px-5 py-16 md:py-[72px]">
        <Kicker>Talk to us</Kicker>
        <h2 className="display mt-3 text-3xl text-ink">Whitefield, Bengaluru</h2>
        <p className="mt-4 text-[17px] leading-[1.7] text-ink2">
          {contact.entity}. {contact.address}. {contact.hours}.
        </p>
        <p className="mono mt-4 text-sm text-ink">
          {contact.phone}
          <span className="text-muted"> · </span>
          {contact.email}
        </p>
        <p className="mono mt-6 text-[11px] leading-relaxed text-muted">
          Every promise in writing · Every call recorded & AI-monitored.
        </p>
      </div>
    </section>
  );
}

export function Contents({ items }: { items: readonly { href: string; label: string }[] }) {
  return (
    <nav aria-label="On this page" className="mx-auto max-w-3xl px-5 pb-4">
      <p className="kicker text-muted">On this page</p>
      <ol className="mt-3 grid gap-2 sm:grid-cols-2">
        {items.map((item, index) => (
          <li key={item.href}>
            <a href={item.href} className="text-sm text-ink2 hover:text-trust">
              <span className="mono mr-2 text-xs text-muted">{String(index + 1).padStart(2, "0")}</span>
              {item.label}
            </a>
          </li>
        ))}
      </ol>
    </nav>
  );
}
