"use client";

import Link from "next/link";
import { use, useEffect, useRef, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ApiError, apiJson } from "@/lib/api";

type LessonNote = {
  id: number;
  lesson_id: number;
  transcript: string;
  notes: string | null;
  source: "paste" | "upload";
  status: "draft" | "approved";
  has_notes: boolean;
  has_pdf: boolean;
  pdf_uploaded: boolean;
  is_citable: boolean;
} | null;

const inputCls = "w-full rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust";

export default function ContentEditorPage({ params }: { params: Promise<{ lesson: string }> }) {
  const { lesson } = use(params);
  const qc = useQueryClient();
  const [error, setError] = useState<string | null>(null);
  const [transcript, setTranscript] = useState("");
  const [notes, setNotes] = useState("");
  const fileRef = useRef<HTMLInputElement>(null);
  const pdfRef = useRef<HTMLInputElement>(null);

  const query = useQuery({
    queryKey: ["admin", "note", lesson],
    queryFn: () => apiJson<{ data: LessonNote }>(`/api/v1/admin/lessons/${lesson}/notes`),
  });
  const note = query.data?.data ?? null;

  useEffect(() => {
    if (note) { setTranscript(note.transcript); setNotes(note.notes ?? ""); }
  }, [note]);

  const refresh = () => void qc.invalidateQueries({ queryKey: ["admin", "note", lesson] });
  const onError = (err: unknown) => setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Request failed.");

  const saveTranscript = useMutation({
    mutationFn: () => apiJson(`/api/v1/admin/lessons/${lesson}/notes`, { method: "PUT", body: JSON.stringify({ transcript }) }),
    onSuccess: () => { setError(null); setTimeout(refresh, 1200); },
    onError,
  });
  const upload = useMutation({
    mutationFn: () => {
      const fd = new FormData();
      const f = fileRef.current?.files?.[0];
      if (f) fd.append("file", f);
      return apiJson(`/api/v1/admin/lessons/${lesson}/notes/upload`, { method: "POST", body: fd });
    },
    onSuccess: () => { setError(null); setTimeout(refresh, 1200); },
    onError,
  });
  const generate = useMutation({
    mutationFn: () => apiJson(`/api/v1/admin/lessons/${lesson}/notes/generate`, { method: "POST", body: JSON.stringify({}) }),
    onSuccess: () => { setError(null); setTimeout(refresh, 1500); },
    onError,
  });
  const saveNotes = useMutation({
    mutationFn: () => apiJson(`/api/v1/admin/lessons/${lesson}/notes`, { method: "PATCH", body: JSON.stringify({ notes }) }),
    onSuccess: () => { setError(null); refresh(); },
    onError,
  });
  const approve = useMutation({
    mutationFn: () => apiJson(`/api/v1/admin/lesson-notes/${note?.id}/approve`, { method: "POST", body: JSON.stringify({}) }),
    onSuccess: () => { setError(null); refresh(); },
    onError,
  });

  const openPdf = (res: { data: { url: string | null } }) => {
    if (res.data.url) window.open(res.data.url, "_blank");
  };
  const makePdf = useMutation({
    mutationFn: () => apiJson<{ data: { url: string | null } }>(`/api/v1/admin/lessons/${lesson}/notes/pdf`, { method: "POST", body: JSON.stringify({}) }),
    onSuccess: (res) => { setError(null); openPdf(res); },
    onError,
  });
  const uploadPdf = useMutation({
    mutationFn: () => {
      const f = pdfRef.current?.files?.[0];
      if (!f) throw new ApiError(422, { message: "Choose a PDF first." });
      const fd = new FormData();
      fd.append("file", f);
      return apiJson<{ data: { url: string | null } }>(`/api/v1/admin/lessons/${lesson}/notes/pdf/upload`, { method: "POST", body: fd });
    },
    onSuccess: (res) => { setError(null); openPdf(res); },
    onError,
  });

  return (
    <div className="mx-auto max-w-2xl">
      <Link href="/admin/content" className="text-sm text-trust hover:underline">← Class notes</Link>
      <h1 className="display mt-3 text-2xl text-ink">Class notes</h1>
      {note && <p className="mono mt-1 text-[11px] uppercase tracking-widest text-muted">{note.status} · {note.source} · {note.is_citable ? "in tutor KB" : "not published"}</p>}

      {error && <p className="mt-3 text-sm text-warn">{error}</p>}

      {/* Transcript */}
      <div className="mt-6 rounded-2xl border border-line bg-white p-5">
        <p className="text-sm font-semibold text-ink">1. Transcript</p>
        <textarea className={`${inputCls} mt-3`} rows={6} placeholder="Paste the class transcript…" value={transcript} onChange={(e) => setTranscript(e.target.value)} />
        <div className="mt-3 flex flex-wrap items-center gap-2">
          <button onClick={() => saveTranscript.mutate()} disabled={saveTranscript.isPending || !transcript.trim()} className="rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white disabled:opacity-50">
            {saveTranscript.isPending ? "Saving…" : "Save transcript"}
          </button>
          <span className="text-xs text-muted">or</span>
          <input ref={fileRef} type="file" accept=".txt,.vtt,.md" className="text-sm text-muted" />
          <button onClick={() => upload.mutate()} disabled={upload.isPending} className="rounded-full border border-line px-4 py-2 text-sm font-semibold text-ink hover:bg-paper disabled:opacity-50">Upload</button>
        </div>
      </div>

      {/* Notes */}
      {note && (
        <div className="mt-6 rounded-2xl border border-line bg-white p-5">
          <div className="flex items-center justify-between">
            <p className="text-sm font-semibold text-ink">2. Study notes</p>
            <button onClick={() => generate.mutate()} disabled={generate.isPending} className="rounded-full border border-line px-4 py-2 text-xs font-semibold text-ink hover:bg-paper disabled:opacity-50">
              {generate.isPending ? "Generating…" : "Generate with AI"}
            </button>
          </div>
          <textarea className={`${inputCls} mt-3 font-mono text-xs`} rows={10} placeholder="AI notes appear here — generate or write them, then approve." value={notes} onChange={(e) => setNotes(e.target.value)} />
          <div className="mt-3 flex gap-2">
            <button onClick={() => saveNotes.mutate()} disabled={saveNotes.isPending || !notes.trim()} className="rounded-full border border-line px-4 py-2 text-sm font-semibold text-ink hover:bg-paper disabled:opacity-50">Save notes</button>
            <button onClick={() => approve.mutate()} disabled={approve.isPending || note.status === "approved" || !note.has_notes} className="rounded-full bg-verify px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">
              {note.status === "approved" ? "Approved ✓" : "Approve & publish"}
            </button>
          </div>
          <p className="mt-2 text-xs text-muted">Approving publishes the notes to students and feeds this transcript to the AI Tutor. Editing reverts to draft.</p>
        </div>
      )}

      {/* Downloadable PDF */}
      {note && (
        <div className="mt-6 rounded-2xl border border-line bg-white p-5">
          <p className="text-sm font-semibold text-ink">3. Downloadable PDF</p>
          <p className="mt-1 text-xs text-muted">
            Turn the notes into a branded PDF students can download — or upload your own.
          </p>
          <div className="mt-3 flex flex-wrap items-center gap-2">
            <button onClick={() => makePdf.mutate()} disabled={makePdf.isPending || !note.has_notes}
              className="rounded-full bg-trust px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-deep disabled:opacity-50">
              {makePdf.isPending ? "Building…" : "Generate PDF"}
            </button>
            <span className="text-xs text-muted">or</span>
            <input ref={pdfRef} type="file" accept="application/pdf,.pdf" className="text-sm text-muted" />
            <button onClick={() => uploadPdf.mutate()} disabled={uploadPdf.isPending}
              className="rounded-full border border-line px-4 py-2 text-sm font-semibold text-ink transition-colors hover:bg-paper disabled:opacity-50">
              {uploadPdf.isPending ? "Uploading…" : "Upload PDF"}
            </button>
          </div>
          {note.pdf_uploaded && <p className="mono mt-2 text-[10px] uppercase tracking-widest text-verify">Uploaded PDF attached</p>}
        </div>
      )}

      {/* Flashcards */}
      {note && (
        <div className="mt-6 rounded-2xl border border-line bg-white p-5">
          <p className="text-sm font-semibold text-ink">4. Flashcards</p>
          <p className="mt-1 text-xs text-muted">
            Turn this class into recall prompts students review on a spaced-repetition schedule. Draft with AI from these notes, edit, then publish.
          </p>
          <Link href={`/admin/flashcards/${lesson}`}
            className="mt-3 inline-block rounded-full border border-line px-4 py-2 text-sm font-semibold text-ink transition-colors hover:bg-paper">
            Edit flashcards →
          </Link>
        </div>
      )}
    </div>
  );
}
