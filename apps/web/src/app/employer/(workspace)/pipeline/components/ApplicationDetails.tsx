import Link from "next/link";
import { nextStage, STAGE_LABELS, type ApplicationRow } from "@/lib/employer";
import { BAND_BG, BAND_FG, BAND_LABEL, scoreBand } from "./score";

/**
 * The selected application's real details — nothing here is inferred from
 * the screenshot. `onClose` clears selection AND returns focus to wherever
 * opened it (the caller passes a ref-based restorer); this component itself
 * only renders the close button.
 */
export function ApplicationDetails({
  application,
  roleTitle,
  onClose,
  onAdvance,
  busy,
  canAdvance,
  closeButtonRef,
}: {
  application: ApplicationRow | null;
  roleTitle: string;
  onClose: () => void;
  onAdvance: (row: ApplicationRow) => void;
  busy: boolean;
  canAdvance: boolean;
  closeButtonRef?: React.Ref<HTMLButtonElement>;
}) {
  if (application === null) {
    return (
      <div className="flex h-full flex-col items-center justify-center p-8 text-center">
        <p className="text-sm" style={{ color: "var(--bj-dash-muted)" }}>
          Select an application to see its details here.
        </p>
      </div>
    );
  }

  const name = application.candidate?.name ?? `Candidate #${application.id}`;
  const band = scoreBand(application.mock_score);
  const target = nextStage(application.stage);
  const hasScore = application.mock_score !== null && application.mock_score !== undefined;

  return (
    <div className="flex h-full flex-col p-5">
      <div className="flex items-center justify-between">
        <h2 className="text-base font-semibold" style={{ color: "var(--bj-dash-ink)" }}>Application details</h2>
        <button
          ref={closeButtonRef}
          type="button"
          onClick={onClose}
          aria-label="Close application details"
          className="grid size-8 place-items-center rounded-full text-lg transition-colors hover:bg-[var(--bj-dash-soft)]"
          style={{ color: "var(--bj-dash-muted)" }}
        >
          ×
        </button>
      </div>

      <div className="mt-4 flex items-center gap-3">
        <span
          aria-hidden
          className="grid size-14 shrink-0 place-items-center rounded-full text-lg font-semibold"
          style={{ background: "var(--bj-dash-soft)", color: "var(--bj-dash-primary)" }}
        >
          {name.trim().charAt(0).toUpperCase() || "?"}
        </span>
        <div className="min-w-0">
          <p className="truncate text-lg font-semibold" style={{ color: "var(--bj-dash-ink)" }}>{name}</p>
          <p className="truncate text-sm" style={{ color: "var(--bj-dash-muted)" }}>{roleTitle}</p>
        </div>
      </div>

      <div className="mt-5 border-t pt-4" style={{ borderColor: "var(--bj-dash-border)" }}>
        <p className="text-xs font-medium uppercase tracking-wide" style={{ color: "var(--bj-dash-muted)" }}>
          Job-specific interview score
        </p>
        <div className="mt-1.5 flex items-center gap-2.5">
          <span className="bj-dash-serif text-4xl" style={{ color: "var(--bj-dash-ink)" }}>
            {hasScore ? application.mock_score : "—"}
          </span>
          <span className="rounded-full px-2.5 py-1 text-xs font-semibold" style={{ background: BAND_BG[band], color: BAND_FG[band] }}>
            {BAND_LABEL[band]}
          </span>
        </div>
        {hasScore && band === "below" && (
          <p className="mt-1.5 text-xs" style={{ color: "var(--bj-dash-muted)" }}>Below the 60-point threshold.</p>
        )}
      </div>

      <dl className="mt-4 space-y-2.5 border-t pt-4 text-sm" style={{ borderColor: "var(--bj-dash-border)" }}>
        <div className="flex justify-between gap-3">
          <dt style={{ color: "var(--bj-dash-muted)" }}>Current stage</dt>
          <dd className="font-medium" style={{ color: "var(--bj-dash-ink)" }}>{STAGE_LABELS[application.stage] ?? application.stage}</dd>
        </div>
        <div className="flex justify-between gap-3">
          <dt style={{ color: "var(--bj-dash-muted)" }}>Role</dt>
          <dd className="truncate font-medium" style={{ color: "var(--bj-dash-ink)" }}>{roleTitle}</dd>
        </div>
        {target && (
          <div className="flex justify-between gap-3">
            <dt style={{ color: "var(--bj-dash-muted)" }}>Next stage</dt>
            <dd className="font-medium" style={{ color: "var(--bj-dash-ink)" }}>{STAGE_LABELS[target] ?? target}</dd>
          </div>
        )}
      </dl>

      <div className="mt-5 space-y-2.5">
        {canAdvance && target && (
          <button
            type="button"
            onClick={() => onAdvance(application)}
            disabled={busy}
            className="flex w-full items-center justify-center gap-2 rounded-full px-4 py-2.5 text-sm font-semibold text-white transition disabled:opacity-50"
            style={{ background: "var(--bj-dash-primary)" }}
          >
            {busy ? "Moving…" : `Move to ${STAGE_LABELS[target] ?? target}`}
            {!busy && <span aria-hidden>→</span>}
          </button>
        )}
        <Link
          href={`/employer/jobs/${application.job_id}/candidates/${application.id}`}
          className="flex w-full items-center justify-center gap-1.5 rounded-full border px-4 py-2.5 text-sm font-semibold"
          style={{ borderColor: "var(--bj-dash-border)", color: "var(--bj-dash-ink)" }}
        >
          View full application <span aria-hidden>↗</span>
        </Link>
      </div>

      <p className="mt-4 flex items-start gap-1.5 text-xs" style={{ color: "var(--bj-dash-muted)" }}>
        <span aria-hidden>ⓘ</span> Moving an application does not change its interview score.
      </p>
    </div>
  );
}
