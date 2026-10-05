"use client";

import Link from "next/link";
import { useState } from "react";
import { ChevronDownIcon } from "@/components/employer/icons";

/**
 * "Welcome back, {name}." + the serif "Hiring overview" title, with the
 * job/scope filters at the right on desktop. The job filter is real — it
 * lists this workspace's actual published roles — but there is no backend
 * support for re-aggregating the dashboard's own numbers by job or by a
 * chosen date range (DashboardController returns one fixed all-time/last-7-
 * day/last-14-day mix, not filterable). Picking a specific role therefore
 * navigates to that role's own real page rather than pretending to filter
 * this overview in place; the date control is an honest static label, not a
 * dropdown with nothing behind it.
 */
export function DashboardHeading({
  displayName,
  roles,
}: {
  displayName: string;
  roles: { id: number; title: string }[];
}) {
  const [jobsOpen, setJobsOpen] = useState(false);

  return (
    <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <p className="text-sm" style={{ color: "var(--bj-dash-muted)" }}>
          Welcome back, <span style={{ color: "var(--bj-dash-ink)" }}>{displayName}</span>.
        </p>
        <h1 className="bj-dash-serif mt-1" style={{ fontSize: "var(--bj-dash-title-size)", color: "var(--bj-dash-ink)" }}>
          Hiring overview
        </h1>
      </div>

      <div className="flex shrink-0 items-center gap-2.5">
        <div className="relative">
          <button
            type="button"
            onClick={() => setJobsOpen((o) => !o)}
            aria-expanded={jobsOpen}
            className="flex items-center gap-2 rounded-full border bg-white px-4 py-2 text-sm font-medium"
            style={{ borderColor: "var(--bj-dash-border)", color: "var(--bj-dash-ink)" }}
          >
            All jobs
            <ChevronDownIcon className="size-3.5 text-[var(--bj-dash-muted)]" />
          </button>
          {jobsOpen && (
            <>
              <button type="button" aria-label="Close" className="fixed inset-0 z-40 cursor-default" onClick={() => setJobsOpen(false)} />
              <div
                className="absolute right-0 top-full z-50 mt-2 w-64 overflow-hidden rounded-2xl border bg-white py-1.5 shadow-lg"
                style={{ borderColor: "var(--bj-dash-border)" }}
              >
                <p className="px-4 pb-1 pt-1.5 font-mono text-[10px] uppercase tracking-widest" style={{ color: "var(--bj-dash-muted)" }}>
                  Jump to a role
                </p>
                {roles.length === 0 ? (
                  <p className="px-4 py-2 text-sm" style={{ color: "var(--bj-dash-muted)" }}>No published roles yet.</p>
                ) : (
                  roles.map((role) => (
                    <Link
                      key={role.id}
                      href={`/employer/jobs/${role.id}`}
                      className="block truncate px-4 py-2 text-sm hover:bg-[var(--bj-dash-soft)]"
                      style={{ color: "var(--bj-dash-ink)" }}
                    >
                      {role.title}
                    </Link>
                  ))
                )}
                <Link
                  href="/employer/jobs"
                  className="mt-1 block border-t px-4 py-2 text-sm font-semibold"
                  style={{ borderColor: "var(--bj-dash-border)", color: "var(--bj-dash-primary)" }}
                >
                  View all roles →
                </Link>
              </div>
            </>
          )}
        </div>

        {/* Honest static scope label — see file header. Every number below is
            all-time except "graded, last 7 days", which is labelled as such
            on its own card rather than implied by a global selector here. */}
        <span
          className="rounded-full border bg-white px-4 py-2 text-sm font-medium"
          style={{ borderColor: "var(--bj-dash-border)", color: "var(--bj-dash-muted)" }}
        >
          All time
        </span>
      </div>
    </div>
  );
}
