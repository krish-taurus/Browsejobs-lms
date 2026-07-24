"use client";

import { useRef, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ApiError, apiJson } from "@/lib/api";
import { EmptyState } from "@/components/ui/EmptyState";

type Transcript = {
  id: number;
  source: string;
  original_name: string | null;
  role_title: string | null;
  course: string | null;
  uploader: string | null;
  status: string;
  parse_error: string | null;
  questions_found: number;
  created_at: string | null;
};

type BankQuestion = {
  id: number;
  question: string;
  role_title: string;
  course: string | null;
  topic_tags: string[];
  difficulty: string | null;
  round_type: string | null;
  follow_ups: string[] | null;
  strong_answer: string | null;
  struggle_points: string | null;
  confidence: string;
  status: string;
  asked_count: number;
};

type Analytics = {
  total_approved: number;
  by_topic: Record<string, number>;
  by_role: Record<string, number>;
  monthly_trend: Record<string, number>;
};

type Course = { id: number; name: string };

const SOURCES = ["text", "parakeet", "debrief", "file", "audio", "zip"] as const;

function StatusPill({ status }: { status: string }) {
  const tone =
    status === "parsed" || status === "approved"
      ? "bg-verify-bg text-verify"
      : status === "failed" || status === "rejected"
        ? "bg-warn/10 text-warn"
        : "bg-sky text-deep";
  return (
    <span className={`mono rounded-full px-2.5 py-0.5 text-[11px] uppercase tracking-widest ${tone}`}>{status}</span>
  );
}

export default function AdminInterviewBankPage() {
  const qc = useQueryClient();
  const fileRef = useRef<HTMLInputElement | null>(null);
  const [source, setSource] = useState<(typeof SOURCES)[number]>("text");
  const [text, setText] = useState("");
  const [roleTitle, setRoleTitle] = useState("");
  const [company, setCompany] = useState("");
  const [courseId, setCourseId] = useState("");
  const [consent, setConsent] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  const bank = useQuery({
    queryKey: ["admin", "interview-bank"],
    queryFn: () =>
      apiJson<{ data: { pending: BankQuestion[]; approved: BankQuestion[]; analytics: Analytics } }>(
        "/api/v1/admin/interview-bank",
      ),
  });
  const transcripts = useQuery({
    queryKey: ["admin", "interview-transcripts"],
    queryFn: () => apiJson<{ data: Transcript[] }>("/api/v1/admin/interview-transcripts"),
  });
  const { data: coursesData } = useQuery({
    queryKey: ["admin", "courses"],
    queryFn: () => apiJson<{ data: Course[] }>("/api/v1/admin/courses"),
  });

  const refresh = () => {
    qc.invalidateQueries({ queryKey: ["admin", "interview-bank"] });
    qc.invalidateQueries({ queryKey: ["admin", "interview-transcripts"] });
  };
  const onError = (err: unknown) =>
    setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Something went wrong.");

  const upload = useMutation({
    mutationFn: () => {
      const form = new FormData();
      form.append("source", source);
      form.append("consent", consent ? "1" : "0");
      if (roleTitle.trim()) form.append("role_title", roleTitle.trim());
      if (company.trim()) form.append("company", company.trim());
      if (courseId) form.append("course_id", courseId);
      const file = fileRef.current?.files?.[0];
      if (file) form.append("file", file);
      else form.append("text", text);
      return apiJson("/api/v1/admin/interview-transcripts", { method: "POST", body: form });
    },
    onSuccess: () => {
      setText("");
      setConsent(false);
      if (fileRef.current) fileRef.current.value = "";
      setError(null);
      setNotice("Uploaded — parsing runs in the background. Refresh the pipeline below in a moment.");
      refresh();
    },
    onError,
  });

  const decide = useMutation({
    mutationFn: ({ id, action }: { id: number; action: "approve" | "reject" }) =>
      apiJson(`/api/v1/admin/interview-bank/${id}/${action}`, { method: "POST", body: JSON.stringify({}) }),
    onSuccess: refresh,
    onError,
  });

  const batchApprove = useMutation({
    mutationFn: () =>
      apiJson("/api/v1/admin/interview-bank/approve-batch", {
        method: "POST",
        body: JSON.stringify({ high_confidence: true }),
      }),
    onSuccess: refresh,
    onError,
  });

  const pending = bank.data?.data.pending ?? [];
  const approved = bank.data?.data.approved ?? [];
  const analytics = bank.data?.data.analytics;
  const rows = transcripts.data?.data ?? [];
  const courses = coursesData?.data ?? [];
  const topicMax = Math.max(1, ...Object.values(analytics?.by_topic ?? {}));
  const needsFile = source === "file" || source === "audio" || source === "zip";

  return (
    <div className="mx-auto max-w-4xl">
      <p className="kicker text-trust">Real interview intelligence</p>
      <h1 className="display mt-2 text-3xl text-ink">Interview bank</h1>
      <p className="mt-1 text-sm text-muted">
        Real interview transcripts → AI parse → anonymisation → your approval → the bank. Mocks draw
        from approved questions; gap reports and the syllabus engine read the frequencies.
      </p>

      {/* Upload */}
      <div className="mt-8 rounded-[14px] border border-line bg-white p-5">
        <h2 className="text-sm font-semibold text-ink">Ingest a transcript</h2>
        {error && <p className="mt-2 text-sm text-warn">{error}</p>}
        {notice && <p className="mt-2 text-sm text-verify">{notice}</p>}
        <div className="mt-3 grid gap-3 sm:grid-cols-3">
          <select
            value={source}
            onChange={(e) => setSource(e.target.value as (typeof SOURCES)[number])}
            className="rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust"
          >
            {SOURCES.map((s) => <option key={s} value={s}>{s}</option>)}
          </select>
          <input
            value={roleTitle}
            onChange={(e) => setRoleTitle(e.target.value)}
            placeholder="Role title (e.g. Data Engineer)"
            className="rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust"
          />
          <input
            value={company}
            onChange={(e) => setCompany(e.target.value)}
            placeholder="Company (e.g. Infosys)"
            className="rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust"
          />
          <select
            value={courseId}
            onChange={(e) => setCourseId(e.target.value)}
            className="rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust"
          >
            <option value="">Course (optional)…</option>
            {courses.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
          </select>
        </div>

        {needsFile ? (
          <input
            ref={fileRef}
            type="file"
            className="mt-3 w-full rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink"
          />
        ) : (
          <textarea
            value={text}
            onChange={(e) => setText(e.target.value)}
            rows={5}
            placeholder="Paste the interview transcript / Parakeet export / debrief notes…"
            className="mt-3 w-full resize-y rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust"
          />
        )}

        <label className="mt-3 flex cursor-pointer items-start gap-2 text-sm text-ink">
          <input type="checkbox" checked={consent} onChange={(e) => setConsent(e.target.checked)} className="mt-0.5 accent-trust" />
          <span>
            I confirm we have the right to share this recording/transcript. Names and contact details
            are removed automatically before anything enters the bank; the original is stored encrypted.
          </span>
        </label>

        <button
          onClick={() => upload.mutate()}
          disabled={upload.isPending || !consent || (needsFile ? false : !text.trim())}
          className="mt-3 rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white disabled:opacity-50"
        >
          {upload.isPending ? "Uploading…" : "Ingest transcript"}
        </button>
      </div>

      {/* Review queue */}
      <div className="mt-10 flex items-center justify-between">
        <h2 className="text-sm font-semibold uppercase tracking-widest text-muted">
          Review queue{pending.length > 0 && ` · ${pending.length}`}
        </h2>
        {pending.some((q) => q.confidence === "high") && (
          <button
            onClick={() => batchApprove.mutate()}
            disabled={batchApprove.isPending}
            className="rounded-full border border-line bg-white px-4 py-1.5 text-xs font-semibold text-ink disabled:opacity-50"
          >
            {batchApprove.isPending ? "Approving…" : "Approve all high-confidence"}
          </button>
        )}
      </div>
      {pending.length === 0 ? (
        <p className="mt-3 text-sm text-muted">Nothing waiting — parsed questions land here for approval.</p>
      ) : (
        <div className="mt-3 space-y-2">
          {pending.map((q) => (
            <div key={q.id} className="rounded-[14px] border border-line bg-white p-4">
              <p className="text-sm text-ink">{q.question}</p>
              <p className="mono mt-1 text-[11px] uppercase tracking-widest text-muted">
                {q.role_title} · {q.topic_tags.join(" · ")} · {q.difficulty ?? "—"} · {q.round_type ?? "—"} ·
                confidence {q.confidence}
              </p>
              {q.strong_answer && <p className="mt-2 text-xs text-muted">Strong answer: {q.strong_answer}</p>}
              <div className="mt-2 flex gap-3">
                <button onClick={() => decide.mutate({ id: q.id, action: "approve" })} className="text-xs font-semibold text-verify hover:underline">Approve</button>
                <button onClick={() => decide.mutate({ id: q.id, action: "reject" })} className="text-xs font-semibold text-warn hover:underline">Reject</button>
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Analytics */}
      <h2 className="mt-10 text-sm font-semibold uppercase tracking-widest text-muted">
        Bank analytics{analytics && ` · ${analytics.total_approved} live questions`}
      </h2>
      {!analytics || analytics.total_approved === 0 ? (
        <p className="mt-3 text-sm text-muted">Approve questions to see frequency by topic and role.</p>
      ) : (
        <div className="mt-3 grid gap-4 sm:grid-cols-2">
          <div className="rounded-[14px] border border-line bg-white p-4">
            <p className="text-xs font-semibold uppercase tracking-widest text-muted">Asked most, by topic</p>
            <div className="mt-3 space-y-2">
              {Object.entries(analytics.by_topic).map(([topic, n]) => (
                <div key={topic}>
                  <div className="flex justify-between text-xs text-ink"><span>{topic}</span><span className="mono">{n}×</span></div>
                  <div className="mt-1 h-1.5 rounded-full bg-paper">
                    <div className="h-1.5 rounded-full bg-trust" style={{ width: `${(n / topicMax) * 100}%` }} />
                  </div>
                </div>
              ))}
            </div>
          </div>
          <div className="rounded-[14px] border border-line bg-white p-4">
            <p className="text-xs font-semibold uppercase tracking-widest text-muted">By role</p>
            <div className="mt-3 space-y-1.5">
              {Object.entries(analytics.by_role).map(([role, n]) => (
                <div key={role} className="flex justify-between text-sm text-ink">
                  <span>{role}</span><span className="mono text-xs text-muted">{n}×</span>
                </div>
              ))}
            </div>
            {Object.keys(analytics.monthly_trend).length > 0 && (
              <>
                <p className="mt-4 text-xs font-semibold uppercase tracking-widest text-muted">Seen per month</p>
                <div className="mt-2 flex items-end gap-1.5">
                  {Object.entries(analytics.monthly_trend).map(([month, n]) => {
                    const max = Math.max(...Object.values(analytics.monthly_trend));
                    return (
                      <div key={month} className="flex flex-col items-center gap-1">
                        <div className="w-7 rounded-t bg-sky" style={{ height: `${Math.max(6, (n / max) * 56)}px` }} />
                        <span className="mono text-[9px] text-muted">{month.slice(5)}</span>
                      </div>
                    );
                  })}
                </div>
              </>
            )}
          </div>
        </div>
      )}

      {/* Pipeline */}
      <h2 className="mt-10 text-sm font-semibold uppercase tracking-widest text-muted">Ingestion pipeline</h2>
      {rows.length === 0 ? (
        <div className="mt-3"><EmptyState title="No transcripts yet" body="Paste a transcript or drop a Parakeet export above to start filling the bank." /></div>
      ) : (
        <div className="mt-3 divide-y divide-line rounded-[14px] border border-line bg-white">
          {rows.map((t) => (
            <div key={t.id} className="flex items-center gap-3 px-5 py-3">
              <div className="min-w-0 flex-1">
                <p className="truncate text-sm text-ink">
                  {t.original_name ?? `${t.source} transcript`} {t.role_title && <span className="text-muted">· {t.role_title}</span>}
                </p>
                <p className="mono text-[11px] uppercase tracking-widest text-muted">
                  {t.source} · {t.uploader ?? "—"} · {t.questions_found} q
                  {t.parse_error && <span className="text-warn"> · {t.parse_error}</span>}
                </p>
              </div>
              <StatusPill status={t.status} />
            </div>
          ))}
        </div>
      )}

      {/* Approved sample */}
      <h2 className="mt-10 text-sm font-semibold uppercase tracking-widest text-muted">Live in the bank</h2>
      {approved.length === 0 ? (
        <p className="mt-3 text-sm text-muted">No approved questions yet.</p>
      ) : (
        <div className="mt-3 divide-y divide-line rounded-[14px] border border-line bg-white">
          {approved.slice(0, 15).map((q) => (
            <div key={q.id} className="flex items-center gap-3 px-5 py-3">
              <div className="min-w-0 flex-1">
                <p className="truncate text-sm text-ink">{q.question}</p>
                <p className="mono text-[11px] uppercase tracking-widest text-muted">
                  {q.role_title} · {q.topic_tags.join(" · ")}
                </p>
              </div>
              <span className="mono text-xs text-muted">{q.asked_count}×</span>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
