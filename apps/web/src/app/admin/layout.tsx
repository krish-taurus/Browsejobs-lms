import type { ReactNode } from "react";
import { NO_INDEX } from "@/lib/seo";

/**
 * Sits above the `(panel)` group so it also covers `/admin` itself, which lives
 * outside it. Exists only to keep the staff panel out of the search index.
 */
export const metadata = NO_INDEX;

export default function AdminLayout({ children }: { children: ReactNode }) {
  return <>{children}</>;
}
