"use client";

import Link from "next/link";
import { notFound } from "next/navigation";
import { use, useEffect, useState } from "react";
import { keepPreviousData, useQuery } from "@tanstack/react-query";
import { apiJson } from "@/lib/api";
import { EmptyState } from "@/components/ui/EmptyState";
import { MOCK_KINDS, MOCK_KIND_META, isMockKind, type MockKind } from "@/lib/mockKinds";

type Row = {
  id: number;
  student: string | null;
  email: string | null;
  phone: string | null;
  role_title: string | null;
  status: "in_progress" | "completed" | "abandoned";
  overall_score: number | null;
  scorecard_source: string | null;
  duration_seconds: number | null;
  started_at: string | null;
  completed_at: string | null;
};

type Payload = {
  data: { kind: MockKind; counts: Record<MockKind, number>; interviews: Row[] };
  meta: { current_page: number; last_page: number; total: number };
};

const STATUS: Record<Row["status"], { label: string; tone: string }> = {
  in_progress: { label: "In progress", tone: "bg-sky text-trust" },
  completed: { label: "Completed", tone: "bg-verify-bg text-verify" },
  abandoned: { label: "Not scored", tone: "bg-paper text-muted" },
};

function when(iso: string | null): string {
  return iso
    ? new Date(iso).toLocaleString("en-IN", { day: "numeric", month: "short", year: "numeric", hour: "2-digit", minute: "2-digit" })
    : "—";
}

function scoreTone(score: number | null): string {
  if (score === null) return "text-muted";
  if (score >= 70) return "text-verify";
  if (score >= 40) return "text-ink";
  return "text-warn";
}

/** One table per interview kind — /admin/ai-interviews/{practice|voice|job|cv}. */
export default function AdminAiInterviewsPage({ params }: { params: Promise<{ kind: string }> }) {
  const { kind: rawKind } = use(params);
  const valid = isMockKind(rawKind);
  const kind: MockKind = valid ? rawKind : "practice";

  const [status, setStatus] = useState("");
  const [search, setSearch] = useState("");
  const [q, setQ] = useState("");
  const [page, setPage] = useState(1);

  // Debounce the search box so typing doesn't fire a request per key.
  useEffect(() => {
    const t = setTimeout(() => { setQ(search.trim()); setPage(1); }, 350);
    return () => clearTimeout(t);
  }, [search]);

  const query = new URLSearchParams({ kind, page: String(page) });
  if (status) query.set("status", status);
  if (q) query.set("q", q);

  const { data, isLoading, isError } = useQuery({
    queryKey: ["admin", "mock-interviews", kind, status, q, page],
    queryFn: () => apiJson<Payload>(`/api/v1/admin/mock-interviews?${query.toString()}`),
    placeholderData: keepPreviousData,
    enabled: valid,
  });

  if (!valid) notFound();

  const rows = data?.data.interviews ?? [];
  const counts = data?.data.counts;
  const meta = data?.meta;

  return (
    <div className="mx-auto max-w-6xl">
      <p className="kicker text-trust">AI interviews</p>
      <h1 className="display mt-2 text-3xl text-ink">{MOCK_KIND_META[kind].title}</h1>
      <p className="mt-1 text-sm text-muted">{MOCK_KIND_META[kind].blurb}</p>

      <nav aria-label="Interview types" className="mt-6 flex gap-2 overflow-x-auto pb-1">
        {MOCK_KINDS.map((k) => {
          const on = k === kind;
          return (
            <Link
              key={k}
              href={`/admin/ai-interviews/${k}`}
              aria-current={on ? "page" : undefined}
              className={`flex shrink-0 items-center gap-2 rounded-full border px-4 py-2 text-sm font-semibold ${
                on ? "border-trust bg-trust text-white" : "border-line bg-white text-ink hover:border-trust"
              }`}
            >
              {MOCK_KIND_META[k].label}
              {counts && <span className={`mono text-xs ${on ? "text-white/80" : "text-muted"}`}>{counts[k]}</span>}
            </Link>
          );
        })}
      </nav>

      <div className="mt-4 flex flex-wrap gap-3">
        <input
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          placeholder="Search name, email or phone"
          className="w-full max-w-xs rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust"
        />
        <select
          value={status}
          onChange={(e) => { setStatus(e.target.value); setPage(1); }}
          className="rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust"
        >
          <option value="">All statuses</option>
          <option value="completed">Completed</option>
          <option value="in_progress">In progress</option>
          <option value="abandoned">Not scored</option>
        </select>
        {meta && <span className="mono self-center text-xs text-muted">{meta.total} interviews</span>}
      </div>

      {isError ? (
        <p className="mt-6 text-sm text-warn">Couldn&apos;t load interviews. Refresh to try again.</p>
      ) : isLoading ? (
        <div className="shimmer mt-6 h-64 rounded-[14px]" />
      ) : rows.length === 0 ? (
        <div className="mt-6">
          <EmptyState title="No interviews here yet" body="Interviews of this type will appear as students take them." />
        </div>
      ) : (
        <div className="mt-6 overflow-x-auto rounded-[14px] border border-line bg-white">
          <table className="w-full min-w-[860px] text-left text-sm">
            <thead>
              <tr className="border-b border-line text-xs uppercase tracking-widest text-muted">
                <th className="px-4 py-3 font-semibold">#</th>
                <th className="px-4 py-3 font-semibold">Student</th>
                <th className="px-4 py-3 font-semibold">Role</th>
                <th className="px-4 py-3 font-semibold">Status</th>
                <th className="px-4 py-3 text-right font-semibold">Score</th>
                <th className="px-4 py-3 font-semibold">Started</th>
                <th className="px-4 py-3 font-semibold">Completed</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((r) => (
                <tr key={r.id} className="border-b border-line last:border-0 align-top">
                  <td className="mono px-4 py-3 text-muted">{r.id}</td>
                  <td className="px-4 py-3">
                    <p className="text-ink">{r.student ?? "—"}</p>
                    <p className="mono text-xs text-muted">{[r.email, r.phone].filter(Boolean).join(" · ")}</p>
                  </td>
                  <td className="px-4 py-3 text-ink">{r.role_title ?? "—"}</td>
                  <td className="px-4 py-3">
                    <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${STATUS[r.status].tone}`}>{STATUS[r.status].label}</span>
                    {r.scorecard_source === "fallback" && <p className="mt-1 text-xs text-muted">Fallback score</p>}
                  </td>
                  <td className={`mono px-4 py-3 text-right font-semibold ${scoreTone(r.overall_score)}`}>
                    {r.overall_score === null ? "—" : `${r.overall_score}/100`}
                  </td>
                  <td className="mono px-4 py-3 text-xs text-muted">{when(r.started_at)}</td>
                  <td className="mono px-4 py-3 text-xs text-muted">{when(r.completed_at)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {meta && meta.last_page > 1 && (
        <div className="mt-4 flex items-center justify-end gap-3">
          <button
            onClick={() => setPage((p) => Math.max(1, p - 1))}
            disabled={page <= 1}
            className="rounded-full border border-line bg-white px-4 py-1.5 text-sm font-semibold text-ink disabled:opacity-40"
          >
            ← Prev
          </button>
          <span className="mono text-xs text-muted">{meta.current_page} / {meta.last_page}</span>
          <button
            onClick={() => setPage((p) => Math.min(meta.last_page, p + 1))}
            disabled={page >= meta.last_page}
            className="rounded-full border border-line bg-white px-4 py-1.5 text-sm font-semibold text-ink disabled:opacity-40"
          >
            Next →
          </button>
        </div>
      )}
    </div>
  );
}
