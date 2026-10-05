"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import { useWorkspace } from "@/components/employer/EmployerShell";
import { employerApi, nextStage, STAGE_LABELS, type ApplicationRow, type EmployerApplicationsPage as ApplicationsPage } from "@/lib/employer";
import { PipelineHeader } from "./components/PipelineHeader";
import { PipelineToolbar, type ViewMode } from "./components/PipelineToolbar";
import { StageNav, type StageFilter } from "./components/StageNav";
import { ApplicationsTable } from "./components/ApplicationsTable";
import { ApplicationDetails } from "./components/ApplicationDetails";
import { PipelineBoardView } from "./components/PipelineBoardView";
import { Pagination } from "../jobs/components/Pagination";

/**
 * Pipeline page — PRD-E emerald/ivory redesign (approved kit, Sept 2026).
 *
 * List and Board are two views over the SAME fetch (workspace + role +
 * search, board always unstaged since showing every stage at once is its
 * whole point) and the SAME transition action — switching views never
 * mutates anything or clears a filter. Only List additionally narrows by
 * the left-nav stage selection and paginates a single stage's rows; Board
 * is capped at the endpoint's first page (25) across the current role+
 * search scope, same practical limit the pre-redesign board already had
 * (it looped per job at 25 each) — noted here since a workspace with far
 * more than 25 matching applications would show an incomplete board.
 */
export default function PipelineBoardPage() {
  const { workspace } = useWorkspace();
  const canAdvance = workspace.my_role === "owner" || workspace.my_role === "recruiter";

  const [jobs, setJobs] = useState<{ id: number; title: string }[]>([]);
  const [jobId, setJobId] = useState<number | "all">("all");
  const [searchInput, setSearchInput] = useState("");
  const [search, setSearch] = useState("");
  const [stage, setStage] = useState<StageFilter>("all");
  const [view, setView] = useState<ViewMode>("list");
  const [page, setPage] = useState(1);

  const [listData, setListData] = useState<ApplicationsPage | null>(null);
  const [boardData, setBoardData] = useState<ApplicationsPage | null>(null);
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [busyId, setBusyId] = useState<number | null>(null);
  const [failed, setFailed] = useState(false);
  const [notice, setNotice] = useState<string | null>(null);

  const detailsCloseRef = useRef<HTMLButtonElement>(null);
  const openedFromRef = useRef<HTMLElement | null>(null);

  useEffect(() => {
    employerApi.jobs(workspace.id)
      .then((r) => setJobs(r.data.map((j) => ({ id: j.id, title: j.title }))))
      .catch(() => setJobs([]));
  }, [workspace.id]);

  useEffect(() => {
    const t = setTimeout(() => setSearch(searchInput.trim()), 350);
    return () => clearTimeout(t);
  }, [searchInput]);

  const firstRunRef = useRef(true);
  useEffect(() => {
    if (firstRunRef.current) { firstRunRef.current = false; return; }
    setPage(1);
  }, [jobId, search, stage]);

  const jobIdNum = jobId === "all" ? undefined : jobId;

  // List — respects the selected stage and paginates.
  useEffect(() => {
    if (view !== "list") return;
    let cancelled = false;
    employerApi.workspaceApplications(workspace.id, {
      jobId: jobIdNum,
      stage: stage === "all" ? undefined : stage,
      search: search || undefined,
      page,
    })
      .then((res) => { if (!cancelled) setListData(res); })
      .catch(() => { if (!cancelled) setFailed(true); });
    return () => { cancelled = true; };
  }, [workspace.id, view, jobIdNum, stage, search, page]);

  // Board — every stage at once, same role/search scope, unpaginated (see file header).
  useEffect(() => {
    if (view !== "board") return;
    let cancelled = false;
    employerApi.workspaceApplications(workspace.id, { jobId: jobIdNum, search: search || undefined })
      .then((res) => { if (!cancelled) setBoardData(res); })
      .catch(() => { if (!cancelled) setFailed(true); });
    return () => { cancelled = true; };
  }, [workspace.id, view, jobIdNum, search]);

  // The counts driving the header + stage nav always come from whichever
  // view is active and loaded — both compute them the same way server-side
  // (job_id + search, stage NOT applied), so either is equally authoritative.
  const counts = (view === "list" ? listData : boardData)?.counts ?? null;

  const rows: ApplicationRow[] = view === "list" ? (listData?.data ?? []) : (boardData?.data ?? []);

  // If the selected application drops out of view (filtered away, moved to
  // a stage no longer shown), clear selection instead of showing a stale
  // record for something no longer visible.
  useEffect(() => {
    if (selectedId !== null && !rows.some((r) => r.id === selectedId)) {
      setSelectedId(null);
    }
  }, [rows, selectedId]);

  const selected = rows.find((r) => r.id === selectedId) ?? null;

  const selectApplication = useCallback((row: ApplicationRow, sourceEl?: HTMLElement) => {
    openedFromRef.current = sourceEl ?? document.activeElement as HTMLElement;
    setSelectedId(row.id);
  }, []);

  const closeDetails = useCallback(() => {
    setSelectedId(null);
    openedFromRef.current?.focus?.();
  }, []);

  async function advance(row: ApplicationRow) {
    // Re-derived from the row passed in (not a captured closure over some
    // earlier render), so this always targets the stage the button actually
    // showed when clicked.
    const next = nextStage(row.stage);
    if (!next) return;

    setBusyId(row.id);
    setNotice(null);
    try {
      await employerApi.moveStage(workspace.id, row.job_id, row.id, next);
      setNotice(`${row.candidate?.name ?? "Application"} moved to ${STAGE_LABELS[next] ?? next}.`);
      // Reconcile with authoritative data rather than assuming the optimistic
      // move landed exactly as sent — a conflicting concurrent edit refreshes
      // instead of silently overwriting it.
      if (view === "list") {
        const res = await employerApi.workspaceApplications(workspace.id, {
          jobId: jobIdNum, stage: stage === "all" ? undefined : stage, search: search || undefined, page,
        });
        setListData(res);
      } else {
        const res = await employerApi.workspaceApplications(workspace.id, { jobId: jobIdNum, search: search || undefined });
        setBoardData(res);
      }
    } catch {
      setNotice("Could not move that application — try again.");
    } finally {
      setBusyId(null);
    }
  }

  const heading = stage === "all" ? "All applications" : (STAGE_LABELS[stage] ?? stage);
  const roleTitleFor = (row: ApplicationRow | null) => jobs.find((j) => j.id === row?.job_id)?.title ?? "—";

  if (failed) {
    return (
      <p className="rounded-[var(--bj-dash-radius)] border bg-white p-8 text-sm" style={{ borderColor: "var(--bj-dash-border)", color: "var(--bj-dash-muted)" }}>
        The pipeline could not load. Refresh to try again.
      </p>
    );
  }

  return (
    <div className="flex h-full flex-col space-y-5 pb-6">
      <PipelineHeader counts={counts} search={search} />

      <PipelineToolbar
        search={searchInput}
        onSearchChange={setSearchInput}
        jobId={jobId}
        onJobChange={setJobId}
        jobs={jobs}
        view={view}
        onViewChange={setView}
      />

      {notice && (
        <p className="rounded-xl px-4 py-2.5 text-sm" style={{ background: "var(--bj-dash-soft)", color: "var(--bj-dash-primary)" }} role="status">
          {notice}
        </p>
      )}

      <div
        className="grid flex-1 overflow-hidden rounded-[var(--bj-dash-radius)] border bg-white lg:grid-cols-[210px_1fr_340px]"
        style={{ borderColor: "var(--bj-dash-border)", minHeight: 520 }}
      >
        <div className="hidden border-r lg:block" style={{ borderColor: "var(--bj-dash-border)" }}>
          <StageNav stage={stage} onStageChange={setStage} counts={counts} />
        </div>

        <div className="min-w-0 border-r lg:border-r" style={{ borderColor: "var(--bj-dash-border)" }}>
          {view === "list" ? (
            <div className="flex h-full flex-col">
              <ApplicationsTable
                heading={heading}
                count={listData?.meta.total ?? 0}
                rows={rows}
                jobs={jobs}
                loading={listData === null}
                selectedId={selectedId}
                onSelect={(row) => selectApplication(row)}
                onAdvance={advance}
                busyId={busyId}
                canAdvance={canAdvance}
              />
              {listData && (
                <div className="border-t px-4 py-2" style={{ borderColor: "var(--bj-dash-border)" }}>
                  <p className="text-center text-xs" style={{ color: "var(--bj-dash-muted)" }}>
                    {listData.meta.total > 0
                      ? search || stage !== "all"
                        ? `Showing ${listData.meta.from ?? 0}–${listData.meta.to ?? 0} of ${listData.meta.total} matching applications.`
                        : `Showing all ${listData.meta.total} applications.`
                      : "No applications match this view."}
                  </p>
                  <Pagination currentPage={listData.meta.current_page} lastPage={listData.meta.last_page} onChange={setPage} label="Applications pages" />
                </div>
              )}
            </div>
          ) : boardData === null ? (
            <div className="grid h-full place-items-center">
              <span className="shimmer h-10 w-10 rounded-full" />
            </div>
          ) : (
            <PipelineBoardView rows={rows} jobs={jobs} onAdvance={advance} busyId={busyId} canAdvance={canAdvance} />
          )}
        </div>

        <div className="hidden lg:block">
          <ApplicationDetails
            application={selected}
            roleTitle={roleTitleFor(selected)}
            onClose={closeDetails}
            onAdvance={advance}
            busy={selected !== null && busyId === selected.id}
            canAdvance={canAdvance}
            closeButtonRef={detailsCloseRef}
          />
        </div>
      </div>

      {/* Narrower than the three-pane desktop layout: the inspector becomes a drawer instead of squeezing the table. */}
      {selected && (
        <div className="fixed inset-0 z-50 lg:hidden">
          <button type="button" aria-label="Close" className="absolute inset-0 bg-black/30" onClick={closeDetails} />
          <div
            role="dialog"
            aria-modal="true"
            aria-label="Application details"
            className="absolute inset-x-0 bottom-0 max-h-[85vh] overflow-y-auto rounded-t-2xl bg-white"
            onKeyDown={(e) => { if (e.key === "Escape") closeDetails(); }}
          >
            <ApplicationDetails
              application={selected}
              roleTitle={roleTitleFor(selected)}
              onClose={closeDetails}
              onAdvance={advance}
              busy={busyId === selected.id}
              canAdvance={canAdvance}
            />
          </div>
        </div>
      )}
    </div>
  );
}
