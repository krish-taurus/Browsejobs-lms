"use client";

import Link from "next/link";
import { useCallback, useEffect, useMemo, useState } from "react";
import { motion } from "framer-motion";
import { useWorkspace } from "@/components/employer/EmployerShell";
import { EASE, Label, PageHead, Pill, Skeleton, Tile } from "@/components/employer/ui";
import { TRUST } from "@/components/employer/charts";
import { employerApi, nextStage, STAGE_LABELS, type ApplicationRow } from "@/lib/employer";

/**
 * The ATS board (PRD-E F13): every candidate across every JD, in columns by
 * stage, the way a hiring team actually thinks about a pipeline.
 *
 * Colour is quality, not stage — a candidate's band never changes because
 * they moved column. Green at 80+, amber 60–79, red below or rejected.
 * Because colour alone must never carry meaning, every card also prints the
 * score and names its band in text.
 */

type BoardRow = ApplicationRow & { jobId: number };

const COLUMNS = ["applied", "graded", "shortlisted", "l1", "l2", "human_round", "offer", "hired"] as const;

type Band = { key: string; label: string; color: string; bg: string; border: string };

const BANDS: Record<string, Band> = {
  strong: { key: "strong", label: "Strong", color: "#5fd6a6", bg: "#0da06e1f", border: "#0da06e55" },
  fair: { key: "fair", label: "Fair", color: "#e8bf63", bg: "#b07c001f", border: "#b07c0055" },
  weak: { key: "weak", label: "Below bar", color: "#f2a1a7", bg: "#e055611f", border: "#e0556155" },
  ungraded: { key: "ungraded", label: "Not scored", color: "rgba(244,247,251,0.5)", bg: "rgba(255,255,255,0.04)", border: "rgba(255,255,255,0.10)" },
};

/** Quality band. Rejected always reads red regardless of score. */
function bandFor(row: BoardRow): Band {
  if (row.stage === "rejected" || row.stage === "withdrawn") return BANDS.weak;
  if (row.mock_score === null || row.mock_score === undefined) return BANDS.ungraded;
  if (row.mock_score >= 80) return BANDS.strong;
  if (row.mock_score >= 60) return BANDS.fair;
  return BANDS.weak;
}

export default function PipelineBoardPage() {
  const { workspace } = useWorkspace();
  const [jobs, setJobs] = useState<{ id: number; title: string }[]>([]);
  const [jobId, setJobId] = useState<number | "all">("all");
  // ApplicationRow carries no job id, but every stage move needs one, so the
  // owning job is attached as rows are flattened across roles.
  const [rows, setRows] = useState<BoardRow[] | null>(null);
  const [busy, setBusy] = useState<number | null>(null);
  const [failed, setFailed] = useState(false);

  useEffect(() => {
    employerApi
      .jobs(workspace.id)
      .then((r) => setJobs(r.data.map((j) => ({ id: j.id, title: j.title }))))
      .catch(() => setJobs([]));
  }, [workspace.id]);

  const load = useCallback(() => {
    setRows(null);
    const targets = jobId === "all" ? jobs.map((j) => j.id) : [jobId];
    if (targets.length === 0) {
      setRows([]);
      return;
    }
    Promise.all(
      targets.map((id) =>
        employerApi
          .applications(workspace.id, id)
          .then((r) => r.data.map((row): BoardRow => ({ ...row, jobId: id })))
          .catch((): BoardRow[] => []),
      ),
    )
      .then((all) => setRows(all.flat()))
      .catch(() => setFailed(true));
  }, [workspace.id, jobId, jobs]);

  useEffect(load, [load]);

  const byStage = useMemo(() => {
    const map: Record<string, BoardRow[]> = {};
    for (const c of COLUMNS) map[c] = [];
    for (const r of rows ?? []) {
      if (map[r.stage]) map[r.stage].push(r);
    }
    // Strongest first inside every column — the board should answer
    // "who do I look at next" without sorting by hand.
    for (const c of COLUMNS) {
      map[c].sort((a, b) => (b.mock_score ?? -1) - (a.mock_score ?? -1));
    }
    return map;
  }, [rows]);

  const counts = useMemo(() => {
    const live = (rows ?? []).filter((r) => r.stage !== "rejected" && r.stage !== "withdrawn");
    return {
      strong: live.filter((r) => (r.mock_score ?? -1) >= 80).length,
      fair: live.filter((r) => (r.mock_score ?? -1) >= 60 && (r.mock_score ?? -1) < 80).length,
      weak: live.filter((r) => r.mock_score !== null && (r.mock_score ?? 0) < 60).length,
      rejected: (rows ?? []).filter((r) => r.stage === "rejected").length,
    };
  }, [rows]);

  async function advance(row: BoardRow) {
    const target = nextStage(row.stage);
    if (!target) return;
    setBusy(row.id);
    try {
      await employerApi.moveStage(workspace.id, row.jobId, row.id, target);
      load();
    } finally {
      setBusy(null);
    }
  }

  return (
    <div className="w-full min-w-0 space-y-6 pb-10">
      <PageHead
        kicker="Pipeline"
        title="Every candidate,"
        highlight="one board."
        sub="Columns are where a candidate is. Colour is how strong they are — it never changes because they moved."
      />

      <div className="flex flex-wrap items-center gap-3">
        <select
          value={jobId}
          onChange={(e) => setJobId(e.target.value === "all" ? "all" : Number(e.target.value))}
          aria-label="Filter by job"
          className="rounded-2xl border border-white/[0.12] bg-white/[0.05] px-4 py-2.5 text-sm text-white outline-none focus:border-[#4d8ef7] focus:ring-4 focus:ring-[#4d8ef7]/25"
        >
          <option className="text-[#0a1220]" value="all">All roles</option>
          {jobs.map((j) => (
            <option className="text-[#0a1220]" key={j.id} value={j.id}>{j.title}</option>
          ))}
        </select>

        {/* The legend is the accessibility contract: colour never alone. */}
        <div className="flex flex-wrap gap-2">
          {[
            { b: BANDS.strong, n: counts.strong, hint: "80+" },
            { b: BANDS.fair, n: counts.fair, hint: "60–79" },
            { b: BANDS.weak, n: counts.weak, hint: "under 60" },
          ].map(({ b, n, hint }) => (
            <span
              key={b.key}
              className="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-[11px] font-semibold"
              style={{ background: b.bg, borderColor: b.border, color: b.color }}
            >
              <span aria-hidden className="h-1.5 w-1.5 rounded-full" style={{ background: b.color }} />
              {b.label} <span className="font-mono opacity-70">{hint}</span>
              <span className="font-mono">{n}</span>
            </span>
          ))}
        </div>
      </div>

      {failed ? (
        <Tile accent={TRUST} hover={false}>
          <p className="text-sm text-white/60">The board could not load. Refresh to try again.</p>
        </Tile>
      ) : rows === null ? (
        <div className="flex w-full gap-4 overflow-x-auto">
          {[0, 1, 2, 3].map((i) => <Skeleton key={i} className="h-72 w-72 shrink-0" />)}
        </div>
      ) : (
        <div className="w-full overflow-x-auto pb-4">
          <div className="flex w-max gap-4">
          {COLUMNS.map((stage) => {
            const items = byStage[stage] ?? [];
            return (
              <section key={stage} className="w-[288px] shrink-0">
                <div className="flex items-baseline justify-between px-1 pb-2.5">
                  <Label>{STAGE_LABELS[stage] ?? stage}</Label>
                  <span className="font-mono text-[11px] text-white/40">{items.length}</span>
                </div>

                <div
                  className="min-h-[220px] rounded-2xl border border-white/[0.07] p-2"
                  style={{ background: "rgba(255,255,255,0.015)" }}
                >
                  {items.length === 0 ? (
                    <p className="px-2 py-6 text-center text-[12px] text-white/25">Nobody here</p>
                  ) : (
                    <ul className="space-y-2">
                      {items.map((row, i) => {
                        const band = bandFor(row);
                        const target = nextStage(row.stage);
                        return (
                          <motion.li
                            key={row.id}
                            initial={{ opacity: 0, y: 8 }}
                            animate={{ opacity: 1, y: 0 }}
                            transition={{ duration: 0.4, ease: EASE, delay: Math.min(i, 6) * 0.03 }}
                            className="rounded-xl border p-3"
                            style={{ background: band.bg, borderColor: band.border }}
                          >
                            <div className="flex items-start justify-between gap-2">
                              <p className="truncate text-[13px] font-semibold text-white">
                                {row.candidate?.name ?? `Candidate #${row.id}`}
                              </p>
                              <span className="shrink-0 font-mono text-[13px] font-bold" style={{ color: band.color }}>
                                {row.mock_score ?? "—"}
                              </span>
                            </div>

                            {/* Band named in text, so the colour is never the
                                only thing carrying the meaning. */}
                            <p className="mt-1 font-mono text-[10px] uppercase tracking-[0.12em]" style={{ color: band.color }}>
                              {band.label}
                            </p>

                            <div className="mt-2.5 flex items-center gap-2">
                              <Link
                                href={`/employer/jobs/${row.jobId}/candidates/${row.id}`}
                                className="text-[11px] font-medium text-white/50 underline-offset-4 hover:text-white hover:underline"
                              >
                                Open
                              </Link>
                              {target && (
                                <button
                                  onClick={() => advance(row)}
                                  disabled={busy === row.id}
                                  className="rounded-full border border-white/[0.14] px-2.5 py-1 text-[11px] font-medium text-white/70 transition-colors hover:border-white/30 disabled:opacity-40"
                                >
                                  {busy === row.id ? "…" : `→ ${STAGE_LABELS[target] ?? target}`}
                                </button>
                              )}
                            </div>
                          </motion.li>
                        );
                      })}
                    </ul>
                  )}
                </div>
              </section>
            );
          })}
          </div>
        </div>
      )}

      <div className="flex flex-wrap items-center gap-3">
        <Pill tone="verify">{counts.strong} at or above 80</Pill>
        <Pill tone="warn">{counts.rejected} closed</Pill>
        <span className="text-[12px] text-white/40">
          Scores come from the job-specific interview. A candidate with no score has not sat it yet.
        </span>
      </div>
    </div>
  );
}
