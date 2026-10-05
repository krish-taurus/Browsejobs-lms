import type { EmployerApplicationCounts } from "@/lib/employer";

/**
 * Applications / Scored / Unscored / Hired — application counts, not people
 * (the same candidate can have more than one application). Scoped to the
 * current workspace + role + search, same as the stage nav below, and
 * independent of pagination — see ApplicationController::indexForWorkspace().
 */
export function PipelineHeader({ counts, search }: { counts: EmployerApplicationCounts | null; search: string }) {
  const stats: { label: string; value: number | null }[] = [
    { label: "Applications", value: counts?.total ?? null },
    { label: "Scored", value: counts?.scored ?? null },
    { label: "Unscored", value: counts?.unscored ?? null },
    { label: "Hired", value: counts?.hired ?? null },
  ];

  return (
    <div
      className="flex flex-col gap-6 rounded-[var(--bj-dash-radius)] p-6 text-white sm:flex-row sm:items-center sm:justify-between md:p-8"
      style={{ background: "var(--bj-dash-hero)" }}
    >
      <div>
        <p className="font-mono text-[11px] font-semibold uppercase tracking-widest text-white/70">
          Workspace / Pipeline
        </p>
        <h1 className="bj-dash-serif mt-1 text-3xl md:text-4xl">Hiring pipeline</h1>
        <p className="mt-1 text-sm text-white/80">
          {search ? `Every application matching "${search}".` : "Every application. One clear view."}
        </p>
      </div>

      <div className="flex divide-x divide-white/15 text-center sm:text-left">
        {stats.map((stat) => (
          <div key={stat.label} className="px-4 first:pl-0 sm:px-6">
            <p className="bj-dash-serif text-3xl md:text-4xl">
              {stat.value === null ? (
                <span className="inline-block h-8 w-8 animate-pulse rounded bg-white/15" />
              ) : (
                stat.value
              )}
            </p>
            <p className="mt-1 text-xs text-white/70">{stat.label}</p>
          </div>
        ))}
      </div>
    </div>
  );
}
