import Link from "next/link";
import type { DashboardData } from "@/lib/employer";
import { ChevronRightIcon } from "@/components/employer/icons";

/** "Data Engineer" → "DE"; falls back to the first two letters if there's only one word. */
function initials(title: string): string {
  const words = title.trim().split(/\s+/).filter(Boolean);
  if (words.length === 0) return "—";
  if (words.length === 1) return words[0].slice(0, 2).toUpperCase();
  return (words[0][0] + words[1][0]).toUpperCase();
}

export function OpenRoles({ pipeline }: { pipeline: DashboardData["pipeline"] }) {
  return (
    <div className="flex h-full flex-col rounded-[var(--bj-dash-radius)] border bg-white p-5 sm:p-6" style={{ borderColor: "var(--bj-dash-border)" }}>
      <div className="flex items-center justify-between gap-3">
        <div className="flex items-center gap-2.5">
          <h2 className="text-base font-semibold" style={{ color: "var(--bj-dash-ink)" }}>Open roles</h2>
          <span
            className="rounded-full px-2.5 py-0.5 text-xs font-medium"
            style={{ background: "var(--bj-dash-soft)", color: "var(--bj-dash-primary)" }}
          >
            {pipeline.length} {pipeline.length === 1 ? "role" : "roles"}
          </span>
        </div>
        <Link href="/employer/jobs" className="flex items-center gap-1 text-sm font-medium" style={{ color: "var(--bj-dash-primary)" }}>
          View all roles ↗
        </Link>
      </div>

      {pipeline.length === 0 ? (
        <p className="mt-6 text-sm" style={{ color: "var(--bj-dash-muted)" }}>
          No published roles yet.{" "}
          <Link href="/employer/jobs/new" className="font-semibold" style={{ color: "var(--bj-dash-primary)" }}>
            Post one
          </Link>{" "}
          to start filling this in.
        </p>
      ) : (
        <ul className="mt-4 flex-1 space-y-1">
          {pipeline.map((job) => {
            const total = Object.values(job.stage_counts).reduce((a, b) => a + b, 0);
            return (
              <li key={job.id}>
                <Link
                  href={`/employer/jobs/${job.id}`}
                  className="flex items-center gap-3 rounded-xl px-2 py-3 transition-colors hover:bg-[var(--bj-dash-soft)]/50 sm:gap-4"
                >
                  <span
                    className="grid size-10 shrink-0 place-items-center rounded-xl text-sm font-semibold"
                    style={{ background: "var(--bj-dash-soft)", color: "var(--bj-dash-primary)" }}
                  >
                    {initials(job.title)}
                  </span>
                  <span className="min-w-0 flex-1 truncate text-sm font-medium" style={{ color: "var(--bj-dash-ink)" }}>
                    {job.title}
                  </span>
                  <span className="hidden shrink-0 text-sm sm:block" style={{ color: "var(--bj-dash-muted)" }}>
                    {total} {total === 1 ? "candidate" : "candidates"} in pipeline
                  </span>
                  <span
                    className="hidden shrink-0 items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium sm:flex"
                    style={{ borderColor: "var(--bj-dash-primary)", color: "var(--bj-dash-primary)" }}
                  >
                    <span className="size-1.5 rounded-full" style={{ background: "var(--bj-dash-primary)" }} />
                    Hiring
                  </span>
                  <span
                    className="grid size-8 shrink-0 place-items-center rounded-full border"
                    style={{ borderColor: "var(--bj-dash-border)", color: "var(--bj-dash-muted)" }}
                  >
                    <ChevronRightIcon className="size-4" />
                  </span>
                </Link>
              </li>
            );
          })}
        </ul>
      )}
    </div>
  );
}
