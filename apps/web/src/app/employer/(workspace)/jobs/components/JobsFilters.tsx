"use client";

import type { EmployerJobCounts } from "@/lib/employer";
import { SearchIcon } from "@/components/employer/icons";

export type StatusFilter = "all" | "published" | "closed";

const PILLS: { key: StatusFilter; label: string }[] = [
  { key: "all", label: "All jobs" },
  { key: "published", label: "Published" },
  { key: "closed", label: "Closed" },
];

/**
 * Plain pressed buttons, not ARIA tabs — there's no tab-panel keyboard
 * behaviour (arrow-key roving focus) behind these, and mislabelling them
 * `role="tab"` without that would be worse than no ARIA at all.
 */
export function JobsFilters({
  status,
  onStatusChange,
  counts,
  search,
  onSearchChange,
}: {
  status: StatusFilter;
  onStatusChange: (s: StatusFilter) => void;
  counts: EmployerJobCounts | null;
  search: string;
  onSearchChange: (v: string) => void;
}) {
  const countFor = (key: StatusFilter): number | null => {
    if (counts === null) return null;
    if (key === "all") return counts.total;
    if (key === "published") return counts.published;
    return counts.closed;
  };

  return (
    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div className="flex flex-wrap gap-2" role="group" aria-label="Filter by status">
        {PILLS.map((pill) => {
          const active = status === pill.key;
          const count = countFor(pill.key);
          return (
            <button
              key={pill.key}
              type="button"
              aria-pressed={active}
              onClick={() => onStatusChange(pill.key)}
              className="flex items-center gap-1.5 rounded-full border px-4 py-2 text-sm font-medium transition-colors"
              style={active
                ? { background: "var(--bj-dash-soft)", borderColor: "var(--bj-dash-primary)", color: "var(--bj-dash-primary)" }
                : { background: "white", borderColor: "var(--bj-dash-border)", color: "var(--bj-dash-ink)" }}
            >
              {pill.label}
              {count !== null && (
                <span
                  className="rounded-full px-1.5 py-0.5 text-xs"
                  style={active ? { background: "white", color: "var(--bj-dash-primary)" } : { background: "var(--bj-dash-canvas)", color: "var(--bj-dash-muted)" }}
                >
                  {count}
                </span>
              )}
            </button>
          );
        })}
      </div>

      <label className="relative w-full sm:w-72">
        <span className="sr-only">Search job titles or skills</span>
        <SearchIcon className="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-[var(--bj-dash-muted)]" />
        <input
          type="search"
          value={search}
          onChange={(e) => onSearchChange(e.target.value)}
          placeholder="Search job titles or skills…"
          className="h-11 w-full rounded-full border bg-white pl-10 pr-4 text-sm outline-none focus:ring-2 focus:ring-[var(--bj-dash-focus)]/30 focus:border-[var(--bj-dash-focus)]"
          style={{ borderColor: "var(--bj-dash-border)", color: "var(--bj-dash-ink)" }}
        />
      </label>
    </div>
  );
}
