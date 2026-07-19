import type { Metadata } from "next";
import { MarketingShell } from "@/components/landing/MarketingShell";
import { MasterclassView } from "@/components/masterclass/MasterclassView";

export const metadata: Metadata = {
  title: "Free Masterclass — How the Syllabus Is Reverse-Engineered",
  description:
    "Watch how BrowseJobs rebuilds its syllabus from real interviews every month. Book the free live masterclass or watch the latest recording.",
};

export default function MasterclassPage() {
  return (
    <MarketingShell>
      <MasterclassView />
    </MarketingShell>
  );
}
