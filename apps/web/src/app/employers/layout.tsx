import type { Metadata } from "next";
import { EMPLOYER_META } from "@/content/employer-landing";
import { moneyMetadata } from "@/lib/seo";

/**
 * Metadata stays in the layout so the self-referencing canonical for /employers
 * cannot drift from the route. Wording matches the page. No invented rates.
 */
export const metadata: Metadata = moneyMetadata(EMPLOYER_META);

export default function EmployersLayout({ children }: { children: React.ReactNode }) {
  return children;
}
