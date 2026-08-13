import type { ReactNode } from "react";
import { NO_INDEX } from "@/lib/seo";

/**
 * `/v3` is a preview alias of the promoted template: it renders byte-identical
 * output to `/` and `/courses/[slug]`. Left indexable it is exact duplicate
 * content competing with the live pages, so the alias stays out of the index
 * while remaining reachable for internal review.
 */
export const metadata = NO_INDEX;

export default function V3PreviewLayout({ children }: { children: ReactNode }) {
  return <>{children}</>;
}
