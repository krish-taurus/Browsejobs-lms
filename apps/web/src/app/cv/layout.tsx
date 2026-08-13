import type { ReactNode } from "react";
import { NO_INDEX } from "@/lib/seo";

/**
 * `/cv/print/[id]` and `/cv/shared/[token]` render a named person's CV behind a
 * shareable token. Indexing them would publish candidate personal data, so the
 * noindex here is a privacy control as much as an SEO one.
 */
export const metadata = NO_INDEX;

export default function CvLayout({ children }: { children: ReactNode }) {
  return <>{children}</>;
}
