import type { ReactNode } from "react";
import { NO_INDEX } from "@/lib/seo";

/**
 * Certificate verification answers any `[code]` with a 200, so without this the
 * route is an unbounded set of crawlable URLs.
 */
export const metadata = NO_INDEX;

export default function VerifyLayout({ children }: { children: ReactNode }) {
  return <>{children}</>;
}
