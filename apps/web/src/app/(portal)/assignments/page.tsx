"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { apiJson } from "@/lib/api";

type AssignmentRow = {
  lesson_id: number;
  lesson: string | null;
  title: string;
  max_points: number;
  status: "open" | "submitted" | "graded";
  score: number | null;
  submitted_at: string | null;
};

const STATUS_META: Record<AssignmentRow["status"], { label: string; cls: string }> = {
  open: { label: "To do", cls: "bg-sky text-deep" },
  submitted: { label: "Submitted — being graded", cls: "bg-paper text-muted" },
  graded: { label: "Graded", cls: "bg-verify-bg text-verify" },
};

export default function AssignmentsPage() {
  const [rows, setRows] = useState<AssignmentRow[] | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    apiJson<{ data: AssignmentRow[] }>("/api/v1/me/assignments")
      .then((r) => setRows(r.data))
      .catch(() => setRows(null))
      .finally(() => setLoading(false));
  }, []);

  if (loading) return <div className="mx-auto max-w-2xl"><div className="shimmer h-64 rounded-[14px]" /></div>;

  const todo = rows?.filter((r) => r.status === "open") ?? [];

  return (
    <div className="mx-auto max-w-2xl">
      <p className="kicker text-trust">Assessments</p>
      <h1 className="display mt-2 text-3xl text-ink">Assignments</h1>
      <p className="mt-1 text-sm text-muted">
        Real work, graded against a rubric — with written feedback from your trainer.
      </p>

      {todo.length > 0 && (
        <p className="mt-4 rounded-[10px] bg-sky px-3 py-2 text-sm text-deep">
          {todo.length} assignment{todo.length === 1 ? "" : "s"} waiting for your submission.
        </p>
      )}

      {rows === null || rows.length === 0 ? (
        <div className="mt-6 rounded-2xl border border-dashed border-line bg-white p-10 text-center">
          <p className="text-sm font-semibold text-ink">No assignments yet</p>
          <p className="mt-1 text-sm text-muted">Your trainer posts them here as the course progresses.</p>
        </div>
      ) : (
        <div className="mt-6 space-y-2">
          {rows.map((r) => (
            <Link
              key={r.lesson_id}
              href={`/assignments/${r.lesson_id}`}
              className="flex items-center justify-between gap-3 rounded-[14px] border border-line bg-white p-4 hover:border-trust"
            >
              <span className="min-w-0">
                <span className="block truncate text-sm font-semibold text-ink">{r.title}</span>
                <span className="block truncate text-xs text-muted">{r.lesson ?? ""}</span>
              </span>
              <span className="flex shrink-0 items-center gap-2">
                {r.status === "graded" && r.score !== null && (
                  <span className="mono text-sm font-bold text-ink">{r.score}/{r.max_points}</span>
                )}
                <span className={`rounded-full px-2.5 py-1 text-[11px] font-semibold ${STATUS_META[r.status].cls}`}>
                  {STATUS_META[r.status].label}
                </span>
              </span>
            </Link>
          ))}
        </div>
      )}
    </div>
  );
}
