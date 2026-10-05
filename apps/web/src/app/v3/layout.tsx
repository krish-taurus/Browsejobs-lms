import type { Metadata } from "next";
import type { ReactNode } from "react";
import { canonical } from "@/lib/seo";

/** Preview alias of the homepage. Keep it reachable; don't index a second copy. */
export const metadata: Metadata = {
  robots: { index: false, follow: true },
  alternates: { canonical: canonical("/") },
};

export default function V3Layout({ children }: { children: ReactNode }) {
  return children;
}
