import Link from "next/link";
import { CheckCircleIcon, DocumentCheckIcon, UsersIcon } from "@/components/employer/icons";

export function InterviewStatusCard({
  inProgress,
  gradedLast7d,
}: {
  inProgress: number;
  gradedLast7d: number;
}) {
  return (
    <div className="rounded-[var(--bj-dash-radius)] border bg-white p-5 sm:p-6" style={{ borderColor: "var(--bj-dash-border)" }}>
      <div className="flex items-baseline justify-between">
        <h2 className="text-base font-semibold" style={{ color: "var(--bj-dash-ink)" }}>Interview status</h2>
        <Link href="/employer/pipeline" className="text-sm font-medium" style={{ color: "var(--bj-dash-primary)" }}>
          View interviews ↗
        </Link>
      </div>

      <div className="mt-4 flex items-center gap-5">
        <div className="flex flex-1 items-center gap-3">
          <span className="grid size-10 shrink-0 place-items-center rounded-xl" style={{ background: "var(--bj-dash-soft)", color: "var(--bj-dash-primary)" }}>
            <UsersIcon />
          </span>
          <div>
            <p className="text-2xl font-semibold" style={{ color: "var(--bj-dash-ink)" }}>{inProgress}</p>
            <p className="text-xs" style={{ color: "var(--bj-dash-muted)" }}>In progress</p>
          </div>
        </div>
        <div className="h-10 w-px" style={{ background: "var(--bj-dash-border)" }} />
        <div className="flex flex-1 items-center gap-3">
          <span className="grid size-10 shrink-0 place-items-center rounded-xl" style={{ background: "var(--bj-dash-soft)", color: "var(--bj-dash-primary)" }}>
            <DocumentCheckIcon />
          </span>
          <div>
            <p className="text-2xl font-semibold" style={{ color: "var(--bj-dash-ink)" }}>{gradedLast7d}</p>
            <p className="text-xs" style={{ color: "var(--bj-dash-muted)" }}>Graded, last 7 days</p>
          </div>
        </div>
      </div>

      <p className="mt-4 text-xs leading-relaxed" style={{ color: "var(--bj-dash-muted)" }}>
        Results appear here automatically once an interview is completed and graded.
      </p>
    </div>
  );
}

/** Viewing summary only — no approve/reject control lives here (PRD-E scope). */
export function ReviewQueueCard({ awaitingReview }: { awaitingReview: number }) {
  const clear = awaitingReview === 0;

  return (
    <div className="rounded-[var(--bj-dash-radius)] border bg-white p-5 sm:p-6" style={{ borderColor: "var(--bj-dash-border)" }}>
      <div className="flex items-center gap-4">
        <span
          className="grid size-11 shrink-0 place-items-center rounded-full"
          style={{ background: clear ? "var(--bj-dash-soft)" : "#fdf1dd", color: clear ? "var(--bj-dash-primary)" : "#a5720a" }}
        >
          <CheckCircleIcon />
        </span>
        <div className="min-w-0">
          <p className="text-sm" style={{ color: "var(--bj-dash-muted)" }}>
            {awaitingReview} awaiting review
          </p>
          <p className="text-lg font-semibold" style={{ color: "var(--bj-dash-ink)" }}>
            {clear ? "You're all caught up" : `${awaitingReview} graded ${awaitingReview === 1 ? "applicant needs" : "applicants need"} a look`}
          </p>
        </div>
      </div>
      <Link href="/employer/pipeline" className="mt-3 inline-flex items-center gap-1 text-sm font-medium" style={{ color: "var(--bj-dash-primary)" }}>
        View applicants →
      </Link>
    </div>
  );
}
