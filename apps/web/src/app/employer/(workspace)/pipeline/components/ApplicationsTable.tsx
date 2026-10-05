import { nextStage, STAGE_LABELS, type ApplicationRow } from "@/lib/employer";
import { BAND_BG, BAND_FG, BAND_LABEL, scoreBand } from "./score";

/** A stable colour per candidate name, so the same person's initial always reads the same. */
const AVATAR_HUES = [152, 96, 176, 40, 8];
function avatarTone(name: string): string {
  let hash = 0;
  for (let i = 0; i < name.length; i++) hash = (hash * 31 + name.charCodeAt(i)) | 0;
  return `hsl(${AVATAR_HUES[Math.abs(hash) % AVATAR_HUES.length]} 35% 92%)`;
}

function Avatar({ name }: { name: string }) {
  return (
    <span
      aria-hidden
      className="grid size-10 shrink-0 place-items-center rounded-full text-sm font-semibold"
      style={{ background: avatarTone(name), color: "var(--bj-dash-primary)" }}
    >
      {name.trim().charAt(0).toUpperCase() || "?"}
    </span>
  );
}

export function ApplicationsTable({
  heading,
  count,
  rows,
  jobs,
  loading,
  selectedId,
  onSelect,
  onAdvance,
  busyId,
  canAdvance,
}: {
  heading: string;
  count: number;
  rows: ApplicationRow[];
  jobs: { id: number; title: string }[];
  loading: boolean;
  selectedId: number | null;
  onSelect: (row: ApplicationRow) => void;
  onAdvance: (row: ApplicationRow) => void;
  busyId: number | null;
  /** False for a role without pipeline-management permission — read-only rows, no transition control. */
  canAdvance: boolean;
}) {
  return (
    <div className="flex h-full min-w-0 flex-col">
      <div className="flex items-center gap-2.5 border-b px-4 py-3.5" style={{ borderColor: "var(--bj-dash-border)" }}>
        <h2 className="text-base font-semibold" style={{ color: "var(--bj-dash-ink)" }}>{heading}</h2>
        <span className="rounded-full px-2.5 py-0.5 text-xs font-medium" style={{ background: "var(--bj-dash-soft)", color: "var(--bj-dash-primary)" }}>
          {count}
        </span>
        <span className="ml-auto text-xs" style={{ color: "var(--bj-dash-muted)" }}>
          {count} {count === 1 ? "result" : "results"}
        </span>
      </div>

      <div className="min-h-0 flex-1 overflow-y-auto">
        {loading ? (
          <div className="space-y-2 p-4">
            {[0, 1, 2, 3].map((i) => (
              <div key={i} className="h-20 animate-pulse rounded-xl" style={{ background: "var(--bj-dash-soft)" }} />
            ))}
          </div>
        ) : rows.length === 0 ? (
          <p className="p-6 text-sm" style={{ color: "var(--bj-dash-muted)" }}>No applications in this stage.</p>
        ) : (
          <table className="w-full text-left text-sm">
            <thead className="sr-only">
              <tr>
                <th scope="col">Candidate</th>
                <th scope="col">Stage</th>
                <th scope="col">Score</th>
                <th scope="col">Next step</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => {
                const name = row.candidate?.name ?? `Candidate #${row.id}`;
                const role = jobs.find((j) => j.id === row.job_id)?.title ?? "—";
                const band = scoreBand(row.mock_score);
                const target = nextStage(row.stage);
                const selected = selectedId === row.id;

                return (
                  <tr
                    key={row.id}
                    className="relative cursor-pointer border-b transition-colors last:border-b-0 hover:bg-[var(--bj-dash-soft)]/40"
                    style={{
                      borderColor: "var(--bj-dash-border)",
                      background: selected ? "var(--bj-dash-soft)" : undefined,
                      minHeight: "var(--bj-row-min-height, 84px)",
                    }}
                    onClick={() => onSelect(row)}
                  >
                    <td className="py-3 pl-4 pr-3">
                      {selected && <span aria-hidden className="absolute inset-y-0 left-0 w-[3px]" style={{ background: "var(--bj-dash-primary)" }} />}
                      <div className="flex items-center gap-3">
                        <Avatar name={name} />
                        <div className="min-w-0">
                          <button
                            type="button"
                            onClick={(e) => { e.stopPropagation(); onSelect(row); }}
                            className="truncate text-left text-sm font-semibold underline-offset-2 hover:underline"
                            style={{ color: "var(--bj-dash-ink)" }}
                          >
                            {name}
                          </button>
                          <p className="truncate text-xs" style={{ color: "var(--bj-dash-muted)" }}>{role}</p>
                        </div>
                      </div>
                    </td>
                    <td className="py-3 pr-3 align-middle">
                      <span className="rounded-full px-2.5 py-1 text-xs font-medium" style={{ background: "var(--bj-dash-canvas)", color: "var(--bj-dash-ink)" }}>
                        {STAGE_LABELS[row.stage] ?? row.stage}
                      </span>
                    </td>
                    <td className="py-3 pr-3 align-middle">
                      <div className="flex items-center gap-2">
                        <span className="font-semibold" style={{ color: "var(--bj-dash-ink)" }}>
                          {row.mock_score === null || row.mock_score === undefined ? "—" : row.mock_score}
                        </span>
                        <span className="rounded-full px-2 py-0.5 text-[11px] font-medium" style={{ background: BAND_BG[band], color: BAND_FG[band] }}>
                          {BAND_LABEL[band]}
                        </span>
                      </div>
                    </td>
                    <td className="py-3 pr-4 align-middle">
                      {canAdvance && target ? (
                        <button
                          type="button"
                          onClick={(e) => { e.stopPropagation(); onAdvance(row); }}
                          disabled={busyId === row.id}
                          className="rounded-full border px-3 py-1.5 text-xs font-semibold transition-colors hover:border-[var(--bj-dash-primary)] hover:text-[var(--bj-dash-primary)] disabled:cursor-not-allowed disabled:opacity-40"
                          style={{ borderColor: "var(--bj-dash-border)", color: "var(--bj-dash-ink)" }}
                        >
                          {busyId === row.id ? "Moving…" : `${STAGE_LABELS[target] ?? target} →`}
                        </button>
                      ) : (
                        <span className="text-xs" style={{ color: "var(--bj-dash-muted)" }}>{canAdvance ? "Terminal stage" : "View only"}</span>
                      )}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        )}
      </div>
    </div>
  );
}
