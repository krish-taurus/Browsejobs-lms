"use client";

import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ApiError, apiJson } from "@/lib/api";
import { EmptyState } from "@/components/ui/EmptyState";

type Feedback = {
  id: number;
  company: string;
  role_title: string;
  candidate: string | null;
  status: string;
  overall: number | null;
  would_hire: boolean | null;
  ratings: Record<string, number> | null;
  strengths: string | null;
  gaps: string | null;
  link: string | null;
  submitted_at: string | null;
};
type Course = { id: number; name: string };
type Benchmark = {
  id: number;
  course: string | null;
  role_title: string;
  city: string;
  experience_band: string;
  p25_lpa: number;
  p50_lpa: number;
  p75_lpa: number;
  source: string | null;
};

type BenchForm = { role_title: string; city: string; experience_band: string; p25_lpa: string; p50_lpa: string; p75_lpa: string; course_id: string; source: string };
const emptyBench: BenchForm = { role_title: "", city: "", experience_band: "fresher", p25_lpa: "", p50_lpa: "", p75_lpa: "", course_id: "", source: "" };

export default function AdviceGraphPage() {
  const qc = useQueryClient();
  const [error, setError] = useState<string | null>(null);
  const [copied, setCopied] = useState<number | null>(null);
  const [bench, setBench] = useState<BenchForm>(emptyBench);

  const feedback = useQuery({
    queryKey: ["admin", "partner-feedback"],
    queryFn: () => apiJson<{ data: Feedback[] }>("/api/v1/admin/partner-feedback"),
  });
  const benchmarks = useQuery({
    queryKey: ["admin", "salary-benchmarks"],
    queryFn: () => apiJson<{ data: { courses: Course[]; benchmarks: Benchmark[] } }>("/api/v1/admin/salary-benchmarks"),
  });

  const onError = (err: unknown) => setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Something went wrong.");
  const courses = benchmarks.data?.data.courses ?? [];
  const benchRows = benchmarks.data?.data.benchmarks ?? [];
  const feedbackRows = feedback.data?.data ?? [];

  const addBench = useMutation({
    mutationFn: () =>
      apiJson("/api/v1/admin/salary-benchmarks", {
        method: "POST",
        body: JSON.stringify({
          role_title: bench.role_title,
          city: bench.city,
          experience_band: bench.experience_band,
          p25_lpa: Number(bench.p25_lpa),
          p50_lpa: Number(bench.p50_lpa),
          p75_lpa: Number(bench.p75_lpa),
          course_id: bench.course_id ? Number(bench.course_id) : null,
          source: bench.source.trim() || null,
        }),
      }),
    onSuccess: () => { setBench(emptyBench); setError(null); qc.invalidateQueries({ queryKey: ["admin", "salary-benchmarks"] }); },
    onError,
  });

  const removeBench = useMutation({
    mutationFn: (id: number) => apiJson(`/api/v1/admin/salary-benchmarks/${id}`, { method: "DELETE" }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["admin", "salary-benchmarks"] }),
    onError,
  });

  const canAddBench = bench.role_title.trim() && bench.city.trim() && bench.p25_lpa && bench.p50_lpa && bench.p75_lpa;

  return (
    <div className="mx-auto max-w-3xl">
      <p className="kicker text-trust">Curriculum intelligence</p>
      <h1 className="display mt-2 text-3xl text-ink">Advice graph inputs</h1>
      <p className="mt-1 text-sm text-muted">
        Hiring-partner feedback and salary benchmarks — the long-term signals that sharpen coaching,
        gap reports, and offer guidance. Alumni check-ins feed in automatically as placements mature.
      </p>

      {error && <p className="mt-4 text-sm text-warn">{error}</p>}

      {/* Hiring partner feedback */}
      <h2 className="mt-8 text-sm font-semibold uppercase tracking-widest text-muted">Hiring-partner feedback</h2>
      <p className="mt-1 text-xs text-muted">
        Request feedback from a candidate&apos;s placement page (Placements → an application), then share the
        generated link with the company. Submitted feedback appears here.
      </p>
      {feedback.isLoading ? (
        <div className="mt-3 space-y-2">{Array.from({ length: 2 }).map((_, i) => <div key={i} className="shimmer h-16 rounded-[14px]" />)}</div>
      ) : feedbackRows.length === 0 ? (
        <div className="mt-3"><EmptyState title="No feedback yet" body="Request one from a candidate's application in Placements." /></div>
      ) : (
        <div className="mt-3 space-y-2">
          {feedbackRows.map((f) => (
            <div key={f.id} className="rounded-[14px] border border-line bg-white p-4">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <div>
                  <p className="text-sm font-semibold text-ink">{f.company} · {f.role_title}</p>
                  <p className="mono text-[11px] uppercase tracking-widest text-muted">
                    {f.candidate ?? "candidate"}{f.overall ? ` · ${f.overall}/5 overall` : ""}
                    {f.would_hire != null && ` · ${f.would_hire ? "would hire" : "would not hire"}`}
                  </p>
                </div>
                <span className={`mono rounded-full px-2.5 py-0.5 text-[11px] uppercase tracking-widest ${f.status === "submitted" ? "bg-verify-bg text-verify" : "bg-paper text-muted"}`}>
                  {f.status}
                </span>
              </div>
              {f.status === "submitted" ? (
                <div className="mt-2 text-xs text-ink">
                  {f.strengths && <p><span className="text-muted">Strengths:</span> {f.strengths}</p>}
                  {f.gaps && <p className="mt-1"><span className="text-muted">Gaps:</span> {f.gaps}</p>}
                </div>
              ) : f.link ? (
                <button
                  onClick={() => { navigator.clipboard?.writeText(f.link!); setCopied(f.id); }}
                  className="mono mt-2 text-[11px] text-trust hover:underline"
                >
                  {copied === f.id ? "Link copied ✓" : "Copy feedback link for the company"}
                </button>
              ) : null}
            </div>
          ))}
        </div>
      )}

      {/* Salary benchmarks */}
      <h2 className="mt-10 text-sm font-semibold uppercase tracking-widest text-muted">Salary benchmarks</h2>
      <div className="mt-3 rounded-[14px] border border-line bg-white p-5">
        <div className="grid gap-3 sm:grid-cols-3">
          <input value={bench.role_title} onChange={(e) => setBench({ ...bench, role_title: e.target.value })} placeholder="Role" className="rounded-[10px] border border-line bg-white px-3 py-2 text-sm outline-none focus:border-trust" />
          <input value={bench.city} onChange={(e) => setBench({ ...bench, city: e.target.value })} placeholder="City" className="rounded-[10px] border border-line bg-white px-3 py-2 text-sm outline-none focus:border-trust" />
          <select value={bench.experience_band} onChange={(e) => setBench({ ...bench, experience_band: e.target.value })} className="rounded-[10px] border border-line bg-white px-3 py-2 text-sm outline-none focus:border-trust">
            {["fresher", "1-3", "3-5", "5plus"].map((b) => <option key={b} value={b}>{b}</option>)}
          </select>
          <input value={bench.p25_lpa} onChange={(e) => setBench({ ...bench, p25_lpa: e.target.value })} inputMode="decimal" placeholder="P25 (LPA)" className="rounded-[10px] border border-line bg-white px-3 py-2 text-sm outline-none focus:border-trust" />
          <input value={bench.p50_lpa} onChange={(e) => setBench({ ...bench, p50_lpa: e.target.value })} inputMode="decimal" placeholder="Median (LPA)" className="rounded-[10px] border border-line bg-white px-3 py-2 text-sm outline-none focus:border-trust" />
          <input value={bench.p75_lpa} onChange={(e) => setBench({ ...bench, p75_lpa: e.target.value })} inputMode="decimal" placeholder="P75 (LPA)" className="rounded-[10px] border border-line bg-white px-3 py-2 text-sm outline-none focus:border-trust" />
        </div>
        <div className="mt-3 flex flex-wrap items-center gap-3">
          <select value={bench.course_id} onChange={(e) => setBench({ ...bench, course_id: e.target.value })} className="rounded-[10px] border border-line bg-white px-3 py-2 text-sm outline-none focus:border-trust">
            <option value="">Course… (optional)</option>
            {courses.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
          </select>
          <button onClick={() => addBench.mutate()} disabled={!canAddBench || addBench.isPending} className="rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white disabled:opacity-50">
            {addBench.isPending ? "Adding…" : "Add benchmark"}
          </button>
        </div>
      </div>

      {benchRows.length > 0 && (
        <div className="mt-3 overflow-x-auto rounded-[14px] border border-line bg-white">
          <table className="w-full text-left text-sm">
            <thead>
              <tr className="mono text-[10px] uppercase tracking-widest text-muted">
                <th className="px-4 py-2">Role</th><th className="px-4 py-2">City</th><th className="px-4 py-2">Exp</th>
                <th className="px-4 py-2 text-right">P25</th><th className="px-4 py-2 text-right">Median</th><th className="px-4 py-2 text-right">P75</th><th className="px-4 py-2" />
              </tr>
            </thead>
            <tbody className="divide-y divide-line">
              {benchRows.map((b) => (
                <tr key={b.id}>
                  <td className="px-4 py-2 text-ink">{b.role_title}</td>
                  <td className="px-4 py-2 text-muted">{b.city}</td>
                  <td className="mono px-4 py-2 text-[11px] text-muted">{b.experience_band}</td>
                  <td className="mono px-4 py-2 text-right text-ink">{b.p25_lpa}</td>
                  <td className="mono px-4 py-2 text-right font-semibold text-ink">{b.p50_lpa}</td>
                  <td className="mono px-4 py-2 text-right text-ink">{b.p75_lpa}</td>
                  <td className="px-4 py-2 text-right"><button onClick={() => removeBench.mutate(b.id)} className="text-xs text-warn hover:underline">Delete</button></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
