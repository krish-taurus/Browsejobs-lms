import type { Metadata } from "next";
import { HiringFloor } from "@/components/recruiter/HiringFloor";

export const metadata: Metadata = {
  title: "AI Recruiter",
  robots: { index: false, follow: false },
};

/**
 * Same floor as the public demo, inside the signed-in workspace.
 * Still demo data. Jobs, pipeline, and team are unchanged.
 */
export default function AiRecruiterPage() {
  return (
    <div className="-mx-5 -my-8 md:-mx-8 md:-my-10">
      <HiringFloor variant="console" />
    </div>
  );
}
