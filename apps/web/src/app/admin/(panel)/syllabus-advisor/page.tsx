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

  const [intelCourse, setIntelCourse] = useState("");
  const intel = useQuery({
    queryKey: ["admin", "interview-intel", intelCourse],
    queryFn: () => apiJson<{ data: { courses: { id: number; name: string }[]; course_id: number; sample: number; topics: { topic: string; frequency: number; questions: number; struggles: number; items: { question: string; asked_count: number; difficulty: string | null; round_type: string | null; company: string | null }[] }[]; companies: { company: string; frequency: number }[] } }>(`/api/v1/admin/interview-intel${intelCourse ? `?course_id=${intelCourse}` : ""}`),
  });

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

      {/* What interviews actually ask — pure aggregation over the approved bank */}
      <section className="mt-6 rounded-[14px] border border-line bg-white p-5">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <p className="kicker text-verify">What interviews actually ask</p>
          <select value={intelCourse} onChange={(e) => setIntelCourse(e.target.value)} className="rounded-[10px] border border-line bg-white px-3 py-1.5 text-sm outline-none focus:border-trust">
            {(intel.data?.data.courses ?? []).map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
          </select>
        </div>
        {intel.isLoading ? (
          <div className="mt-4 shimmer h-24 rounded-[10px]" />
        ) : (intel.data?.data.topics ?? []).length === 0 ? (
          <p className="mt-3 text-sm text-muted">
            No approved interview questions yet for this course. Upload real interviews under
            Careers → Interview bank and approve the extracted questions — this ranking builds itself from them.
          </p>
        ) : (
          <>
            <p className="mono mt-1 text-[11px] uppercase tracking-widest text-muted">
              {intel.data?.data.sample} approved questions · ranked by how often each topic is asked
            </p>
            {(intel.data?.data.companies ?? []).length > 0 && (
              <div className="mt-3 flex flex-wrap gap-1.5">
                {(intel.data?.data.companies ?? []).map((c) => (
                  <span key={c.company} className="mono rounded-full bg-sky px-2.5 py-0.5 text-[11px] text-deep">{c.company} · {c.frequency}</span>
                ))}
              </div>
            )}
            <div className="mt-4 space-y-2">
              {(intel.data?.data.topics ?? []).map((t, i) => (
                <details key={t.topic} className="rounded-[10px] border border-line bg-paper px-4 py-3">
                  <summary className="flex cursor-pointer flex-wrap items-center gap-3 text-sm">
                    <span className="mono w-6 text-muted">#{i + 1}</span>
                    <span className="font-semibold text-ink">{t.topic}</span>
                    <span className="mono text-xs text-muted">asked {t.frequency}× · {t.questions} question{t.questions === 1 ? "" : "s"}</span>
                    {t.struggles > 0 && <span className="mono rounded-full bg-warn/10 px-2 py-0.5 text-[10px] text-warn">{t.struggles} struggled</span>}
                  </summary>
                  <ul className="mt-3 space-y-2 border-t border-line pt-3">
                    {t.items.map((q, j) => (
                      <li key={j} className="text-sm text-ink">
                        {q.question}
                        <span className="mono ml-2 text-[10px] uppercase tracking-widest text-muted">
                          {[q.company, q.round_type, q.difficulty, `asked ${q.asked_count}×`].filter(Boolean).join(" · ")}
                        </span>
                      </li>
                    ))}
                  </ul>
                </details>
              ))}
            </div>
          </>
        )}
      </section>
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
