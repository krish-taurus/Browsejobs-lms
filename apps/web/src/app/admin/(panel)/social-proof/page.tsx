"use client";

import { useEffect, useMemo, useRef, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ApiError, apiBlob, apiJson } from "@/lib/api";

/**
 * Per-course social-proof authoring: placement stories (+ WhatsApp screenshot
 * upload), the interview-question bank (paste rounds → bulk save), and
 * course-tagged reviews. Drives the public course-page section.
 */

type Course = { id: number; code: string; name: string; slug: string; status: string };
type Story = {
  id: number; student_name: string; before_label: string; after_role: string;
  package_label: string | null; company_name: string | null; company_color: string | null;
  rounds: number | null; quote: string | null; screenshot_url: string | null;
  has_screenshot: boolean; consent: boolean; is_published: boolean;
};
type QRound = { round_no: number; round_name: string; company: string | null; questions: string[] };
type Review = { id: number; author_name: string; author_meta: string | null; rating: number; body: string; source: string; is_active: boolean };

const input = "w-full rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust";
const label = "text-xs font-semibold text-muted";
const SOURCES = ["google", "justdial", "whatsapp", "platform"] as const;

export default function SocialProofAdminPage() {
  const [courseId, setCourseId] = useState<number | null>(null);
  const { data: courses } = useQuery({
    queryKey: ["admin", "courses"],
    queryFn: () => apiJson<{ data: Course[] }>("/api/v1/admin/courses"),
  });

  useEffect(() => {
    if (courseId === null && courses?.data?.length) setCourseId(courses.data[0].id);
  }, [courses, courseId]);

  return (
    <div className="mx-auto max-w-3xl">
      <h1 className="display text-2xl text-ink">Social proof</h1>
      <p className="mt-1 text-sm text-ink2/70">
        Placement stories, interview questions, and reviews shown on each course page. Real, consented content only.
      </p>

      <BulkStoryImport />

      <div className="mt-6">
        <label className={label}>Course</label>
        <select className={`${input} mt-1`} value={courseId ?? ""} onChange={(e) => setCourseId(Number(e.target.value))}>
          {(courses?.data ?? []).map((c) => (
            <option key={c.id} value={c.id}>{c.name} ({c.code})</option>
          ))}
        </select>
      </div>

      {courseId !== null && (
        <div className="mt-6 space-y-10">
          <StoriesSection courseId={courseId} />
          <QuestionsSection courseId={courseId} />
          <ReviewsSection courseId={courseId} />
        </div>
      )}
    </div>
  );
}

/* ---------------- Bulk story upload ---------------- */

function BulkStoryImport() {
  const qc = useQueryClient();
  const [busy, setBusy] = useState(false);
  const [msg, setMsg] = useState<string | null>(null);
  const [err, setErr] = useState<string | null>(null);

  async function downloadTemplate() {
    setErr(null);
    try {
      const blob = await apiBlob("/api/v1/admin/placement-stories/template");
      const url = URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = "browsejobs-placement-stories-template.csv";
      document.body.appendChild(a);
      a.click();
      a.remove();
      URL.revokeObjectURL(url);
    } catch {
      setErr("Could not download the template.");
    }
  }

  async function upload(file: File) {
    setBusy(true);
    setMsg(null);
    setErr(null);
    try {
      const fd = new FormData();
      fd.set("file", file);
      const res = await apiJson<{ data: { created: number; updated: number; published: number; skipped: { row: number; reason: string }[] } }>(
        "/api/v1/admin/placement-stories/import",
        { method: "POST", body: fd },
      );
      const d = res.data;
      setMsg(
        `Imported ${d.created} new · ${d.updated} updated · ${d.published} published` +
          (d.skipped.length ? ` · ${d.skipped.length} skipped (${d.skipped.slice(0, 3).map((s) => `row ${s.row}`).join(", ")}${d.skipped.length > 3 ? "…" : ""})` : ""),
      );
      void qc.invalidateQueries({ queryKey: ["admin"] });
    } catch (e) {
      setErr(e instanceof ApiError ? (e.firstError ?? e.message) : "Upload failed.");
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="mt-4 rounded-[14px] border border-line bg-white p-5">
      <h2 className="display text-lg text-ink">Bulk upload stories</h2>
      <p className="mt-1 text-sm text-muted">
        Capture every card in a spreadsheet and upload them at once. Set <span className="mono">course_slug</span> per row;
        rows with <span className="mono">consent = yes</span> publish, others land as drafts. CSV or Excel (.xlsx).
      </p>
      <div className="mt-3 flex flex-wrap items-center gap-3">
        <button
          type="button"
          onClick={downloadTemplate}
          className="rounded-full border border-line px-4 py-2 text-sm font-semibold text-ink transition-colors hover:border-trust hover:text-trust"
        >
          ↓ Download template
        </button>
        <label className={`cursor-pointer rounded-full bg-trust px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-deep ${busy ? "opacity-50" : ""}`}>
          {busy ? "Uploading…" : "↑ Upload filled sheet"}
          <input
            type="file"
            accept=".csv,.xlsx"
            className="hidden"
            disabled={busy}
            onChange={(e) => {
              const f = e.target.files?.[0];
              if (f) void upload(f);
              e.target.value = "";
            }}
          />
        </label>
      </div>
      {msg && <p className="mono mt-3 text-xs text-verify">{msg}</p>}
      {err && <p className="mt-3 rounded-[10px] bg-warn/10 px-3 py-2 text-sm text-warn">{err}</p>}
    </div>
  );
}

/* ---------------- Placement stories ---------------- */

const emptyStory = {
  student_name: "", before_label: "", after_role: "", package_label: "", company_name: "",
  company_color: "#1B6DF0", rounds: "", quote: "", consent: false, is_published: false,
};

function StoriesSection({ courseId }: { courseId: number }) {
  const qc = useQueryClient();
  const [error, setError] = useState<string | null>(null);
  const [form, setForm] = useState({ ...emptyStory });
  const key = ["admin", "placement-stories", courseId];

  const { data } = useQuery({
    queryKey: key,
    queryFn: () => apiJson<{ data: Story[] }>(`/api/v1/admin/courses/${courseId}/placement-stories`),
  });
  const refresh = () => void qc.invalidateQueries({ queryKey: key });
  const onError = (e: unknown) => setError(e instanceof ApiError ? (e.firstError ?? e.message) : "Failed.");

  const body = () => ({
    ...form,
    package_label: form.package_label || null,
    company_name: form.company_name || null,
    rounds: form.rounds === "" ? null : Number(form.rounds),
    quote: form.quote || null,
  });

  const create = useMutation({
    mutationFn: () => apiJson(`/api/v1/admin/courses/${courseId}/placement-stories`, { method: "POST", body: JSON.stringify(body()) }),
    onSuccess: () => { setError(null); setForm({ ...emptyStory }); refresh(); },
    onError,
  });
  const del = useMutation({
    mutationFn: (id: number) => apiJson(`/api/v1/admin/placement-stories/${id}`, { method: "DELETE" }),
    onSuccess: refresh, onError,
  });
  const publish = useMutation({
    mutationFn: (s: Story) => apiJson(`/api/v1/admin/placement-stories/${s.id}`, {
      method: "PUT",
      body: JSON.stringify({
        student_name: s.student_name, before_label: s.before_label, after_role: s.after_role,
        package_label: s.package_label, company_name: s.company_name, company_color: s.company_color,
        rounds: s.rounds, quote: s.quote, consent: s.consent, is_published: !s.is_published,
      }),
    }),
    onSuccess: refresh, onError,
  });
  const upload = useMutation({
    mutationFn: ({ id, file }: { id: number; file: File }) => {
      const fd = new FormData();
      fd.append("image", file);
      return apiJson(`/api/v1/admin/placement-stories/${id}/screenshot`, { method: "POST", body: fd });
    },
    onSuccess: refresh, onError,
  });

  const stories = data?.data ?? [];
  const ready = form.student_name && form.before_label && form.after_role;

  return (
    <section>
      <h2 className="display text-lg text-ink">Placement stories</h2>
      {error && <p className="mt-2 rounded-[10px] bg-warn/10 px-3 py-2 text-sm text-warn">{error}</p>}

      <div className="mt-3 space-y-2">
        {stories.map((s) => (
          <div key={s.id} className="rounded-[14px] border border-line bg-white p-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
              <div>
                <span className="font-semibold text-ink">{s.student_name}</span>{" "}
                <span className="text-sm text-muted">{s.before_label} → {s.after_role}</span>
                <div className="mono mt-0.5 text-xs text-muted">
                  {s.package_label ?? "—"} · {s.company_name ?? "—"} · {s.rounds ?? "?"} rounds
                  {!s.consent && <span className="text-warn"> · no consent</span>}
                </div>
              </div>
              <div className="flex items-center gap-2 text-xs font-semibold">
                <span className={`mono rounded-full px-2 py-0.5 ${s.is_published ? "bg-verify-bg text-verify" : "bg-paper text-muted"}`}>
                  {s.is_published ? "live" : "draft"}
                </span>
                <button onClick={() => publish.mutate(s)} disabled={!s.consent && !s.is_published} className="text-trust hover:underline disabled:opacity-40" title={!s.consent ? "Needs consent to publish" : ""}>
                  {s.is_published ? "Unpublish" : "Publish"}
                </button>
                <ScreenshotUpload onPick={(file) => upload.mutate({ id: s.id, file })} has={s.has_screenshot} />
                <button onClick={() => del.mutate(s.id)} className="text-warn hover:underline">Delete</button>
              </div>
            </div>
          </div>
        ))}
        {stories.length === 0 && <p className="rounded-[14px] border border-dashed border-line px-4 py-6 text-center text-sm text-muted">No stories yet.</p>}
      </div>

      {/* Add form */}
      <div className="mt-4 rounded-[14px] border border-line bg-white p-4">
        <p className="kicker text-muted">Add a story</p>
        <div className="mt-3 grid gap-2.5 sm:grid-cols-2">
          <div><label className={label}>Student name</label><input className={`${input} mt-1`} value={form.student_name} onChange={(e) => setForm({ ...form, student_name: e.target.value })} /></div>
          <div><label className={label}>After role</label><input className={`${input} mt-1`} value={form.after_role} onChange={(e) => setForm({ ...form, after_role: e.target.value })} placeholder="Data Engineer" /></div>
          <div className="sm:col-span-2"><label className={label}>Before (transformation)</label><input className={`${input} mt-1`} value={form.before_label} onChange={(e) => setForm({ ...form, before_label: e.target.value })} placeholder="Mechanical engineer, 2 yrs no job" /></div>
          <div><label className={label}>Package</label><input className={`${input} mt-1`} value={form.package_label} onChange={(e) => setForm({ ...form, package_label: e.target.value })} placeholder="₹11.5 LPA" /></div>
          <div><label className={label}>Company</label><input className={`${input} mt-1`} value={form.company_name} onChange={(e) => setForm({ ...form, company_name: e.target.value })} /></div>
          <div><label className={label}>Rounds</label><input type="number" min={1} max={20} className={`${input} mt-1`} value={form.rounds} onChange={(e) => setForm({ ...form, rounds: e.target.value })} /></div>
          <div><label className={label}>Chip colour</label><input type="color" className="mt-1 h-9 w-16 rounded border border-line" value={form.company_color} onChange={(e) => setForm({ ...form, company_color: e.target.value })} /></div>
          <div className="sm:col-span-2"><label className={label}>WhatsApp message (or upload a screenshot after saving)</label><textarea rows={2} className={`${input} mt-1`} value={form.quote} onChange={(e) => setForm({ ...form, quote: e.target.value })} /></div>
        </div>
        <div className="mt-3 flex flex-wrap items-center gap-4">
          <label className="flex items-center gap-2 text-sm text-ink"><input type="checkbox" checked={form.consent} onChange={(e) => setForm({ ...form, consent: e.target.checked })} className="h-4 w-4" /> Student consented to public use</label>
          <label className="flex items-center gap-2 text-sm text-ink"><input type="checkbox" checked={form.is_published} onChange={(e) => setForm({ ...form, is_published: e.target.checked })} className="h-4 w-4" /> Publish now</label>
          <button onClick={() => create.mutate()} disabled={create.isPending || !ready || (form.is_published && !form.consent)} className="rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white disabled:opacity-50">
            {create.isPending ? "Saving…" : "Add story"}
          </button>
        </div>
        {form.is_published && !form.consent && <p className="mt-2 text-xs text-warn">Consent is required to publish a real student&apos;s story.</p>}
      </div>
    </section>
  );
}

function ScreenshotUpload({ onPick, has }: { onPick: (file: File) => void; has: boolean }) {
  const ref = useRef<HTMLInputElement>(null);
  return (
    <>
      <button onClick={() => ref.current?.click()} className="text-trust hover:underline">{has ? "Replace image" : "Upload image"}</button>
      <input ref={ref} type="file" accept="image/*" className="hidden" onChange={(e) => { const f = e.target.files?.[0]; if (f) onPick(f); e.target.value = ""; }} />
    </>
  );
}

/* ---------------- Interview questions ---------------- */

function QuestionsSection({ courseId }: { courseId: number }) {
  const qc = useQueryClient();
  const [error, setError] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);
  const key = ["admin", "interview-questions", courseId];
  const { data } = useQuery({
    queryKey: key,
    queryFn: () => apiJson<{ data: QRound[] }>(`/api/v1/admin/courses/${courseId}/interview-questions`),
  });

  // Represent the whole bank as editable text: each round is "## N. Round name" then one question per line.
  const initial = useMemo(() => {
    const rounds = data?.data ?? [];
    return rounds.map((r) => `## ${r.round_no}. ${r.round_name}\n${r.questions.join("\n")}`).join("\n\n");
  }, [data]);
  const [text, setText] = useState<string | null>(null);
  useEffect(() => { setText(initial); }, [initial]);

  const save = useMutation({
    mutationFn: () => apiJson<{ data: QRound[] }>(`/api/v1/admin/courses/${courseId}/interview-questions`, {
      method: "PUT",
      body: JSON.stringify({ rounds: parseRounds(text ?? "") }),
    }),
    onSuccess: () => { setError(null); setSaved(true); void qc.invalidateQueries({ queryKey: key }); },
    onError: (e) => { setSaved(false); setError(e instanceof ApiError ? (e.firstError ?? e.message) : "Failed."); },
  });

  return (
    <section>
      <h2 className="display text-lg text-ink">Interview questions</h2>
      <p className="mt-1 text-sm text-muted">
        One round per <code className="mono text-xs">## 1. Round name</code> heading, then one question per line. Paste your bank and save — this replaces the whole set.
      </p>
      {error && <p className="mt-2 rounded-[10px] bg-warn/10 px-3 py-2 text-sm text-warn">{error}</p>}
      <textarea
        className={`${input} mt-3 min-h-64 font-mono text-xs`}
        value={text ?? ""}
        onChange={(e) => { setText(e.target.value); setSaved(false); }}
        placeholder={"## 1. Screening\nWalk me through a pipeline you built.\nSQL: 2nd highest salary?\n\n## 2. SQL & Python\nRANK vs DENSE_RANK?"}
      />
      <div className="mt-3 flex items-center gap-3">
        <button onClick={() => save.mutate()} disabled={save.isPending} className="rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white disabled:opacity-50">
          {save.isPending ? "Saving…" : "Save questions"}
        </button>
        {saved && <span className="mono text-xs text-verify">Saved ✓</span>}
      </div>
    </section>
  );
}

/** Parse the "## N. Name" + lines format into the API's rounds payload. */
function parseRounds(text: string): { round_no: number; round_name: string; questions: string[] }[] {
  const rounds: { round_no: number; round_name: string; questions: string[] }[] = [];
  let current: { round_no: number; round_name: string; questions: string[] } | null = null;
  for (const raw of text.split("\n")) {
    const line = raw.trim();
    if (!line) continue;
    const head = line.match(/^##\s*(\d+)\.\s*(.+)$/);
    if (head) {
      current = { round_no: Number(head[1]), round_name: head[2].trim(), questions: [] };
      rounds.push(current);
    } else if (current) {
      current.questions.push(line);
    }
  }
  return rounds.filter((r) => r.questions.length > 0);
}

/* ---------------- Reviews ---------------- */

const emptyReview = { author_name: "", author_meta: "", rating: 5, source: "google", body: "" };

function ReviewsSection({ courseId }: { courseId: number }) {
  const qc = useQueryClient();
  const [error, setError] = useState<string | null>(null);
  const [form, setForm] = useState({ ...emptyReview });
  const key = ["admin", "course-reviews", courseId];
  const { data } = useQuery({
    queryKey: key,
    queryFn: () => apiJson<{ data: Review[] }>(`/api/v1/admin/courses/${courseId}/course-reviews`),
  });
  const refresh = () => void qc.invalidateQueries({ queryKey: key });
  const onError = (e: unknown) => setError(e instanceof ApiError ? (e.firstError ?? e.message) : "Failed.");

  const create = useMutation({
    mutationFn: () => apiJson(`/api/v1/admin/courses/${courseId}/course-reviews`, {
      method: "POST",
      body: JSON.stringify({ ...form, author_meta: form.author_meta || null }),
    }),
    onSuccess: () => { setError(null); setForm({ ...emptyReview }); refresh(); },
    onError,
  });
  const del = useMutation({
    mutationFn: (id: number) => apiJson(`/api/v1/admin/course-reviews/${id}`, { method: "DELETE" }),
    onSuccess: refresh, onError,
  });

  const reviews = data?.data ?? [];

  return (
    <section>
      <h2 className="display text-lg text-ink">Reviews</h2>
      {error && <p className="mt-2 rounded-[10px] bg-warn/10 px-3 py-2 text-sm text-warn">{error}</p>}

      <div className="mt-3 space-y-2">
        {reviews.map((r) => (
          <div key={r.id} className="flex items-start justify-between gap-3 rounded-[14px] border border-line bg-white p-4">
            <div>
              <span className="font-semibold text-ink">{r.author_name}</span>{" "}
              <span className="text-xs text-muted">{r.author_meta}</span>
              <span className="text-amber"> {"★".repeat(r.rating)}</span>
              <span className="mono ml-2 rounded-full border border-line px-2 py-0.5 text-[9px] uppercase tracking-widest text-muted">{r.source}</span>
              <p className="mt-1 text-sm text-ink2/85">{r.body}</p>
            </div>
            <button onClick={() => del.mutate(r.id)} className="shrink-0 text-xs font-semibold text-warn hover:underline">Delete</button>
          </div>
        ))}
        {reviews.length === 0 && <p className="rounded-[14px] border border-dashed border-line px-4 py-6 text-center text-sm text-muted">No reviews yet.</p>}
      </div>

      <div className="mt-4 rounded-[14px] border border-line bg-white p-4">
        <p className="kicker text-muted">Add a review</p>
        <div className="mt-3 grid gap-2.5 sm:grid-cols-2">
          <div><label className={label}>Author name</label><input className={`${input} mt-1`} value={form.author_name} onChange={(e) => setForm({ ...form, author_name: e.target.value })} /></div>
          <div><label className={label}>Meta (e.g. Local Guide · 17 reviews)</label><input className={`${input} mt-1`} value={form.author_meta} onChange={(e) => setForm({ ...form, author_meta: e.target.value })} /></div>
          <div><label className={label}>Rating</label>
            <select className={`${input} mt-1`} value={form.rating} onChange={(e) => setForm({ ...form, rating: Number(e.target.value) })}>
              {[5, 4, 3, 2, 1].map((n) => <option key={n} value={n}>{n} ★</option>)}
            </select>
          </div>
          <div><label className={label}>Source</label>
            <select className={`${input} mt-1`} value={form.source} onChange={(e) => setForm({ ...form, source: e.target.value })}>
              {SOURCES.map((s) => <option key={s} value={s}>{s}</option>)}
            </select>
          </div>
          <div className="sm:col-span-2"><label className={label}>Review text</label><textarea rows={3} className={`${input} mt-1`} value={form.body} onChange={(e) => setForm({ ...form, body: e.target.value })} /></div>
        </div>
        <button onClick={() => create.mutate()} disabled={create.isPending || !form.author_name || !form.body} className="mt-3 rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white disabled:opacity-50">
          {create.isPending ? "Saving…" : "Add review"}
        </button>
      </div>
    </section>
  );
}
