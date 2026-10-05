import { STAGE_LABELS, STAGE_ORDER, type ApplicationStage, type EmployerApplicationCounts } from "@/lib/employer";
import {
  CalendarIcon,
  CheckCircleIcon,
  DiamondIcon,
  DocumentIcon,
  StarIcon,
  UsersIcon,
  type IconComponent,
} from "@/components/employer/icons";
import { BAND_FG } from "./score";

export type StageFilter = "all" | ApplicationStage;

/**
 * This page's own icon per stage — a purely visual wayfinding aid, so it
 * lives here rather than on the shared STAGE_LABELS/STAGE_ORDER used
 * elsewhere. Partial: rejected/withdrawn have no nav row (see file-level
 * comment below) so they're never looked up here, but the type still needs
 * to admit their absence since they're part of ApplicationStage.
 */
const STAGE_ICON: Partial<Record<StageFilter, IconComponent>> = {
  all: DocumentIcon,
  applied: DocumentIcon,
  graded: StarIcon,
  shortlisted: UsersIcon,
  l1: CalendarIcon,
  l2: CalendarIcon,
  human_round: UsersIcon,
  offer: DiamondIcon,
  hired: CheckCircleIcon,
};

/**
 * Longer form for this nav specifically ("L1 interview", not the bare "L1"
 * used in tighter contexts elsewhere — e.g. the dashboard's pipeline-pulse
 * bar) — a display-only override, kept local so it doesn't touch the shared
 * STAGE_LABELS other approved pages already render.
 */
const NAV_LABEL: Partial<Record<ApplicationStage, string>> = {
  l1: "L1 interview",
  l2: "L2 interview",
};

/**
 * All applications, then the real stages in their existing workflow order
 * (STAGE_ORDER — Applied…Hired, this app's actual backend enum, unchanged).
 * rejected/withdrawn have no dedicated row here (matching the approved
 * reference's exact 9-row nav) but are never hidden from the data itself —
 * they still count toward "All applications" and still appear in the table
 * there.
 */
export function StageNav({
  stage,
  onStageChange,
  counts,
}: {
  stage: StageFilter;
  onStageChange: (s: StageFilter) => void;
  counts: EmployerApplicationCounts | null;
}) {
  const countFor = (s: StageFilter): number | null => {
    if (counts === null) return null;
    if (s === "all") return counts.total;
    return counts.by_stage[s] ?? 0;
  };

  const Row = ({ value, label }: { value: StageFilter; label: string }) => {
    const active = stage === value;
    const count = countFor(value);
    const Icon = STAGE_ICON[value] ?? DocumentIcon;
    return (
      <button
        type="button"
        onClick={() => onStageChange(value)}
        aria-pressed={active}
        className="relative flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-left text-sm transition-colors"
        style={active ? { background: "var(--bj-dash-soft)", color: "var(--bj-dash-primary)" } : { color: "var(--bj-dash-ink)" }}
      >
        {active && <span aria-hidden className="absolute inset-y-1 left-0 w-[3px] rounded-full" style={{ background: "var(--bj-dash-primary)" }} />}
        <Icon className={`size-4 shrink-0 ${active ? "text-[var(--bj-dash-primary)]" : "text-[var(--bj-dash-muted)]"}`} />
        <span className="min-w-0 flex-1 truncate font-medium">{label}</span>
        <span
          className="ml-auto shrink-0 rounded-full px-2 py-0.5 text-xs"
          style={active ? { background: "white", color: "var(--bj-dash-primary)" } : { background: "var(--bj-dash-canvas)", color: "var(--bj-dash-muted)" }}
        >
          {count === null ? "…" : count}
        </span>
      </button>
    );
  };

  return (
    <nav aria-label="Hiring stages" className="flex h-full flex-col p-4">
      <h2 className="px-2 text-sm font-semibold" style={{ color: "var(--bj-dash-ink)" }}>Hiring stages</h2>
      <div className="mt-2 space-y-0.5">
        <Row value="all" label="All applications" />
        {STAGE_ORDER.map((s) => (
          <Row key={s} value={s} label={NAV_LABEL[s] ?? STAGE_LABELS[s]} />
        ))}
      </div>

      <div className="mt-auto border-t pt-3" style={{ borderColor: "var(--bj-dash-border)" }}>
        <p className="px-2 text-xs font-semibold uppercase tracking-wide" style={{ color: "var(--bj-dash-muted)" }}>Score guide</p>
        <ul className="mt-2 space-y-1.5 px-2 text-xs" style={{ color: "var(--bj-dash-muted)" }}>
          <li className="flex items-center gap-2"><span className="size-2 rounded-full" style={{ background: BAND_FG.strong }} />Strong 80+</li>
          <li className="flex items-center gap-2"><span className="size-2 rounded-full" style={{ background: BAND_FG.fair }} />Fair 60–79</li>
          <li className="flex items-center gap-2"><span className="size-2 rounded-full" style={{ background: BAND_FG.below }} />Below bar &lt;60</li>
          <li className="flex items-center gap-2"><span className="size-2 rounded-full" style={{ background: BAND_FG.unscored }} />Unscored</li>
        </ul>
      </div>
    </nav>
  );
}
