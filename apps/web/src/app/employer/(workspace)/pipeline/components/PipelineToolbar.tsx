"use client";

import { SearchIcon } from "@/components/employer/icons";

export type ViewMode = "list" | "board";

export function PipelineToolbar({
  search,
  onSearchChange,
  jobId,
  onJobChange,
  jobs,
  view,
  onViewChange,
}: {
  search: string;
  onSearchChange: (v: string) => void;
  jobId: number | "all";
  onJobChange: (v: number | "all") => void;
  jobs: { id: number; title: string }[];
  view: ViewMode;
  onViewChange: (v: ViewMode) => void;
}) {
  return (
    <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
      <label className="relative flex-1">
        <span className="sr-only">Search candidates</span>
        <SearchIcon className="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-[var(--bj-dash-muted)]" />
        <input
          type="search"
          value={search}
          onChange={(e) => onSearchChange(e.target.value)}
          placeholder="Search candidates…"
          className="h-11 w-full rounded-full border bg-white pl-10 pr-4 text-sm outline-none focus:border-[var(--bj-dash-focus)] focus:ring-2 focus:ring-[var(--bj-dash-focus)]/30"
          style={{ borderColor: "var(--bj-dash-border)", color: "var(--bj-dash-ink)" }}
        />
      </label>

      {/* Real job identifiers, not titles — several jobs can share a title. */}
      <select
        value={jobId}
        onChange={(e) => onJobChange(e.target.value === "all" ? "all" : Number(e.target.value))}
        aria-label="Filter by role"
        className="h-11 rounded-full border bg-white px-4 text-sm outline-none focus:border-[var(--bj-dash-focus)] focus:ring-2 focus:ring-[var(--bj-dash-focus)]/30"
        style={{ borderColor: "var(--bj-dash-border)", color: "var(--bj-dash-ink)" }}
      >
        <option value="all">All roles</option>
        {jobs.map((j) => (
          <option key={j.id} value={j.id}>{j.title}</option>
        ))}
      </select>

      <div className="flex rounded-full border p-1" style={{ borderColor: "var(--bj-dash-border)" }} role="group" aria-label="View">
        <button
          type="button"
          aria-pressed={view === "list"}
          onClick={() => onViewChange("list")}
          className="rounded-full px-4 py-1.5 text-sm font-medium transition-colors"
          style={view === "list" ? { background: "var(--bj-dash-primary)", color: "white" } : { color: "var(--bj-dash-ink)" }}
        >
          ☰ List
        </button>
        <button
          type="button"
          aria-pressed={view === "board"}
          onClick={() => onViewChange("board")}
          className="rounded-full px-4 py-1.5 text-sm font-medium transition-colors"
          style={view === "board" ? { background: "var(--bj-dash-primary)", color: "white" } : { color: "var(--bj-dash-ink)" }}
        >
          ▦ Board
        </button>
      </div>
    </div>
  );
}
