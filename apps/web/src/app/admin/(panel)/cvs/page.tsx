"use client";

import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ApiError, apiJson } from "@/lib/api";
import { EmptyState } from "@/components/ui/EmptyState";

type CvRow = {
  id: number;
  student: string | null;
  title: string;
  version: number;
  source: string;
  parse_score: number | null;
  status: string;
  content: {
    headline?: string;
    summary?: string;
    skills?: string[];
    projects?: { name: string; bullets: string[] }[];
  };
  created_at: string | null;
};

export default function AdminCvsPage() {
  const qc = useQueryClient();
  const [open, setOpen] = useState<number | null>(null);
  const [error, setError] = useState<string | null>(null);

  const { data, isLoading } = useQuery({
    queryKey: ["admin", "cvs"],
    queryFn: () => apiJson<{ data: { pending: CvRow[]; approved: CvRow[] } }>("/api/v1/admin/cvs"),
  });

  const approve = useMutation({
    mutationFn: (id: number) => apiJson(`/api/v1/admin/cvs/${id}/approve`, { method: "POST", body: JSON.stringify({}) }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["admin", "cvs"] }),
    onError: (err) => setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Something went wrong."),
  });

  const pending = data?.data.pending ?? [];
  const approved = data?.data.approved ?? [];

  const row = (cv: CvRow, actionable: boolean) => (
    <div key={cv.id} className="px-5 py-3">
      <div className="flex items-center gap-3">
        <div className="min-w-0 flex-1">
          <p className="truncate text-sm text-ink">{cv.student} · v{cv.version}</p>
          <p className="mono text-[11px] uppercase tracking-widest text-muted">
            {cv.source.replace("_", " ")}{cv.parse_score !== null && ` · ats ${cv.parse_score}`}
          </p>
        </div>
        <button onClick={() => setOpen(open === cv.id ? null : cv.id)} className="text-xs text-trust hover:underline">
          {open === cv.id ? "Hide" : "Review"}
        </button>
        {actionable && (
          <button onClick={() => approve.mutate(cv.id)} disabled={approve.isPending}
            className="rounded-full bg-trust px-4 py-1.5 text-xs font-semibold text-white disabled:opacity-50">
            Approve
          </button>
        )}
      </div>
      {open === cv.id && (
        <div className="mt-3 rounded-[10px] bg-paper p-4 text-sm">
          <p className="font-semibold text-trust">{cv.content.headline}</p>
          <p className="mt-1 text-ink">{cv.content.summary}</p>
          {(cv.content.skills?.length ?? 0) > 0 && (
            <p className="mono mt-2 text-[11px] uppercase tracking-widest text-muted">{cv.content.skills!.join(" · ")}</p>
          )}
          {(cv.content.projects ?? []).map((p, i) => (
            <div key={i} className="mt-2">
              <p className="text-xs font-semibold text-ink">{p.name}</p>
              <ul className="text-xs text-muted">{p.bullets.map((b, j) => <li key={j}>• {b}</li>)}</ul>
            </div>
          ))}
        </div>
      )}
    </div>
  );

  return (
    <div className="mx-auto max-w-3xl">
      <p className="kicker text-trust">Placement</p>
      <h1 className="display mt-2 text-3xl text-ink">CV approvals</h1>
      <p className="mt-1 text-sm text-muted">
        Approval marks a version placement-ready — it carries the company&apos;s name after this.
        Student edits after approval reset it to draft.
      </p>
      {error && <p className="mt-3 text-sm text-warn">{error}</p>}

      <h2 className="mt-8 text-sm font-semibold uppercase tracking-widest text-muted">
        Waiting for review{pending.length > 0 && ` · ${pending.length}`}
      </h2>
      {isLoading ? (
        <div className="mt-3 space-y-2">{Array.from({ length: 3 }).map((_, i) => <div key={i} className="shimmer h-14 rounded-[14px]" />)}</div>
      ) : pending.length === 0 ? (
        <div className="mt-3"><EmptyState title="Nothing waiting" body="New and edited CVs land here for placement approval." /></div>
      ) : (
        <div className="mt-3 divide-y divide-line rounded-[14px] border border-line bg-white">
          {pending.map((cv) => row(cv, true))}
        </div>
      )}

      <h2 className="mt-8 text-sm font-semibold uppercase tracking-widest text-muted">Recently approved</h2>
      {approved.length === 0 ? (
        <p className="mt-3 text-sm text-muted">None yet.</p>
      ) : (
        <div className="mt-3 divide-y divide-line rounded-[14px] border border-line bg-white">
          {approved.map((cv) => row(cv, false))}
        </div>
      )}
    </div>
  );
}
