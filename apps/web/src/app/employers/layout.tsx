import type { Metadata } from "next";
import { moneyMetadata } from "@/lib/seo";

/**
 * /employers is a client component (the animated product demo), and a client
 * component cannot export `metadata`. Its SEO tags live here instead, built with
 * the same moneyMetadata() helper every other landing page uses, so the title,
 * description, canonical and social tags follow one pattern.
 *
 * Keep the wording to what the page actually shows — no invented numbers.
 */
export const metadata: Metadata = moneyMetadata({
  path: "/employers",
  title: "Hire from Real, Scored AI Interviews",
  description:
    "Post a role, set up your interview rounds, and review a scored interview transcript for every applicant before you decide who to talk to.",
});

export default function EmployersLayout({ children }: { children: React.ReactNode }) {
  return children;
}
