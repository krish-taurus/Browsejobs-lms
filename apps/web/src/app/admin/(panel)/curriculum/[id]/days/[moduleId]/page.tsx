"use client";

import { use, useRef, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ApiError, apiJson } from "@/lib/api";
import { EmptyState } from "@/components/ui/EmptyState";

type NotesStatus = { lesson_id: number; status: string; has_content: boolean; has_pdf: boolean; pdf_uploaded: boolean };
type AssignmentStatus = { lesson_id: number; quiz_id: number; status: string; questions: number; has_pdf: boolean; pdf_uploaded: boolean };
type Day = { id: number; day_number: number | null; name: string; summary: string | null; keywords: string[]; notes: NotesStatus | null; assignment: AssignmentStatus | null };
type Payload = { module: { id: number; name: string; course: string | null; course_id: number }; days: Day[] };

const SUGGESTED = ["basics", "setup", "syntax", "functions", "practice", "project", "revision", "interview questions"];

export default function DayBuilderPage({ params }: { params: Promise<{ id: string; moduleId: string }> }) {
  const { id, moduleId } = use(params);
  const qc = useQueryClient();
  const [form, setForm] = useState({ dayNumber: "", name: "", summary: "", keywords: [] as string[] });
  const [keywordInput, setKeywordInput] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [busyDay, setBusyDay] = useState<string | null>(null);
  const fileRefs = useRef<Record<number, HTMLInputElement | null>>({});

  const { data, isLoading } = useQuery({
    queryKey: ["admin", "day-builder", moduleId],
    queryFn: () => apiJson<{ data: Payload }>(`/api/v1/admin/modules/${moduleId}/days`),
  });

  const mod = data?.data.module;
  const days = data?.data.days ?? [];
  const nextDay = days.reduce((max, d) => Math.max(max, d.day_number ?? 0), 0) + 1;
  const refresh = () => qc.invalidateQueries({ queryKey: ["admin", "day-builder", moduleId] });
  const onError = (err: unknown) => setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Something went wrong.");

  const addKeyword = (raw: string) => {
    const k = raw.trim().toLowerCase();
    if (k && !form.keywords.includes(k) && form.keywords.length < 12) {
      setForm((f) => ({ ...f, keywords: [...f.keywords, k] }));
    }
    setKeywordInput("");
  };

  const plan = useMutation({
    mutationFn: () =>
      apiJson<{ data: { name: string; summary: string; subtopics: string[]; keywords: string[] } }>(
        `/api/v1/admin/modules/${moduleId}/days/plan`,
        { method: "POST", body: JSON.stringify({ keywords: form.keywords, day_number: Number(form.dayNumber) || nextDay }) },
      ),
    onSuccess: (r) => {
      const d = r.data;
      const summary = [d.summary, d.subtopics.length ? "Covers: " + d.subtopics.join(" · ") : ""].filter(Boolean).join("\n");
      setForm((f) => ({
        ...f,
        dayNumber: f.dayNumber || String(nextDay),
        name: d.name,
        summary,
        keywords: d.keywords.length ? d.keywords : f.keywords,
      }));
      setNotice("Draft ready — review it, edit anything, then save the day.");
      setError(null);
    },
    onError,
  });

  const saveDay = useMutation({
    mutationFn: () =>
      apiJson(`/api/v1/admin/modules/${moduleId}/days`, {
        method: "POST",
        body: JSON.stringify({
          name: form.name,
          day_number: Number(form.dayNumber) || nextDay,
          summary: form.summary || undefined,
          keywords: form.keywords.length ? form.keywords : undefined,
        }),
      }),
    onSuccess: () => {
      setForm({ dayNumber: "", name: "", summary: "", keywords: [] });
      setNotice("Day saved.");
      setError(null);
      refresh();
    },
    onError,
  });

  const act = useMutation({
    mutationFn: ({ path, body }: { key: string; path: string; body?: unknown }) =>
      apiJson(path, { method: "POST", body: body === undefined ? undefined : JSON.stringify(body) }),
    onMutate: (v) => setBusyDay(v.key),
    onSettled: () => setBusyDay(null),
    onSuccess: () => { setError(null); refresh(); },
    onError,
  });

  const uploadPdf = useMutation({
    mutationFn: ({ quizId, file }: { quizId: number; file: File }) => {
      const fd = new FormData();
      fd.append("file", file);
      return apiJson(`/api/v1/admin/quizzes/${quizId}/pdf/upload`, { method: "POST", body: fd });
    },
    onSuccess: () => { setNotice("Assignment paper attached."); setError(null); refresh(); },
    onError,
  });

  const download = async (quizId: number) => {
    try {
      const r = await apiJson<{ data: { url: string } }>(`/api/v1/admin/quizzes/${quizId}/pdf`);
      window.open(r.data.url, "_blank", "noopener");
    } catch (err) {
      onError(err);
    }
  };

  const statusPill = (status: string) => (
    <span className={`mono rounded-full px-2 py-0.5 text-[10px] uppercase tracking-widest ${status === "approved" ? "bg-verify-bg text-verify" : "bg-sky text-deep"}`}>
      {status}
    </span>
  );

  return (
    <div className="mx-auto max-w-3xl">
      <a href={`/admin/curriculum/${id}`} className="text-xs font-semibold text-trust hover:underline">← Back to curriculum</a>
      <p className="kicker mt-3 text-trust">Day-by-day builder</p>
      <h1 className="display mt-2 text-3xl text-ink">{mod ? mod.name : "…"}</h1>
      <p className="mt-1 text-sm text-muted">
        {mod?.course && <>{mod.course} · </>}Plan this module one teaching day at a time. Type a day
        yourself, or give the AI a few keywords and it drafts the day for you — you review and edit
        before anything is saved. Notes and assignments stay drafts until a trainer approves them.
      </p>

      {error && <p className="mt-4 text-sm text-warn">{error}</p>}
      {notice && !error && <p className="mt-4 text-sm text-verify">{notice}</p>}

      {/* Add a day */}
      <div className="mt-6 rounded-[14px] border border-line bg-white p-5">
        <p className="kicker text-muted">Add a day</p>
        <div className="mt-3 grid gap-3 sm:grid-cols-4">
          <input value={form.dayNumber} onChange={(e) => setForm({ ...form, dayNumber: e.target.value })} inputMode="numeric" placeholder={`Day # (next: ${nextDay})`} className="rounded-[10px] border border-line bg-white px-3 py-2 text-sm outline-none focus:border-trust" />
          <input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} placeholder="Topic, e.g. Functions and scope" className="rounded-[10px] border border-line bg-white px-3 py-2 text-sm outline-none focus:border-trust sm:col-span-3" />
        </div>
        <textarea value={form.summary} onChange={(e) => setForm({ ...form, summary: e.target.value })} placeholder="What this day covers (2–3 plain sentences students will read)…" rows={3} className="mt-3 w-full rounded-[10px] border border-line bg-white px-3 py-2 text-sm outline-none focus:border-trust" />

        {/* Keywords */}
        <div className="mt-3">
          <div className="flex flex-wrap items-center gap-1.5">
            {form.keywords.map((k) => (
              <button key={k} onClick={() => setForm((f) => ({ ...f, keywords: f.keywords.filter((x) => x !== k) }))} title="Remove" className="mono rounded-full bg-sky px-2.5 py-1 text-[11px] text-deep hover:bg-line">
                {k} ×
              </button>
            ))}
            <input
              value={keywordInput}
              onChange={(e) => setKeywordInput(e.target.value)}
              onKeyDown={(e) => { if (e.key === "Enter" || e.key === ",") { e.preventDefault(); addKeyword(keywordInput); } }}
              onBlur={() => keywordInput && addKeyword(keywordInput)}
              placeholder={form.keywords.length ? "Add keyword…" : "Keywords for this day (press Enter after each)…"}
              className="min-w-44 flex-1 rounded-[10px] border border-line bg-white px-3 py-1.5 text-sm outline-none focus:border-trust"
            />
          </div>
          <div className="mt-2 flex flex-wrap items-center gap-1.5">
            <span className="mono text-[10px] uppercase tracking-widest text-muted">Quick add:</span>
            {SUGGESTED.filter((s) => !form.keywords.includes(s)).map((s) => (
              <button key={s} onClick={() => addKeyword(s)} className="rounded-full border border-line bg-white px-2.5 py-0.5 text-[11px] text-ink hover:border-trust">
                + {s}
              </button>
            ))}
          </div>
        </div>

        <div className="mt-4 flex flex-wrap items-center gap-3">
          <button onClick={() => saveDay.mutate()} disabled={!form.name.trim() || saveDay.isPending} className="rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white disabled:opacity-50">
            {saveDay.isPending ? "Saving…" : "Save day"}
          </button>
          <button onClick={() => plan.mutate()} disabled={form.keywords.length === 0 || plan.isPending} title={form.keywords.length === 0 ? "Add at least one keyword first" : undefined} className="rounded-full border border-line bg-white px-5 py-2 text-sm font-semibold text-trust hover:border-trust disabled:opacity-50">
            {plan.isPending ? "Drafting…" : "✦ Generate with AI from keywords"}
          </button>
        </div>
      </div>

      {/* Days */}
      {isLoading ? (
        <div className="mt-6 space-y-2">{Array.from({ length: 3 }).map((_, i) => <div key={i} className="shimmer h-24 rounded-[14px]" />)}</div>
      ) : days.length === 0 ? (
        <div className="mt-6"><EmptyState title="No days yet" body="Add Day 1 above — manually, or from keywords with AI." /></div>
      ) : (
        <div className="mt-6 space-y-3">
          {days.map((d) => (
            <div key={d.id} className="rounded-[14px] border border-line bg-white p-5">
              <div className="flex flex-wrap items-center gap-2">
                <span className="mono rounded-full bg-ink px-2.5 py-0.5 text-[10px] uppercase tracking-widest text-white">
                  {d.day_number ? `Day ${d.day_number}` : "Unscheduled"}
                </span>
                <p className="text-sm font-semibold text-ink">{d.name}</p>
              </div>
              {d.summary && <p className="mt-2 whitespace-pre-line text-sm text-ink2">{d.summary}</p>}
              {d.keywords.length > 0 && (
                <div className="mt-2 flex flex-wrap gap-1">
                  {d.keywords.map((k) => <span key={k} className="mono rounded-full bg-sky px-2 py-0.5 text-[10px] text-deep">{k}</span>)}
                </div>
              )}

              {/* Notes row */}
              <div className="mt-4 flex flex-wrap items-center gap-3 border-t border-line pt-3">
                <span className="mono w-24 text-[10px] uppercase tracking-widest text-muted">Notes</span>
                {d.notes ? statusPill(d.notes.status) : <span className="text-xs text-muted">none yet</span>}
                <button
                  onClick={() => act.mutate({ key: `notes-${d.id}`, path: `/api/v1/admin/topics/${d.id}/simple-notes` })}
                  disabled={busyDay === `notes-${d.id}`}
                  className="rounded-full border border-line bg-white px-3 py-1 text-xs font-semibold text-trust hover:border-trust disabled:opacity-50"
                >
                  {busyDay === `notes-${d.id}` ? "Writing…" : d.notes?.has_content ? "Regenerate beginner notes" : "✦ Generate beginner notes"}
                </button>
                {d.notes && (
                  <a href={`/admin/content/${d.notes.lesson_id}`} className="text-xs font-semibold text-trust hover:underline">
                    Review, approve &amp; PDF →
                  </a>
                )}
              </div>

              {/* Assignment row */}
              <div className="mt-3 flex flex-wrap items-center gap-3 border-t border-line pt-3">
                <span className="mono w-24 text-[10px] uppercase tracking-widest text-muted">Assignment</span>
                {d.assignment ? (
                  <>
                    {statusPill(d.assignment.status)}
                    <span className="mono text-xs text-muted">{d.assignment.questions} Qs</span>
                  </>
                ) : (
                  <span className="text-xs text-muted">none yet</span>
                )}
                <button
                  onClick={() => act.mutate({ key: `quiz-${d.id}`, path: `/api/v1/admin/topics/${d.id}/assignment/generate` })}
                  disabled={busyDay === `quiz-${d.id}`}
                  className="rounded-full border border-line bg-white px-3 py-1 text-xs font-semibold text-trust hover:border-trust disabled:opacity-50"
                >
                  {busyDay === `quiz-${d.id}` ? "Writing…" : d.assignment ? "Regenerate MCQs" : "✦ Generate MCQ assignment"}
                </button>
                {d.assignment && (
                  <>
                    <a href={`/admin/quizzes/${d.assignment.lesson_id}`} className="text-xs font-semibold text-trust hover:underline">
                      Review &amp; approve →
                    </a>
                    <button
                      onClick={() => act.mutate({ key: `pdf-${d.id}`, path: `/api/v1/admin/quizzes/${d.assignment!.quiz_id}/pdf` })}
                      disabled={busyDay === `pdf-${d.id}` || d.assignment.questions === 0}
                      className="text-xs font-semibold text-trust hover:underline disabled:opacity-50"
                    >
                      {busyDay === `pdf-${d.id}` ? "Rendering…" : "Render paper PDF"}
                    </button>
                    <label className="cursor-pointer text-xs font-semibold text-trust hover:underline">
                      Attach own PDF
                      <input
                        ref={(el) => { fileRefs.current[d.id] = el; }}
                        type="file" accept="application/pdf" className="hidden"
                        onChange={(e) => { const f = e.target.files?.[0]; if (f && d.assignment) uploadPdf.mutate({ quizId: d.assignment.quiz_id, file: f }); }}
                      />
                    </label>
                    {d.assignment.has_pdf && (
                      <button onClick={() => download(d.assignment!.quiz_id)} className="text-xs font-semibold text-verify hover:underline">
                        Download paper{d.assignment.pdf_uploaded ? " (uploaded)" : ""}
                      </button>
                    )}
                  </>
                )}
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
