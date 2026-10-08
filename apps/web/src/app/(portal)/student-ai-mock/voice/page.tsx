"use client";

import { MockKindPage } from "@/components/mocks/MockKindPage";
import { VoiceInterviewCard, useMockSummary } from "@/components/mocks/MockCards";

export default function VoiceInterviewsPage() {
  const { summary, loading, reload } = useMockSummary();

  return (
    <MockKindPage
      kind="voice"
      counts={summary?.kind_counts}
      emptyText="No voice interviews yet. Start one above — your scorecards will appear here."
    >
      {loading ? (
        <div className="shimmer mt-6 h-36 rounded-2xl" />
      ) : summary ? (
        <div className="mt-2">
          <VoiceInterviewCard summary={summary} reload={reload} />
        </div>
      ) : null}
    </MockKindPage>
  );
}
