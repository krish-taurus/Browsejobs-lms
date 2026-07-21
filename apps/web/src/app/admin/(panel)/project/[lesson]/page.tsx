"use client";

import Link from "next/link";
import { use, useEffect, useRef, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ApiError, apiJson } from "@/lib/api";
import { ArchitectureView, type Architecture } from "@/components/project/ArchitectureView";

/**
 * Project-lesson authoring (admins with manage-curriculum). Capture the tech
 * stack + details, then attach an architecture — AI-generated (rendered to a
 * downloadable PDF) or an uploaded diagram. Both are offered to students as a
 * signed download.
 */

type Spec = {
  lesson_id: number;
  title: string;
  summary: string | null;
  tech_stack: string[];
  architecture: Architecture | null;
  architecture_source: "none" | "ai" | "upload";
  has_download: boolean;
  download_url: string | null;
} | null;

const COMMON_TECH = [
  "Python", "SQL", "PySpark", "Kafka", "Airflow", "dbt", "Snowflake", "Databricks",
  "PostgreSQL", "MongoDB", "Redis", "Docker", "Kubernetes", "AWS", "GCP", "Azure",
  "Terraform", "Power BI", "Tableau", "Pandas", "FastAPI", "React", "Next.js",
];

const inputCls = "w-full rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust";

export default function ProjectEditor({ params }: { params: Promise<{ lesson: string }> }) {
  const { lesson } = use(params);
  const qc = useQueryClient();
  const [error, setError] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);

  const [title, setTitle] = useState("");
  const [summary, setSummary] = useState("");
  const [tech, setTech] = useState<string[]>([]);
  const [customTech, setCustomTech] = useState("");
  const [arch, setArch] = useState<Architecture | null>(null);
  const fileRef = useRef<HTMLInputElement>(null);

  const { data } = useQuery({
    queryKey: ["admin", "project", lesson],
    queryFn: () => apiJson<{ data: Spec }>(`/api/v1/admin/lessons/${lesson}/project`),
  });
  const spec = data?.data ?? null;

  useEffect(() => {
    if (spec) {
      setTitle(spec.title);
      setSummary(spec.summary ?? "");
      setTech(spec.tech_stack);
      setArch(spec.architecture);
    }
  }, [spec]);

  const refresh = () => void qc.invalidateQueries({ queryKey: ["admin", "project", lesson] });
  const onError = (err: unknown) => setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Request failed.");

  const toggleTech = (t: string) => setTech((prev) => (prev.includes(t) ? prev.filter((x) => x !== t) : [...prev, t]));
  const addCustom = () => {
    const t = customTech.trim();
    if (t && !tech.includes(t)) setTech([...tech, t]);
    setCustomTech("");
  };

  const saveDetails = useMutation({
    mutationFn: (architecture?: Architecture) =>
      apiJson<{ data: Spec }>(`/api/v1/admin/lessons/${lesson}/project`, {
        method: "PUT",
        body: JSON.stringify({ title, summary: summary || null, tech_stack: tech, ...(architecture !== undefined ? { architecture } : {}) }),
      }),
    onSuccess: () => { setError(null); setSaved(true); refresh(); },
    onError,
  });

  const generate = useMutation({
    mutationFn: () => apiJson<{ data: Architecture }>(`/api/v1/admin/lessons/${lesson}/project/architecture`, { method: "POST", body: JSON.stringify({}) }),
    onSuccess: (res) => { setError(null); setArch(res.data); },
    onError,
  });

  const makePdf = useMutation({
    mutationFn: () => apiJson<{ data: { url: string | null } }>(`/api/v1/admin/lessons/${lesson}/project/pdf`, { method: "POST", body: JSON.stringify({}) }),
    onSuccess: (res) => { setError(null); if (res.data.url) window.open(res.data.url, "_blank"); refresh(); },
    onError,
  });

  const upload = useMutation({
    mutationFn: () => {
      const f = fileRef.current?.files?.[0];
      if (!f) throw new ApiError(422, { message: "Choose a file first." });
      const fd = new FormData();
      fd.append("file", f);
      return apiJson<{ data: { url: string | null } }>(`/api/v1/admin/lessons/${lesson}/project/upload`, { method: "POST", body: fd });
    },
    onSuccess: (res) => { setError(null); if (res.data.url) window.open(res.data.url, "_blank"); refresh(); },
    onError,
  });

  const detailsReady = title.trim().length > 0;

  return (
    <div className="mx-auto max-w-2xl">
      <Link href="/admin/curriculum" className="text-sm text-trust hover:underline">← Curriculum</Link>
      <h1 className="display mt-3 text-2xl text-ink">Capstone project</h1>
      <p className="mt-1 text-sm text-ink2/70">
        Define the tech stack and brief, then generate or upload an architecture students can download.
      </p>

      {error && <p className="mt-4 rounded-[10px] bg-warn/10 px-3 py-2 text-sm text-warn">{error}</p>}

      {/* 1. Details */}
      <div className="mt-4 space-y-4 rounded-2xl border border-line bg-white p-5">
        <p className="text-sm font-semibold text-ink">1. Project details</p>
        <div>
          <label className="text-xs font-semibold text-muted">Project title</label>
          <input className={`${inputCls} mt-1`} value={title} onChange={(e) => { setTitle(e.target.value); setSaved(false); }}
            placeholder="e.g. Real-time sales analytics pipeline" />
        </div>
        <div>
          <label className="text-xs font-semibold text-muted">The brief</label>
          <textarea className={`${inputCls} mt-1`} rows={5} value={summary} onChange={(e) => { setSummary(e.target.value); setSaved(false); }}
            placeholder="What should the student build? Inputs, outputs, and what 'done' looks like." />
        </div>
        <div>
          <label className="text-xs font-semibold text-muted">Tech stack</label>
          <div className="mt-2 flex flex-wrap gap-1.5">
            {COMMON_TECH.map((t) => (
              <button key={t} onClick={() => { toggleTech(t); setSaved(false); }}
                className={`mono rounded-full px-2.5 py-1 text-[12px] transition-colors ${tech.includes(t) ? "bg-trust text-white" : "border border-line text-ink hover:bg-paper"}`}>
                {t}
              </button>
            ))}
          </div>
          {tech.filter((t) => !COMMON_TECH.includes(t)).length > 0 && (
            <div className="mt-2 flex flex-wrap gap-1.5">
              {tech.filter((t) => !COMMON_TECH.includes(t)).map((t) => (
                <button key={t} onClick={() => { toggleTech(t); setSaved(false); }}
                  className="mono rounded-full bg-trust px-2.5 py-1 text-[12px] text-white">{t} ✕</button>
              ))}
            </div>
          )}
          <div className="mt-2 flex gap-2">
            <input className={`${inputCls} flex-1`} value={customTech} onChange={(e) => setCustomTech(e.target.value)}
              onKeyDown={(e) => { if (e.key === "Enter") { e.preventDefault(); addCustom(); } }}
              placeholder="Add another tool…" />
            <button onClick={addCustom} className="rounded-full border border-line px-4 py-2 text-sm font-semibold text-ink hover:bg-paper">Add</button>
          </div>
        </div>
        <div className="flex items-center gap-3">
          <button onClick={() => saveDetails.mutate(undefined)} disabled={saveDetails.isPending || !detailsReady}
            className="rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white transition-colors hover:bg-deep disabled:opacity-50">
            {saveDetails.isPending ? "Saving…" : "Save details"}
          </button>
          {saved && <span className="mono text-xs text-verify">Saved ✓</span>}
        </div>
      </div>

      {/* 2. Architecture */}
      {spec && (
        <div className="mt-6 space-y-4 rounded-2xl border border-line bg-white p-5">
          <div className="flex items-center justify-between">
            <p className="text-sm font-semibold text-ink">2. Architecture</p>
            <button onClick={() => generate.mutate()} disabled={generate.isPending}
              className="rounded-full border border-line px-4 py-2 text-xs font-semibold text-ink hover:bg-paper disabled:opacity-50">
              {generate.isPending ? "Designing…" : "✨ Generate with AI"}
            </button>
          </div>
          <p className="text-xs text-muted">
            AI drafts the architecture from your brief and tech stack. Review it, save, then create the downloadable PDF — or upload your own diagram.
          </p>

          {arch && arch.layers && arch.layers.length > 0 && (
            <div className="rounded-[14px] border border-line bg-paper p-4">
              <ArchitectureView architecture={arch} />
              <div className="mt-4 flex flex-wrap gap-2">
                <button onClick={() => saveDetails.mutate(arch)} disabled={saveDetails.isPending}
                  className="rounded-full border border-line px-4 py-2 text-sm font-semibold text-ink hover:bg-paper disabled:opacity-50">
                  Save architecture
                </button>
                <button onClick={() => makePdf.mutate()} disabled={makePdf.isPending}
                  className="rounded-full bg-trust px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-deep disabled:opacity-50">
                  {makePdf.isPending ? "Building…" : "Generate PDF"}
                </button>
              </div>
            </div>
          )}

          <div className="border-t border-line pt-4">
            <p className="text-xs font-semibold text-muted">Or upload a diagram (PNG / JPG / PDF)</p>
            <div className="mt-2 flex flex-wrap items-center gap-2">
              <input ref={fileRef} type="file" accept="application/pdf,image/png,image/jpeg,.pdf,.png,.jpg,.jpeg" className="text-sm text-muted" />
              <button onClick={() => upload.mutate()} disabled={upload.isPending}
                className="rounded-full border border-line px-4 py-2 text-sm font-semibold text-ink hover:bg-paper disabled:opacity-50">
                {upload.isPending ? "Uploading…" : "Upload"}
              </button>
            </div>
          </div>

          {spec.has_download && (
            <p className="mono text-[10px] uppercase tracking-widest text-verify">
              Downloadable architecture attached ({spec.architecture_source === "upload" ? "uploaded file" : "generated PDF"})
            </p>
          )}
        </div>
      )}
    </div>
  );
}
