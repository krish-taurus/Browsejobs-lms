"use client";

import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ApiError, apiJson } from "@/lib/api";
import { EmptyState } from "@/components/ui/EmptyState";

type Row = { submission_id: number; assignment: string | null; student: string | null; status: string; grade_status: string | null; score: number | null; max_points: number | null };
type GradeCriterion = { criterion: string; score: number; note: string };
type Detail = {
  submission_id: number;
  student: string | null;
  assignment: { title: string | null; rubric: { criterion: string; max_points: number }[]; max_points: number };
  body: string | null;
  link: string | null;
  file: { name: string | null; url: string | null } | null;
  grade: { id: number; status: string; score: number; max_points: number; feedback: string | null; criteria: GradeCriterion[] | null; ai_likelihood: number | null; ai_likelihood_caveat: string } | null;
};

export default function GradingPage() {
  const qc = useQueryClient();
  const [tab, setTab] = useState<"all" | "ungraded" | "released">("ungraded");
  const [openId, setOpenId] = useState<number | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [feedback, setFeedback] = useState("");
  const [criteria, setCriteria] = useState<GradeCriterion[]>([]);

  const list = useQuery({
    queryKey: ["admin", "grading", tab],
    queryFn: () => apiJson<{ data: Row[] }>(`/api/v1/admin/assignment-submissions?status=${tab === "all" ? "" : tab}`),
  });

  const detail = useQuery({
    queryKey: ["admin", "submission", openId],
    queryFn: () => apiJson<{ data: Detail }>(`/api/v1/admin/submissions/${openId}`),
    enabled: openId !== null,
  });

  const open = (id: number) => {
    setOpenId(id);
    setError(null);
    void apiJson<{ data: Detail }>(`/api/v1/admin/submissions/${id}`).then((r) => {
      setFeedback(r.data.grade?.feedback ?? "");
      setCriteria(r.data.grade?.criteria ?? r.data.assignment.rubric.map((c) => ({ criterion: c.criterion, score: 0, note: "" })));
    });
  };

  const refresh = () => { void qc.invalidateQueries({ queryKey: ["admin", "grading"] }); void qc.invalidateQueries({ queryKey: ["admin", "submission", openId] }); };
  const onError = (err: unknown) => setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Request failed.");

  const gradeId = detail.data?.data.grade?.id;

  const saveGrade = useMutation({
    mutationFn: () => apiJson(`/api/v1/admin/grades/${gradeId}`, { method: "PATCH", body: JSON.stringify({ feedback, criteria }) }),
    onSuccess: () => { setError(null); refresh(); },
    onError,
  });
  const release = useMutation({
    mutationFn: () => apiJson(`/api/v1/admin/grades/${gradeId}/release`, { method: "POST", body: JSON.stringify({}) }),
    onSuccess: () => { setError(null); refresh(); },
    onError,
  });

  const rows = list.data?.data ?? [];
  const d = detail.data?.data;

  return (
    <div className="mx-auto max-w-5xl">
      <p className="kicker text-trust">Assessments</p>
      <h1 className="display mt-2 text-3xl text-ink">Grading</h1>

      <div className="mt-4 flex gap-2">
        {(["ungraded", "released", "all"] as const).map((t) => (
          <button key={t} onClick={() => { setTab(t); setOpenId(null); }} className={`rounded-full px-4 py-1.5 text-sm font-medium ${tab === t ? "bg-ink text-white" : "border border-line text-muted hover:bg-paper"}`}>{t}</button>
        ))}
      </div>

      <div className="mt-6 grid gap-6 md:grid-cols-[1fr_1.3fr]">
        {/* Queue */}
        <div>
          {list.isLoading ? (
            <div className="space-y-2">{Array.from({ length: 5 }).map((_, i) => <div key={i} className="shimmer h-14 rounded-[14px]" />)}</div>
          ) : rows.length === 0 ? (
            <EmptyState title="Nothing here" body="Submissions to review will appear in this queue." />
          ) : (
            <div className="divide-y divide-line rounded-[14px] border border-line bg-white">
              {rows.map((r) => (
                <button key={r.submission_id} onClick={() => open(r.submission_id)} className={`flex w-full items-center gap-3 px-4 py-3 text-left hover:bg-paper ${openId === r.submission_id ? "bg-sky/40" : ""}`}>
                  <div className="min-w-0 flex-1">
                    <p className="truncate text-sm text-ink">{r.student ?? "Student"}</p>
                    <p className="mono text-[11px] uppercase tracking-widest text-muted">{r.assignment ?? "—"}</p>
                  </div>
                  <span className={`mono rounded-full px-2 py-0.5 text-[10px] uppercase ${r.grade_status === "approved" ? "bg-verify-bg text-verify" : "bg-sky text-deep"}`}>{r.grade_status ?? "ungraded"}</span>
                </button>
              ))}
            </div>
          )}
        </div>

        {/* Detail */}
        <div>
          {openId === null ? (
            <p className="text-sm text-muted">Select a submission to grade.</p>
          ) : !d ? (
            <div className="shimmer h-64 rounded-[14px]" />
          ) : (
            <div className="rounded-2xl border border-line bg-white p-5">
              <h2 className="display text-lg text-ink">{d.student} · {d.assignment.title}</h2>
              {d.body && <p className="mt-2 whitespace-pre-wrap rounded-[10px] bg-paper p-3 text-sm text-ink">{d.body}</p>}
              {d.link && <a href={d.link} target="_blank" rel="noreferrer" className="mt-2 block text-sm text-trust hover:underline">{d.link}</a>}
              {d.file?.url && <a href={d.file.url} className="mt-2 block text-sm text-trust hover:underline">📎 {d.file.name}</a>}

              {d.grade?.ai_likelihood != null && (
                <div className="mt-4 rounded-[10px] border border-amber/40 bg-amber/10 p-3">
                  <p className="mono text-xs font-semibold text-amber">AI-authorship likelihood: {d.grade.ai_likelihood}%</p>
                  <p className="mt-1 text-[11px] text-muted">{d.grade.ai_likelihood_caveat}</p>
                </div>
              )}

              {/* Editable grade */}
              <div className="mt-4 space-y-2">
                {criteria.map((c, i) => (
                  <div key={i} className="flex items-center gap-2">
                    <span className="flex-1 text-sm text-ink">{c.criterion}</span>
                    <input type="number" value={c.score} onChange={(e) => setCriteria(criteria.map((x, j) => j === i ? { ...x, score: Number(e.target.value) } : x))} className="w-20 rounded-[10px] border border-line px-2 py-1 text-sm text-ink outline-none focus:border-trust" />
                    <span className="mono text-xs text-muted">/{d.assignment.rubric.find((r) => r.criterion === c.criterion)?.max_points ?? "?"}</span>
                  </div>
                ))}
                <textarea value={feedback} onChange={(e) => setFeedback(e.target.value)} rows={3} placeholder="Overall feedback…" className="mt-2 w-full resize-none rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust" />
              </div>

              {error && <p className="mt-2 text-sm text-warn">{error}</p>}
              <div className="mt-3 flex gap-2">
                <button onClick={() => saveGrade.mutate()} disabled={!gradeId || saveGrade.isPending} className="rounded-full border border-line px-4 py-2 text-sm font-semibold text-ink hover:bg-paper disabled:opacity-50">Save draft</button>
                <button onClick={() => release.mutate()} disabled={!gradeId || release.isPending || d.grade?.status === "approved"} className="rounded-full bg-verify px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">
                  {d.grade?.status === "approved" ? "Released ✓" : "Release to student"}
                </button>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
