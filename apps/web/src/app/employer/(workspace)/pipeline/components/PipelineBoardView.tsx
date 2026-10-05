import Link from "next/link";
import { nextStage, STAGE_LABELS, STAGE_ORDER, type ApplicationRow } from "@/lib/employer";
import { BAND_FG, BAND_LABEL, scoreBand } from "./score";

/**
 * The pre-redesign board's own columns/cards, restyled to the emerald
 * tokens — same data, same "advance" action (there is no real drag-and-drop
 * to preserve; this was always a button, not a draggable card), same
 * authorization and transition endpoint as List.
 */
export function PipelineBoardView({
  rows,
  jobs,
  onAdvance,
  busyId,
  canAdvance,
}: {
  rows: ApplicationRow[];
  jobs: { id: number; title: string }[];
  onAdvance: (row: ApplicationRow) => void;
  busyId: number | null;
  canAdvance: boolean;
}) {
  const byStage: Record<string, ApplicationRow[]> = {};
  for (const s of STAGE_ORDER) byStage[s] = [];
  for (const r of rows) {
    if (byStage[r.stage]) byStage[r.stage].push(r);
  }
  for (const s of STAGE_ORDER) {
    byStage[s].sort((a, b) => (b.mock_score ?? -1) - (a.mock_score ?? -1));
  }

  return (
    <div className="h-full overflow-x-auto p-4">
      <div className="flex h-full w-max gap-4">
        {STAGE_ORDER.map((stage) => {
          const items = byStage[stage] ?? [];
          return (
            <section key={stage} className="flex w-[268px] shrink-0 flex-col">
              <div className="mb-2.5 flex items-center gap-2 px-0.5">
                <span className="text-sm font-semibold" style={{ color: "var(--bj-dash-ink)" }}>{STAGE_LABELS[stage] ?? stage}</span>
                <span
                  className="ml-auto rounded-full px-2 py-0.5 text-xs font-medium"
                  style={{ background: "var(--bj-dash-soft)", color: "var(--bj-dash-primary)" }}
                >
                  {items.length}
                </span>
              </div>

              <div className="min-h-[160px] flex-1 space-y-2">
                {items.length === 0 ? (
                  <div
                    className="rounded-xl border border-dashed px-3 py-8 text-center text-xs"
                    style={{ borderColor: "var(--bj-dash-border)", color: "var(--bj-dash-muted)" }}
                  >
                    Nobody here
                  </div>
                ) : (
                  items.map((row) => {
                    const band = scoreBand(row.mock_score);
                    const target = nextStage(row.stage);
                    const name = row.candidate?.name ?? `Candidate #${row.id}`;
                    const role = jobs.find((j) => j.id === row.job_id)?.title ?? "—";

                    return (
                      <div
                        key={row.id}
                        className="relative overflow-hidden rounded-xl border bg-white p-3"
                        style={{ borderColor: "var(--bj-dash-border)" }}
                      >
                        <span aria-hidden className="absolute inset-y-0 left-0 w-[3px]" style={{ background: BAND_FG[band] }} />
                        <div className="flex items-start gap-2.5 pl-1.5">
                          <span
                            aria-hidden
                            className="grid size-8 shrink-0 place-items-center rounded-full text-xs font-semibold"
                            style={{ background: "var(--bj-dash-soft)", color: "var(--bj-dash-primary)" }}
                          >
                            {name.trim().charAt(0).toUpperCase() || "?"}
                          </span>
                          <div className="min-w-0 flex-1">
                            <p className="truncate text-[13px] font-semibold" style={{ color: "var(--bj-dash-ink)" }}>{name}</p>
                            <p className="mt-0.5 truncate text-[11px]" style={{ color: "var(--bj-dash-muted)" }}>{role}</p>
                          </div>
                          <span className="shrink-0 text-xs font-semibold" style={{ color: BAND_FG[band] }}>
                            {row.mock_score === null || row.mock_score === undefined ? "—" : row.mock_score}
                          </span>
                        </div>
                        <p className="mt-1.5 pl-1.5 text-[10px] font-semibold uppercase tracking-wide" style={{ color: BAND_FG[band] }}>
                          {BAND_LABEL[band]}
                        </p>
                        <div className="mt-2 flex items-center gap-3 border-t pl-1.5 pt-2" style={{ borderColor: "var(--bj-dash-border)" }}>
                          <Link
                            href={`/employer/jobs/${row.job_id}/candidates/${row.id}`}
                            className="text-[11px] font-medium underline-offset-2 hover:underline"
                            style={{ color: "var(--bj-dash-muted)" }}
                          >
                            Open
                          </Link>
                          {canAdvance && target && (
                            <button
                              type="button"
                              onClick={() => onAdvance(row)}
                              disabled={busyId === row.id}
                              className="ml-auto rounded-full border px-2.5 py-1 text-[11px] font-medium transition-colors hover:border-[var(--bj-dash-primary)] hover:text-[var(--bj-dash-primary)] disabled:opacity-40"
                              style={{ borderColor: "var(--bj-dash-border)", color: "var(--bj-dash-ink)" }}
                            >
                              {busyId === row.id ? "…" : `→ ${STAGE_LABELS[target] ?? target}`}
                            </button>
                          )}
                        </div>
                      </div>
                    );
                  })
                )}
              </div>
            </section>
          );
        })}
      </div>
    </div>
  );
}
