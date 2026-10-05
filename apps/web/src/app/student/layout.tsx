import type { Metadata } from "next";
import type { ReactNode } from "react";
import { canonical } from "@/lib/seo";

export const metadata: Metadata = {
  title: "Sign in",
  description: "Sign in to your BrowseJobs account.",
  alternates: { canonical: canonical("/student") },
};

export default function StudentLayout({ children }: { children: ReactNode }) {
  return children;
}
