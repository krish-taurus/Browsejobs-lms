"use client";

import Link from "next/link";
import { useEffect, useState, type ReactNode } from "react";
import { apiJson } from "@/lib/api";
import { mockPath, type MockKind } from "@/lib/mockKinds";

export type HistoryRow = {
  id: number;
  kind: MockKind;
  role_title: string | null;
  status: "in_progress" | "completed" | "abandoned";
  is_room: boolean;
  overall_score: number | null;
  started_at: string | null;
  completed_at: string | null;
};

const STATUS_LABEL: Record<HistoryRow["status"], string> = {
  in_progress: "In progress",
  completed: "Completed",
  abandoned: "Not scored",
};

function scoreTone(score: number | null): string {
  if (score === null) return "text-muted";
  if (score >= 70) return "text-verify";
  if (score >= 40) return "text-ink";
  return "text-warn";
}

function day(iso: string | null): string {
  return iso ? new Date(iso).toLocaleDateString("en-IN", { day: "numeric", month: "short", year: "numeric" }) : "—";
}

/** One kind's sessions as a table — only that kind, never mixed. */
export function MockHistory({
  kind,
  emptyText,
  empty,
  rows: presetRows,
}: {
  kind: MockKind;
  emptyText: string;
  /** Shown instead of emptyText when there are no sessions. */
  empty?: ReactNode;
  /** Rows supplied by the caller (design previews) — skips the API call. */
  rows?: HistoryRow[];
}) {
  const [rows, setRows] = useState<HistoryRow[] | null>(presetRows ?? null);
  const [failed, setFailed] = useState(false);

  useEffect(() => {
    if (presetRows) {
      setRows(presetRows);
      return;
    }
    apiJson<{ data: { mocks: HistoryRow[] } }>(`/api/v1/me/mocks/history?kind=${kind}`)
      .then((r) => setRows(r.data.mocks))
      .catch(() => setFailed(true));
  }, [kind, presetRows]);

  if (failed) return <p className="mt-3 text-sm text-warn">Couldn&apos;t load your interviews. Refresh to try again.</p>;
  if (rows === null) return <div className="shimmer mt-3 h-32 rounded-[14px]" />;
  if (rows.length === 0) return empty ? <>{empty}</> : <p className="mt-3 text-sm text-muted">{emptyText}</p>;

  return (
    <div className="mt-3 overflow-x-auto rounded-[14px] border border-line bg-white">
      <table className="w-full min-w-[520px] text-left text-sm">
        <thead>
          <tr className="border-b border-line text-xs uppercase tracking-widest text-muted">
            <th className="px-4 py-3 font-semibold">Date</th>
            <th className="px-4 py-3 font-semibold">Role</th>
            <th className="px-4 py-3 font-semibold">Status</th>
            <th className="px-4 py-3 text-right font-semibold">Score</th>
            <th className="px-4 py-3" />
          </tr>
        </thead>
        <tbody>
          {rows.map((m) => {
            const open = m.status === "in_progress";
            return (
              <tr key={m.id} className="border-b border-line last:border-0">
                <td className="mono px-4 py-3 text-ink">{day(m.completed_at ?? m.started_at)}</td>
                <td className="px-4 py-3 text-ink">{m.role_title ?? "—"}</td>
                <td className="px-4 py-3 text-muted">{STATUS_LABEL[m.status]}</td>
                <td className={`mono px-4 py-3 text-right font-semibold ${scoreTone(m.overall_score)}`}>
                  {m.overall_score === null ? "—" : `${m.overall_score}/100`}
                </td>
                <td className="px-4 py-3 text-right">
                  <Link href={mockPath(kind, m.id, open && m.is_room)} className="font-semibold text-trust hover:underline">
                    {open ? "Resume →" : "View →"}
                  </Link>
                </td>
              </tr>
            );
          })}
        </tbody>
      </table>
    </div>
  );
}
