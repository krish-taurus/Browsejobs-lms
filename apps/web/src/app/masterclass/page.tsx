import { MarketingShell } from "@/components/landing/MarketingShell";
import { MasterclassView } from "@/components/masterclass/MasterclassView";
import { pageMetadata } from "@/lib/seo";

export const metadata = pageMetadata({
  title: "Free Masterclass — How the Syllabus Is Reverse-Engineered",
  description:
    "Watch how BrowseJobs rebuilds its syllabus from real interviews every month. Book the free live masterclass or watch the latest recording.",
  path: "/masterclass",
});

export default function MasterclassPage() {
  return (
    <MarketingShell>
      <MasterclassView />
    </MarketingShell>
  );
}
