import type { Metadata } from "next";
import { ArgusFrame } from "@/components/argus/ArgusFrame";
import { MotionLab } from "@/components/argus/MotionLab";

const TITLE = "Motion system";

export const metadata: Metadata = {
  title: { absolute: `${TITLE} · BrowseJobs` },
  description: "Internal review of the Argus motion system. Not for indexing.",
  robots: { index: false, follow: false },
};

export default function MotionPage() {
  return (
    <ArgusFrame>
      <MotionLab />
    </ArgusFrame>
  );
}
