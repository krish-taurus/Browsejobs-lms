import type { Metadata } from "next";
import { LiveRecruiterFloor } from "@/components/recruiter/LiveRecruiterFloor";

export const metadata: Metadata = {
  title: "AI Recruiter",
  robots: { index: false, follow: false },
};

/**
 * The AI Recruiter floor inside the signed-in workspace, on live data: the
 * hiring agents (job intake on WhatsApp, shortlist, BGV, final shortlist,
 * interview invites, L1, L2, client report) and the real people in each
 * step. The public demo pages keep the scripted HiringFloor.
 */
export default function AiRecruiterPage() {
  return (
    <div className="-mx-5 -my-8 md:-mx-8 md:-my-10">
      <LiveRecruiterFloor />
    </div>
  );
}
