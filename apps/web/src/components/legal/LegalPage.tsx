import type { ReactNode } from "react";
import { ApShell } from "@/components/ap/ApShell";

/**
 * Shared shell for legal pages, in the site-wide Apple-direction design.
 * Content is DPDP-aligned structure with clearly-marked placeholders
 * ([CIN], [GST], Grievance Officer) for the founder to fill — flagged for
 * legal review before launch (spec §10).
 */
export function LegalPage({
  title,
  updated,
  children,
}: {
  title: string;
  updated: string;
  children: ReactNode;
}) {
  return (
    <ApShell>
      <article className="ap-article">
        <header className="ap-hero s-white">
          <div className="ap-narrow center">
            <p className="eyebrow">Legal</p>
            <h1 className="h-hero">{title}</h1>
            <p className="fine">Last updated: {updated}</p>
          </div>
        </header>
        <section className="ap-sec" style={{ paddingTop: 0 }}>
          <div className="ap-narrow ap-prose ap-legal">{children}</div>
        </section>
      </article>
    </ApShell>
  );
}
