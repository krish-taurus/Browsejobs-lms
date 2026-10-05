import type { Metadata } from "next";
import type { ReactNode } from "react";
import { canonical } from "@/lib/seo";

export const metadata: Metadata = {
  title: "Create a free account",
  description:
    "Create a free BrowseJobs account. Course registration comes later, only after the free counselling, masterclass, and bootcamp.",
  alternates: { canonical: canonical("/register") },
};

export default function RegisterLayout({ children }: { children: ReactNode }) {
  return children;
}
