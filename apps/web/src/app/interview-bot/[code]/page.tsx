import type { Metadata } from "next";
import { InterviewBot } from "@/components/hiring/InterviewBot";

export const metadata: Metadata = {
  title: { absolute: "BrowseJobs AI Interviewer" },
  robots: { index: false, follow: false },
};

/**
 * The AI interviewer inside an L1/L2 Zoom call. A meeting bot joins the call
 * and opens this page as its camera: the call's audio is this page's
 * microphone, and what this page says and shows is what the candidate hears
 * and sees. The private code in the URL is the only key (see the API's
 * ResolveInterviewBot).
 */
export default async function InterviewBotPage({ params }: { params: Promise<{ code: string }> }) {
  const { code } = await params;

  return <InterviewBot code={code} />;
}
