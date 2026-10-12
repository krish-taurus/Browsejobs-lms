import type { Metadata } from "next";
import { HireInterviewStart } from "@/components/hiring/HireInterviewStart";

export const metadata: Metadata = {
  title: { absolute: "Your interview · BrowseJobs" },
  robots: { index: false, follow: false },
};

/**
 * The link the message agent WhatsApps a shortlisted candidate. Says what the
 * interview is, signs them in with a WhatsApp OTP, and opens the voice
 * interview room.
 */
export default async function HireInterviewPage({ params }: { params: Promise<{ token: string }> }) {
  const { token } = await params;

  return <HireInterviewStart token={token} />;
}
