"use client";

import { useEffect, useMemo, useRef, useState } from "react";
import { useWorkspace } from "@/components/employer/EmployerShell";
import { TwoPanelBanner } from "@/components/employer/TwoPanelBanner";
import { employerApi, type EmployerJobCounts, type EmployerJobRow, type EmployerJobsPage as JobsPageData } from "@/lib/employer";
import { JobsHeader } from "./components/JobsHeader";
import { JobsSummary } from "./components/JobsSummary";
import { JobsFilters, type StatusFilter } from "./components/JobsFilters";
import { PublishedRoles } from "./components/PublishedRoles";
import { ClosedRoles } from "./components/ClosedRoles";
import { Pagination } from "./components/Pagination";

/**
 * Jobs page — PRD-E emerald/ivory redesign (approved kit, Sept 2026).
 *
 * "All jobs" needs a published-cards section AND a closed-roles table at
 * once, but the list endpoint only paginates one status at a time — so
 * "All jobs" runs two independent, separately-paginated requests (one per
 * section) rather than one combined page. See IMPLEMENT-JOBS-PAGE.md §8:
 * "independent group pagination if that is how the API works." `counts`
 * comes back workspace-wide on every response regardless of filter, so
 * either request can supply it.
 */
export default function EmployerJobsPage() {
  const { workspace } = useWorkspace();

  const [status, setStatus] = useState<StatusFilter>("all");
  const [searchInput, setSearchInput] = useState("");
  const [search, setSearch] = useState("");
  const [publishedPage, setPublishedPage] = useState(1);
  const [closedPage, setClosedPage] = useState(1);

  const [counts, setCounts] = useState<EmployerJobCounts | null>(null);
  const [published, setPublished] = useState<JobsPageData | null>(null);
  const [closed, setClosed] = useState<JobsPageData | null>(null);
  const [otherJobs, setOtherJobs] = useState<EmployerJobRow[] | null>(null);
  const [failed, setFailed] = useState(false);

  // Debounce the search box — one request per pause in typing, not per keystroke.
  useEffect(() => {
    const t = setTimeout(() => setSearch(searchInput.trim()), 350);
    return () => clearTimeout(t);
  }, [searchInput]);

  // A changed filter or search term invalidates whatever page number was in
  // effect — page 3 of an unrelated result set is not page 3 of this one.
  const firstRunRef = useRef(true);
  useEffect(() => {
    if (firstRunRef.current) { firstRunRef.current = false; return; }
    setPublishedPage(1);
    setClosedPage(1);
  }, [status, search]);

  const needsPublished = status === "all" || status === "published";
  const needsClosed = status === "all" || status === "closed";

  useEffect(() => {
    if (!needsPublished) { setPublished(null); return; }
    let cancelled = false;
    employerApi.jobs(workspace.id, { status: "published", search: search || undefined, page: publishedPage })
      .then((res) => { if (!cancelled) { setPublished(res); setCounts(res.counts); } })
      .catch(() => { if (!cancelled) setFailed(true); });
    return () => { cancelled = true; };
  }, [workspace.id, needsPublished, search, publishedPage]);

  useEffect(() => {
    if (!needsClosed) { setClosed(null); return; }
    let cancelled = false;
    employerApi.jobs(workspace.id, { status: "closed", search: search || undefined, page: closedPage })
      .then((res) => { if (!cancelled) { setClosed(res); setCounts(res.counts); } })
      .catch(() => { if (!cancelled) setFailed(true); });
    return () => { cancelled = true; };
  }, [workspace.id, needsClosed, search, closedPage]);

  // Draft/paused jobs — not pictured in the approved reference (which only
  // has published + closed pills), but real statuses this workspace may
  // actually have. Fetched only when they exist, so the common case (no
  // draft/paused jobs) renders exactly like the reference with no extra
  // section. See IMPLEMENT-JOBS-PAGE.md: "keep additional real statuses...
  // available... rather than dropping them from All jobs."
  const hasOtherStatuses = counts !== null && counts.total > counts.published + counts.closed;
  useEffect(() => {
    if (status !== "all" || !hasOtherStatuses) { setOtherJobs(null); return; }
    let cancelled = false;
    Promise.all([
      employerApi.jobs(workspace.id, { status: "draft", search: search || undefined }),
      employerApi.jobs(workspace.id, { status: "paused", search: search || undefined }),
    ])
      .then(([draft, paused]) => { if (!cancelled) setOtherJobs([...draft.data, ...paused.data]); })
      .catch(() => { if (!cancelled) setOtherJobs([]); });
    return () => { cancelled = true; };
  }, [workspace.id, status, hasOtherStatuses, search]);

  const resultsSummary = useMemo(() => {
    if (status === "published" && published) {
      return search
        ? `Showing ${published.meta.from ?? 0}–${published.meta.to ?? 0} of ${published.meta.total} published jobs matching "${search}".`
        : `Showing ${published.meta.from ?? 0}–${published.meta.to ?? 0} of ${published.meta.total} published jobs.`;
    }
    if (status === "closed" && closed) {
      return search
        ? `Showing ${closed.meta.from ?? 0}–${closed.meta.to ?? 0} of ${closed.meta.total} closed jobs matching "${search}".`
        : `Showing ${closed.meta.from ?? 0}–${closed.meta.to ?? 0} of ${closed.meta.total} closed jobs.`;
    }
    if (published && closed) {
      const total = published.meta.total + closed.meta.total;
      if (search) return `Showing ${total} matching job${total === 1 ? "" : "s"} for "${search}".`;
      if (counts && total === counts.total) return `Showing all ${counts.total} job${counts.total === 1 ? "" : "s"}.`;
      return `Showing ${total} job${total === 1 ? "" : "s"}.`;
    }
    return null;
  }, [status, published, closed, search, counts]);

  const nothingVisible =
    search !== "" &&
    (!needsPublished || published?.data.length === 0) &&
    (!needsClosed || closed?.data.length === 0) &&
    (published !== null || !needsPublished) &&
    (closed !== null || !needsClosed);

  if (failed) {
    return (
      <p className="rounded-[var(--bj-dash-radius)] border bg-white p-8 text-sm" style={{ borderColor: "var(--bj-dash-border)", color: "var(--bj-dash-muted)" }}>
        The jobs page could not load. Refresh to try again.
      </p>
    );
  }

  return (
    <div className="space-y-5 pb-6">
      <JobsHeader />

      <TwoPanelBanner
        topLine="Clear roles."
        italicLine="Great possibilities."
        subLine="Every published role has its own interview and grading rubric."
        imageSrc="/img/employer/jobs-planning-banner.png"
        imageAlt="Two colleagues reviewing a job description together"
        objectPosition="50% 20%"
      />

      <JobsSummary counts={counts} />

      <JobsFilters status={status} onStatusChange={setStatus} counts={counts} search={searchInput} onSearchChange={setSearchInput} />

      {nothingVisible && (
        <div className="rounded-[var(--bj-dash-radius)] border bg-white p-8 text-center" style={{ borderColor: "var(--bj-dash-border)" }}>
          <p className="text-sm" style={{ color: "var(--bj-dash-ink)" }}>
            No jobs match &quot;{search}&quot;.
          </p>
          <button
            type="button"
            onClick={() => setSearchInput("")}
            className="mt-3 rounded-full border px-4 py-2 text-sm font-medium"
            style={{ borderColor: "var(--bj-dash-primary)", color: "var(--bj-dash-primary)" }}
          >
            Clear search
          </button>
        </div>
      )}

      {!nothingVisible && needsPublished && (
        <>
          <PublishedRoles
            jobs={published?.data ?? []}
            count={published?.meta.total ?? counts?.published ?? 0}
            loading={published === null}
          />
          {published && (
            <Pagination
              currentPage={published.meta.current_page}
              lastPage={published.meta.last_page}
              onChange={setPublishedPage}
              label="Published roles pages"
            />
          )}
        </>
      )}

      {!nothingVisible && status === "all" && otherJobs !== null && otherJobs.length > 0 && (
        <div className="rounded-[var(--bj-dash-radius)] border bg-white p-5 sm:p-6" style={{ borderColor: "var(--bj-dash-border)" }}>
          <div className="flex items-center gap-2.5">
            <h2 className="text-lg font-semibold" style={{ color: "var(--bj-dash-ink)" }}>Other roles</h2>
            <span className="rounded-full px-2.5 py-0.5 text-xs font-medium" style={{ background: "#fdf1dd", color: "#a5720a" }}>
              {otherJobs.length}
            </span>
            <span className="text-xs" style={{ color: "var(--bj-dash-muted)" }}>Draft or paused — not visible to applicants</span>
          </div>
          <ul className="mt-4 space-y-2">
            {otherJobs.map((job) => (
              <li key={job.id} className="flex items-center justify-between gap-3 rounded-xl border p-3" style={{ borderColor: "var(--bj-dash-border)" }}>
                <span className="text-sm font-medium" style={{ color: "var(--bj-dash-ink)" }}>{job.title}</span>
                <span className="flex items-center gap-2">
                  <span className="rounded-full px-2.5 py-1 text-xs font-medium capitalize" style={{ background: "#fdf1dd", color: "#a5720a" }}>
                    {job.status}
                  </span>
                  <a href={`/employer/jobs/${job.id}`} className="text-sm font-semibold" style={{ color: "var(--bj-dash-primary)" }}>
                    View details
                  </a>
                </span>
              </li>
            ))}
          </ul>
        </div>
      )}

      {!nothingVisible && needsClosed && (
        <>
          <ClosedRoles
            jobs={closed?.data ?? []}
            count={closed?.meta.total ?? counts?.closed ?? 0}
            loading={closed === null}
          />
          {closed && (
            <Pagination
              currentPage={closed.meta.current_page}
              lastPage={closed.meta.last_page}
              onChange={setClosedPage}
              label="Closed roles pages"
            />
          )}
        </>
      )}

      {!nothingVisible && resultsSummary && (
        <p className="text-center text-sm" style={{ color: "var(--bj-dash-muted)" }}>{resultsSummary}</p>
      )}
    </div>
  );
}
