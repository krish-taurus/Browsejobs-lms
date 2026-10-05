import { TwoPanelBanner } from "@/components/employer/TwoPanelBanner";

/**
 * `awaitingReview` is `null` while the dashboard payload is still loading —
 * "All caught up" is only ever shown once a real zero has come back, never
 * as a loading guess (PRD-E honesty rule).
 */
export function InterviewBanner({ awaitingReview }: { awaitingReview: number | null }) {
  const statusText =
    awaitingReview === null
      ? "Checking your review queue…"
      : awaitingReview === 0
        ? "All caught up"
        : `${awaitingReview} ${awaitingReview === 1 ? "applicant" : "applicants"} awaiting review`;

  return (
    <TwoPanelBanner
      topLine="Good people."
      italicLine="Great possibilities."
      subLine="Your hiring workspace, beautifully in view."
      imageSrc="/img/employer/hiring-interview-banner.png"
      imageAlt="Recruiter speaking with a candidate during an interview"
      statusSlot={
        <>
          <span
            className="size-2 shrink-0 rounded-full"
            style={{ background: awaitingReview === 0 || awaitingReview === null ? "var(--bj-dash-status-dot)" : "#f2c14e" }}
            aria-hidden
          />
          {statusText}
        </>
      }
    />
  );
}
