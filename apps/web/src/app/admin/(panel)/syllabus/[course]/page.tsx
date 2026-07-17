"use client";

import Link from "next/link";
import { use, useEffect, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ApiError, apiJson } from "@/lib/api";

type Syllabus = {
  id: number;
  course_id: number;
  title: string;
  content: string | null;
  status: "draft" | "approved";
  is_stale: boolean;
  render_status: string;
  version: number;
  downloadable: boolean;
  download_url: string | null;
};

export default function SyllabusBuilderPage({ params }: { params: Promise<{ course: string }> }) {
  const { course } = use(params);
  const qc = useQueryClient();
  const [error, setError] = useState<string | null>(null);
  const [draft, setDraft] = useState("");

  const query = useQuery({
    queryKey: ["admin", "syllabus", course],
    queryFn: () => apiJson<{ data: Syllabus | null }>(`/api/v1/admin/courses/${course}/syllabus`),
  });
  const syllabus = query.data?.data ?? null;

  useEffect(() => {
    if (syllabus?.content != null) setDraft(syllabus.content);
  }, [syllabus?.content]);

  const refresh = () => void qc.invalidateQueries({ queryKey: ["admin", "syllabus", course] });
  const onError = (err: unknown) => setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Request failed.");

  const generate = useMutation({
    mutationFn: () => apiJson(`/api/v1/admin/courses/${course}/syllabus/generate`, { method: "POST", body: JSON.stringify({}) }),
    onSuccess: () => { setError(null); setTimeout(refresh, 1500); },
    onError,
  });
  const save = useMutation({
    mutationFn: () => apiJson(`/api/v1/admin/courses/${course}/syllabus`, { method: "PATCH", body: JSON.stringify({ content: draft }) }),
    onSuccess: () => { setError(null); refresh(); },
    onError,
  });
  const approve = useMutation({
    mutationFn: () => apiJson(`/api/v1/admin/syllabuses/${syllabus!.id}/approve`, { method: "POST", body: JSON.stringify({}) }),
    onSuccess: () => { setError(null); refresh(); },
    onError,
  });

  return (
    <div className="mx-auto max-w-2xl">
      <Link href="/admin/syllabus" className="text-sm text-trust hover:underline">← Course syllabus</Link>

      {query.isLoading ? (
        <div className="mt-4 shimmer h-64 rounded-[14px]" />
      ) : syllabus === null ? (
        <div className="mt-6 rounded-2xl border border-line bg-white p-6">
          <h1 className="display text-2xl text-ink">No syllabus yet</h1>
          <p className="mt-1 text-sm text-muted">Generate a syllabus for this course from its curriculum with AI, then edit and approve.</p>
          {error && <p className="mt-3 text-sm text-warn">{error}</p>}
          <button onClick={() => generate.mutate()} disabled={generate.isPending} className="mt-4 rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white disabled:opacity-50">
            {generate.isPending ? "Generating…" : "Generate with AI"}
          </button>
        </div>
      ) : (
        <>
          <div className="mt-4 flex flex-wrap items-center justify-between gap-3">
            <div>
              <h1 className="display text-2xl text-ink">{syllabus.title}</h1>
              <p className="mono text-[11px] uppercase tracking-widest text-muted">
                {syllabus.status} · v{syllabus.version} · {syllabus.render_status}
                {syllabus.is_stale ? " · stale — regenerate & re-approve" : ""}
              </p>
            </div>
            <div className="flex gap-2">
              <button onClick={() => generate.mutate()} disabled={generate.isPending} className="rounded-full border border-line px-4 py-2 text-sm font-semibold text-ink hover:bg-paper disabled:opacity-50">
                {generate.isPending ? "Generating…" : "Regenerate"}
              </button>
              <button onClick={() => approve.mutate()} disabled={approve.isPending || (syllabus.status === "approved" && !syllabus.is_stale) || !draft.trim()} className="rounded-full bg-verify px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">
                {syllabus.status === "approved" && !syllabus.is_stale ? "Approved ✓" : "Approve"}
              </button>
            </div>
          </div>

          {syllabus.downloadable && syllabus.download_url && (
            <a href={syllabus.download_url} target="_blank" rel="noopener noreferrer" className="mono mt-3 inline-block text-[11px] uppercase tracking-widest text-trust hover:underline">
              Download current ↗
            </a>
          )}

          {error && <p className="mt-3 text-sm text-warn">{error}</p>}

          <div className="mt-5 rounded-2xl border border-line bg-white p-5">
            <p className="text-sm font-semibold text-ink">Syllabus content (markdown)</p>
            <textarea
              value={draft}
              onChange={(e) => setDraft(e.target.value)}
              rows={20}
              className="mt-3 w-full rounded-[10px] border border-line bg-white px-3 py-2 font-mono text-sm text-ink outline-none focus:border-trust"
            />
            <div className="mt-3 flex items-center gap-2">
              <button onClick={() => save.mutate()} disabled={save.isPending || !draft.trim()} className="rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white disabled:opacity-50">
                {save.isPending ? "Saving…" : "Save changes"}
              </button>
              <p className="text-xs text-muted">Editing reverts the syllabus to draft. Approving renders the branded PDF and publishes it.</p>
            </div>
          </div>
        </>
      )}
    </div>
  );
}
