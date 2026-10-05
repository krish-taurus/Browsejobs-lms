import { MonoCounter } from "@/components/motion/MonoCounter";
import type { EmployerJobCounts } from "@/lib/employer";

/** Same 2×2/1×4 divider technique as the Dashboard/Team summary strips. */
function stripItemClasses(i: number): string {
  const mobileRight = i === 0 || i === 2;
  const mobileBottom = i === 0 || i === 1;
  const desktopRight = i !== 3;
  return [
    mobileRight ? "border-r" : "",
    mobileBottom ? "border-b" : "",
    "sm:border-b-0",
    desktopRight ? "sm:border-r" : "sm:border-r-0",
  ].join(" ");
}

/**
 * Total jobs / Published / Closed / Open positions — all four from
 * `counts`, which JobController::index() computes workspace-wide,
 * independent of whatever status/search filter is currently selected below
 * (PRD-E jobs kit honesty rule).
 */
export function JobsSummary({ counts }: { counts: EmployerJobCounts | null }) {
  const items: { label: string; value: number | null; dot?: boolean; sub?: string }[] = [
    { label: "Total jobs", value: counts?.total ?? null },
    { label: "Published", value: counts?.published ?? null, dot: true },
    { label: "Closed", value: counts?.closed ?? null },
    { label: "Open positions", value: counts?.open_positions ?? null, sub: "Across published jobs" },
  ];

  return (
    <div
      className="grid grid-cols-2 rounded-[var(--bj-dash-radius)] border bg-white sm:grid-cols-4"
      style={{ borderColor: "var(--bj-dash-border)" }}
    >
      {items.map((item, i) => (
        <div key={item.label} className={`p-5 sm:p-6 ${stripItemClasses(i)}`} style={{ borderColor: "var(--bj-dash-border)" }}>
          <span className="flex items-center gap-1.5 text-sm" style={{ color: "var(--bj-dash-muted)" }}>
            {item.label}
            {item.dot && <span className="size-1.5 rounded-full" style={{ background: "var(--bj-dash-primary)" }} aria-hidden />}
          </span>
          <p className="bj-dash-serif mt-2" style={{ fontSize: "clamp(2rem, 4vw, 2.75rem)", color: "var(--bj-dash-ink)" }}>
            {item.value === null ? (
              <span className="inline-block h-10 w-10 animate-pulse rounded-lg" style={{ background: "var(--bj-dash-soft)" }} />
            ) : (
              <MonoCounter value={item.value} className="bj-dash-serif" />
            )}
          </p>
          {item.sub && item.value !== null && (
            <span className="text-xs" style={{ color: "var(--bj-dash-muted)" }}>{item.sub}</span>
          )}
        </div>
      ))}
    </div>
  );
}
