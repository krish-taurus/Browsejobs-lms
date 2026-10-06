import type { Metadata } from "next";
import { InterviewContinue } from "@/components/auth/InterviewContinue";
import { canonical } from "@/lib/seo";

const TITLE = "Take your free AI interview · BrowseJobs";
const DESCRIPTION =
  "Sign in with your phone and start the free AI interview. Fifteen questions from your CV. A score of 75% or more counts as clear and puts you in front of HR with your score.";

export const metadata: Metadata = {
  title: { absolute: TITLE },
  description: DESCRIPTION,
  alternates: { canonical: canonical("/interview") },
  robots: { index: false, follow: false },
};

export default function InterviewPage() {
  return <InterviewContinue />;
}
