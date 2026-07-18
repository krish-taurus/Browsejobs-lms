"use client";

import { useMemo, useRef, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ApiError, apiJson } from "@/lib/api";
import { EmptyState } from "@/components/ui/EmptyState";

type Trend = { skill: string; count: number; share: number };
type Course = { id: number; name: string };
type RecentJd = {
  id: number;
  role_title: string;
  company: string | null;
  location: string | null;
  seniority: string | null;
  source: string;
  course: string | null;
  skills: string[];
  parse_status: string;
  quality_score: number;
  ingested_at: string | null;
};
type Payload = {
  courses: Course[];
  trends: Trend[];
  sample_size: number;
  window_days: number;
  recent: RecentJd[];
};

type Form = { role_title: string; raw_jd: string; company: string; location: string; seniority: string; course_id: string };
const emptyForm: Form = { role_title: "", raw_jd: "", company: "", location: "", seniority: "", course_id: "" };

export default function AdminMarketPage() {
  const qc = useQueryClient();
  const [courseId, setCourseId] = useState("");
  const [form, setForm] = useState<Form>(emptyForm);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const fileRef = useRef<HTMLInputElement>(null);

  const query = courseId ? `?course_id=${courseId}` : "";
  const { data, isLoading } = useQuery({
    queryKey: ["admin", "market", courseId],
    queryFn: () => apiJson<{ data: Payload }>(`/api/v1/admin/market-jds${query}`),
  });

  const d = data?.data;
  const courses = d?.courses ?? [];
  const trends = useMemo(() => d?.trends ?? [], [d]);
  const maxCount = useMemo(() => Math.max(1, ...trends.map((t) => t.count)), [trends]);

  const refresh = () => qc.invalidateQueries({ queryKey: ["admin", "market"] });
  const onError = (err: unknown) => setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Something went wrong.");

  const add = useMutation({
    mutationFn: () =>
      apiJson<{ data: { imported: number; duplicates: number } }>("/api/v1/admin/market-jds", {
        method: "POST",
        body: JSON.stringify({
          role_title: form.role_title,
          raw_jd: form.raw_jd,
          company: form.company.trim() || null,
          location: form.location.trim() || null,
          seniority: form.seniority || null,
          course_id: form.course_id ? Number(form.course_id) : null,
        }),
      }),
    onSuccess: (r) => {
      setForm(emptyForm);
      setError(null);
      setNotice(r.data.imported ? "JD added — skills are being extracted." : "That JD was already in the corpus.");
      refresh();
    },
    onError,
  });

  const upload = useMutation({
    mutationFn: (file: File) => {
      const fd = new FormData();
      fd.append("file", file);
      if (courseId) fd.append("course_id", courseId);
      return apiJson<{ data: { imported: number; duplicates: number; skipped: number } }>("/api/v1/admin/market-jds/import", {
        method: "POST",
        body: fd,
      });
    },
    onSuccess: (r) => {
      setError(null);
      setNotice(`Imported ${r.data.imported} JD(s) · ${r.data.duplicates} duplicate(s) · ${r.data.skipped} skipped.`);
      if (fileRef.current) fileRef.current.value = "";
      refresh();
    },
    onError,
  });

  const canAdd = form.role_title.trim() && form.raw_jd.trim().length >= 40;

  return (
    <div className="mx-auto max-w-3xl">
      <p className="kicker text-trust">Curriculum intelligence</p>
      <h1 className="display mt-2 text-3xl text-ink">Job-market demand</h1>
      <p className="mt-1 text-sm text-muted">
        Import real job descriptions (manual or CSV) — never scraped. The AI extracts the skills each one
        asks for, and the aggregate frequency below shows what the market actually demands per role. This
        signal feeds the syllabus-recommendation engine.
      </p>

      {error && <p className="mt-4 text-sm text-warn">{error}</p>}
      {notice && <p className="mt-4 text-sm text-verify">{notice}</p>}

      {/* Filter */}
      <div className="mt-6 flex flex-wrap items-center gap-3">
        <select
          value={courseId}
          onChange={(e) => setCourseId(e.target.value)}
          className="rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust"
        >
          <option value="">All courses</option>
          {courses.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
        </select>
        <span className="mono text-[11px] uppercase tracking-widest text-muted">
          {d ? `${d.sample_size} JDs · last ${d.window_days}d` : "—"}
        </span>
      </div>

      {/* Demand trends */}
      <h2 className="mt-8 text-sm font-semibold uppercase tracking-widest text-muted">Skill demand</h2>
      {isLoading ? (
        <div className="mt-3 space-y-2">{Array.from({ length: 6 }).map((_, i) => <div key={i} className="shimmer h-8 rounded-[10px]" />)}</div>
      ) : trends.length === 0 ? (
        <div className="mt-3"><EmptyState title="No demand data yet" body="Import a few JDs for this course to see which skills the market asks for." /></div>
      ) : (
        <div className="mt-3 space-y-1.5 rounded-[14px] border border-line bg-white p-5">
          {trends.slice(0, 20).map((t) => (
            <div key={t.skill} className="flex items-center gap-3">
              <span className="w-40 truncate text-sm text-ink">{t.skill}</span>
              <div className="h-2 flex-1 overflow-hidden rounded-full bg-paper">
                <div className="h-full rounded-full bg-trust" style={{ width: `${(t.count / maxCount) * 100}%` }} />
              </div>
              <span className="mono w-16 text-right text-[11px] text-muted">{Math.round(t.share * 100)}%</span>
            </div>
          ))}
        </div>
      )}

      {/* Import */}
      <h2 className="mt-10 text-sm font-semibold uppercase tracking-widest text-muted">Add JDs</h2>
      <div className="mt-3 rounded-[14px] border border-line bg-white p-5">
        <div className="grid gap-3 sm:grid-cols-2">
          <input
            value={form.role_title}
            onChange={(e) => setForm({ ...form, role_title: e.target.value })}
            placeholder="Role title (e.g. Data Engineer)"
            className="rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust"
          />
          <select
            value={form.course_id}
            onChange={(e) => setForm({ ...form, course_id: e.target.value })}
            className="rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust"
          >
            <option value="">Map to course… (optional)</option>
            {courses.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
          </select>
          <input
            value={form.company}
            onChange={(e) => setForm({ ...form, company: e.target.value })}
            placeholder="Company (optional)"
            className="rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust"
          />
          <select
            value={form.seniority}
            onChange={(e) => setForm({ ...form, seniority: e.target.value })}
            className="rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust"
          >
            <option value="">Seniority… (optional)</option>
            {["fresher", "junior", "mid", "senior"].map((s) => <option key={s} value={s}>{s}</option>)}
          </select>
        </div>
        <textarea
          value={form.raw_jd}
          onChange={(e) => setForm({ ...form, raw_jd: e.target.value })}
          rows={4}
          placeholder="Paste the full job description here (min 40 characters)…"
          className="mt-3 w-full resize-none rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust"
        />
        <div className="mt-3 flex flex-wrap items-center gap-3">
          <button
            onClick={() => add.mutate()}
            disabled={!canAdd || add.isPending}
            className="rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white disabled:opacity-50"
          >
            {add.isPending ? "Adding…" : "Add JD"}
          </button>
          <span className="text-xs text-muted">or</span>
          <label className="cursor-pointer rounded-full border border-line bg-white px-4 py-2 text-sm font-semibold text-ink hover:border-trust">
            {upload.isPending ? "Importing…" : "Import CSV"}
            <input
              ref={fileRef}
              type="file"
              accept=".csv,text/csv"
              className="hidden"
              onChange={(e) => { const f = e.target.files?.[0]; if (f) upload.mutate(f); }}
            />
          </label>
          <span className="mono text-[11px] text-muted">CSV columns: role_title, raw_jd, company, location, seniority</span>
        </div>
      </div>

      {/* Recent */}
      <h2 className="mt-10 text-sm font-semibold uppercase tracking-widest text-muted">Recently imported</h2>
      {!isLoading && (d?.recent.length ?? 0) === 0 ? (
        <p className="mt-3 text-sm text-muted">Nothing imported yet.</p>
      ) : (
        <div className="mt-3 divide-y divide-line rounded-[14px] border border-line bg-white">
          {d?.recent.map((jd) => (
            <div key={jd.id} className="px-5 py-3">
              <div className="flex items-center gap-3">
                <div className="min-w-0 flex-1">
                  <p className="truncate text-sm text-ink">
                    {jd.role_title}{jd.company ? ` · ${jd.company}` : ""}
                  </p>
                  <p className="mono truncate text-[11px] uppercase tracking-widest text-muted">
                    {[jd.course, jd.seniority, jd.location, jd.source].filter(Boolean).join(" · ")}
                  </p>
                </div>
                <span className={`mono rounded-full px-2.5 py-0.5 text-[11px] uppercase tracking-widest ${
                  jd.parse_status === "parsed" ? "bg-verify-bg text-verify" : jd.parse_status === "failed" ? "bg-warn/10 text-warn" : "bg-paper text-muted"
                }`}>
                  {jd.parse_status}
                </span>
              </div>
              {jd.skills.length > 0 && (
                <div className="mt-2 flex flex-wrap gap-1.5">
                  {jd.skills.slice(0, 12).map((s) => (
                    <span key={s} className="mono rounded-full bg-sky px-2 py-0.5 text-[10px] text-deep">{s}</span>
                  ))}
                </div>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
