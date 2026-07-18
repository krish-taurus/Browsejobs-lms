import Link from "next/link";
import type { ReactNode } from "react";
import { Wordmark } from "@/components/brand/Wordmark";

/**
 * Shared shell for legal pages. Content is DPDP-aligned structure with
 * clearly-marked placeholders ([CIN], [GST], Grievance Officer) for the
 * founder to fill — flagged for legal review before launch (spec §10).
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
    <div className="min-h-screen bg-paper">
      <header className="border-b border-line bg-white">
        <div className="mx-auto flex max-w-3xl items-center justify-between px-5 py-4">
          <Link href="/" aria-label="BrowseJobs home">
            <Wordmark />
          </Link>
          <Link href="/" className="text-sm text-trust hover:underline">
            ← Back to site
          </Link>
        </div>
      </header>
      <main className="mx-auto max-w-3xl px-5 py-12">
        <p className="kicker text-trust">Legal</p>
        <h1 className="display mt-2 text-3xl text-ink">{title}</h1>
        <p className="mono mt-2 text-xs text-muted">Last updated: {updated}</p>
        <div className="prose-legal mt-8 space-y-6 text-ink2 [&_h2]:mt-8 [&_h2]:font-semibold [&_h2]:text-ink [&_ul]:list-disc [&_ul]:pl-5 [&_ul]:space-y-1">
          {children}
        </div>
      </main>
    </div>
  );
}
