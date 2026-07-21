"use client";

import Link from "next/link";
import { use, useEffect, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ApiError, apiJson } from "@/lib/api";

/**
 * Video-lesson authoring (admins with manage-curriculum). Attach either an
 * external embed URL (YouTube/Vimeo) or a Zoom recording from the tenant's
 * library. Direct file upload is a follow-up.
 */

type Video = {
  lesson_id: number;
  source: "url" | "recording" | "upload";
  title: string | null;
  url: string | null;
  recording_id: number | null;
  recording_title: string | null;
  playable_url: string | null;
} | null;

type Recording = { id: number; title: string; duration_seconds: number | null };

const inputCls = "w-full rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust";

function mins(seconds: number | null): string {
  if (!seconds) return "";
  return `${Math.round(seconds / 60)} min`;
}

export default function VideoEditor({ params }: { params: Promise<{ lesson: string }> }) {
  const { lesson } = use(params);
  const qc = useQueryClient();
  const [error, setError] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);

  const [source, setSource] = useState<"url" | "recording">("url");
  const [title, setTitle] = useState("");
  const [url, setUrl] = useState("");
  const [recordingId, setRecordingId] = useState<number | null>(null);

  const { data } = useQuery({
    queryKey: ["admin", "video", lesson],
    queryFn: () => apiJson<{ data: Video }>(`/api/v1/admin/lessons/${lesson}/video`),
  });
  const library = useQuery({
    queryKey: ["admin", "recordings-library"],
    queryFn: () => apiJson<{ data: Recording[] }>(`/api/v1/admin/recordings-library`),
  });

  useEffect(() => {
    const v = data?.data;
    if (v) {
      setSource(v.source === "recording" ? "recording" : "url");
      setTitle(v.title ?? "");
      setUrl(v.url ?? "");
      setRecordingId(v.recording_id);
    }
  }, [data]);

  const save = useMutation({
    mutationFn: () =>
      apiJson(`/api/v1/admin/lessons/${lesson}/video`, {
        method: "PUT",
        body: JSON.stringify({
          source,
          title: title.trim() || null,
          url: source === "url" ? url.trim() : null,
          recording_id: source === "recording" ? recordingId : null,
        }),
      }),
    onSuccess: () => {
      setError(null);
      setSaved(true);
      void qc.invalidateQueries({ queryKey: ["admin", "video", lesson] });
    },
    onError: (err) => setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Save failed."),
  });

  const recordings = library.data?.data ?? [];
  const canSave = source === "url" ? url.trim().length > 0 : recordingId !== null;

  return (
    <div className="mx-auto max-w-2xl">
      <Link href="/admin/curriculum" className="text-sm text-trust hover:underline">← Curriculum</Link>
      <h1 className="display mt-3 text-2xl text-ink">Lesson video</h1>
      <p className="mt-1 text-sm text-ink2/70">
        Attach a video students watch for this lesson — paste an embed link or pick a recorded class.
      </p>

      {error && <p className="mt-4 rounded-[10px] bg-warn/10 px-3 py-2 text-sm text-warn">{error}</p>}

      <div className="mt-4 space-y-4 rounded-2xl border border-line bg-white p-5">
        {/* Source toggle */}
        <div className="flex gap-2">
          <button
            onClick={() => { setSource("url"); setSaved(false); }}
            className={`rounded-full px-4 py-1.5 text-sm font-semibold transition-colors ${source === "url" ? "bg-trust text-white" : "border border-line text-ink hover:bg-paper"}`}>
            Embed link
          </button>
          <button
            onClick={() => { setSource("recording"); setSaved(false); }}
            className={`rounded-full px-4 py-1.5 text-sm font-semibold transition-colors ${source === "recording" ? "bg-trust text-white" : "border border-line text-ink hover:bg-paper"}`}>
            Recorded class
          </button>
        </div>

        <div>
          <label className="text-xs font-semibold text-muted">Title (optional)</label>
          <input className={`${inputCls} mt-1`} value={title} onChange={(e) => setTitle(e.target.value)}
            placeholder="e.g. Intro to window functions" />
        </div>

        {source === "url" ? (
          <div>
            <label className="text-xs font-semibold text-muted">Embed URL</label>
            <input className={`${inputCls} mt-1`} value={url} onChange={(e) => setUrl(e.target.value)}
              placeholder="https://www.youtube.com/embed/…" />
            <p className="mt-1 text-xs text-muted">Use the embed link (YouTube: Share → Embed), not the watch page.</p>
          </div>
        ) : (
          <div>
            <label className="text-xs font-semibold text-muted">Pick a recording</label>
            {library.isLoading ? (
              <div className="shimmer mt-2 h-24 rounded-[10px]" />
            ) : recordings.length === 0 ? (
              <p className="mt-2 rounded-[10px] border border-dashed border-line px-3 py-4 text-sm text-muted">
                No stored recordings yet. Recorded classes appear here once a live session recording is saved.
              </p>
            ) : (
              <div className="mt-2 space-y-1.5">
                {recordings.map((r) => (
                  <button key={r.id} onClick={() => { setRecordingId(r.id); setSaved(false); }}
                    className={`flex w-full items-center justify-between rounded-[10px] border px-3 py-2 text-left text-sm transition-colors ${recordingId === r.id ? "border-trust bg-sky/40 text-ink" : "border-line text-ink hover:bg-paper"}`}>
                    <span className="font-medium">{r.title}</span>
                    <span className="mono text-[11px] text-muted">{mins(r.duration_seconds)}</span>
                  </button>
                ))}
              </div>
            )}
          </div>
        )}

        <div className="flex items-center gap-3">
          <button onClick={() => { setSaved(false); save.mutate(); }} disabled={save.isPending || !canSave}
            className="rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white transition-colors hover:bg-deep disabled:opacity-50">
            {save.isPending ? "Saving…" : "Save video"}
          </button>
          {saved && <span className="mono text-xs text-verify">Saved ✓</span>}
        </div>
      </div>

      {data?.data?.playable_url && data.data.source === "url" && (
        <div className="mt-6 overflow-hidden rounded-2xl border border-line bg-black">
          <div className="aspect-video">
            <iframe src={data.data.playable_url} className="h-full w-full" allowFullScreen
              allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" />
          </div>
        </div>
      )}
    </div>
  );
}
