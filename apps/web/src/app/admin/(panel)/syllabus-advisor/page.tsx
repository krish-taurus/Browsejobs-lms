"use client";

import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ApiError, apiJson } from "@/lib/api";
import { EmptyState } from "@/components/ui/EmptyState";

type Item = { action: "add" | "expand" | "deprioritise"; topic: string; rationale: string; priority: string };
type Demand = { skill: string; count: number; share: number };
type Evidence = {
  market_demand?: Demand[];
  interview_topics?: { topic: string; frequency: number }[];
  failure_points?: { topic: string; count: number }[];
};
type Report = {
  id: number;
  course: string | null;
  course_id: number;
  status: "pending" | "approved" | "rejected";
  source: string;
  content_source: string;
  summary: string | null;
  items: Item[];
  evidence: Evidence;
  market_sample: number;
  reviewer: string | null;
  review_note: string | null;
  created_at: string | null;
};
type Course = { id: number; name: string };
type Payload = { courses: Course[]; reports: Report[] };

const actionStyle: Record<string, string> = {
  add: "bg-verify-bg text-verify",
  expand: "bg-sky text-deep",
  deprioritise: "bg-warn/10 text-warn",
};

export default function SyllabusAdvisorPage() {
  const qc = useQueryClient();
  const [courseId, setCourseId] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [expanded, setExpanded] = useState<number | null>(null);

  const { data, isLoading } = useQuery({
    queryKey: ["admin", "syllabus-recommendations"],
    queryFn: () => apiJson<{ data: Payload }>("/api/v1/admin/syllabus-recommendations"),
  });

  const courses = data?.data.courses ?? [];
  const reports = data?.data.reports ?? [];
  const refresh = () => qc.invalidateQueries({ queryKey: ["admin", "syllabus-recommendations"] });
  const onError = (err: unknown) => setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Something went wrong.");

  const generate = useMutation({
    mutationFn: () => apiJson(`/api/v1/admin/courses/${courseId}/syllabus-recommendations`, { method: "POST" }),
    onSuccess: () => { setError(null); refresh(); },
    onError,
  });

  const review = useMutation({
    mutationFn: ({ id, verb, note }: { id: number; verb: "approve" | "reject"; note?: string }) =>
      apiJson(`/api/v1/admin/syllabus-recommendations/${id}/${verb}`, {
        method: "POST",
        body: JSON.stringify({ note: note ?? null }),
      }),
    onSuccess: () => { setError(null); refresh(); },
    onError,
  });

  return (
    <div className="mx-auto max-w-3xl">
      <p className="kicker text-trust">Curriculum intelligence</p>
      <h1 className="display mt-2 text-3xl text-ink">Syllabus advisor</h1>
      <p className="mt-1 text-sm text-muted">
        Evidence-backed recommendations that cross-reference real job-market demand, interview-bank
        frequency, and where candidates actually struggle. Approving records the decision — apply the
        edits in Curriculum, and running batches stay untouched.
      </p>

      {error && <p className="mt-4 text-sm text-warn">{error}</p>}

      {/* Generate */}
      <div className="mt-6 flex flex-wrap items-center gap-3 rounded-[14px] border border-line bg-white p-4">
        <select
          value={courseId}
          onChange={(e) => setCourseId(e.target.value)}
          className="rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust"
        >
          <option value="">Choose a course…</option>
          {courses.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
        </select>
        <button
          onClick={() => generate.mutate()}
          disabled={!courseId || generate.isPending}
          className="rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white disabled:opacity-50"
        >
          {generate.isPending ? "Analysing…" : "Generate report"}
        </button>
      </div>

      {/* Reports */}
      {isLoading ? (
        <div className="mt-8 space-y-2">{Array.from({ length: 2 }).map((_, i) => <div key={i} className="shimmer h-32 rounded-[14px]" />)}</div>
      ) : reports.length === 0 ? (
        <div className="mt-8"><EmptyState title="No reports yet" body="Generate one for a course to see market-driven syllabus recommendations." /></div>
      ) : (
        <div className="mt-8 space-y-4">
          {reports.map((r) => (
            <div key={r.id} className="rounded-[14px] border border-line bg-white p-5">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <div>
                  <p className="text-sm font-semibold text-ink">{r.course ?? "Course"}</p>
                  <p className="mono text-[11px] uppercase tracking-widest text-muted">
                    {r.source} · {r.market_sample} JDs
                    {r.content_source === "fallback" && " · offline analysis"}
                    {r.created_at && ` · ${new Date(r.created_at).toLocaleDateString("en-IN")}`}
                  </p>
                </div>
                <span className={`mono rounded-full px-2.5 py-0.5 text-[11px] uppercase tracking-widest ${
                  r.status === "approved" ? "bg-verify-bg text-verify" : r.status === "rejected" ? "bg-warn/10 text-warn" : "bg-paper text-muted"
                }`}>
                  {r.status}
                </span>
              </div>

              {r.summary && <p className="mt-3 text-sm text-ink">{r.summary}</p>}

              <div className="mt-3 space-y-2">
                {r.items.map((it, i) => (
                  <div key={i} className="rounded-[10px] border border-line bg-paper p-3">
                    <div className="flex items-center gap-2">
                      <span className={`mono rounded-full px-2 py-0.5 text-[10px] uppercase tracking-widest ${actionStyle[it.action] ?? "bg-paper text-muted"}`}>
                        {it.action}
                      </span>
                      <span className="text-sm font-semibold text-ink">{it.topic}</span>
                      <span className="mono ml-auto text-[10px] uppercase tracking-widest text-muted">{it.priority}</span>
                    </div>
                    <p className="mt-1 text-xs text-muted">{it.rationale}</p>
                  </div>
                ))}
                {r.items.length === 0 && <p className="text-sm text-muted">No changes recommended — the outline already tracks the market.</p>}
              </div>

              <button
                onClick={() => setExpanded(expanded === r.id ? null : r.id)}
                className="mt-3 text-xs font-semibold text-trust hover:underline"
              >
                {expanded === r.id ? "Hide evidence" : "Show evidence"}
              </button>
              {expanded === r.id && (
                <div className="mt-2 grid gap-3 rounded-[10px] bg-paper p-3 text-xs sm:grid-cols-3">
                  <EvidenceCol title="Market demand" rows={(r.evidence.market_demand ?? []).map((d) => `${d.skill} · ${Math.round(d.share * 100)}%`)} />
                  <EvidenceCol title="Interview frequency" rows={(r.evidence.interview_topics ?? []).map((t) => `${t.topic} · ${t.frequency}`)} />
                  <EvidenceCol title="Failure points" rows={(r.evidence.failure_points ?? []).map((f) => `${f.topic} · ${f.count}`)} />
                </div>
              )}

              {r.status === "pending" ? (
                <div className="mt-4 flex gap-2">
                  <button
                    onClick={() => review.mutate({ id: r.id, verb: "approve" })}
                    disabled={review.isPending}
                    className="rounded-full bg-trust px-4 py-1.5 text-xs font-semibold text-white disabled:opacity-50"
                  >
                    Approve
                  </button>
                  <button
                    onClick={() => review.mutate({ id: r.id, verb: "reject" })}
                    disabled={review.isPending}
                    className="rounded-full border border-line bg-white px-4 py-1.5 text-xs font-semibold text-ink hover:border-warn disabled:opacity-50"
                  >
                    Reject
                  </button>
                </div>
              ) : (
                <p className="mono mt-4 text-[11px] uppercase tracking-widest text-muted">
                  {r.status} by {r.reviewer ?? "staff"}{r.review_note ? ` · ${r.review_note}` : ""}
                </p>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

function EvidenceCol({ title, rows }: { title: string; rows: string[] }) {
  return (
    <div>
      <p className="mono pb-1 text-[10px] uppercase tracking-widest text-muted">{title}</p>
      {rows.length === 0 ? (
        <p className="text-muted">—</p>
      ) : (
        <ul className="space-y-0.5 text-ink">
          {rows.slice(0, 8).map((row, i) => <li key={i} className="truncate">{row}</li>)}
        </ul>
      )}
    </div>
  );
}
