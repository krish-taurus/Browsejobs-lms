"use client";

import { MockKindPage } from "@/components/mocks/MockKindPage";
import { TextPracticeCard, useMockSummary } from "@/components/mocks/MockCards";

export default function PracticeInterviewsPage() {
  const { summary, loading } = useMockSummary();

  return (
    <MockKindPage
      kind="practice"
      counts={summary?.kind_counts}
      emptyText="No practice interviews yet. Start one above — your scorecards will appear here."
    >
      {loading ? (
        <div className="shimmer mt-6 h-36 rounded-2xl" />
      ) : summary ? (
        <TextPracticeCard summary={summary} />
      ) : null}
    </MockKindPage>
  );
}
