"use client";

import { use, useEffect, useState } from "react";
import { apiJson } from "@/lib/api";

type Notes = { lesson_id: number; title: string | null; notes: string | null; approved_at: string | null } | null;

/** Minimal markdown-light renderer: headings (##) + bullets (- ) + paragraphs. */
function renderNotes(md: string) {
  return md.split("\n").map((line, i) => {
    const t = line.trim();
    if (t.startsWith("## ")) return <h2 key={i} className="display mt-5 text-lg text-ink">{t.slice(3)}</h2>;
    if (t.startsWith("# ")) return <h2 key={i} className="display mt-5 text-xl text-ink">{t.slice(2)}</h2>;
    if (t.startsWith("- ") || t.startsWith("* ")) return <li key={i} className="ml-5 list-disc text-sm text-ink">{t.slice(2)}</li>;
    if (t === "") return null;
    return <p key={i} className="mt-2 text-sm text-ink">{t}</p>;
  });
}

export default function LessonNotesPage({ params }: { params: Promise<{ lesson: string }> }) {
  const { lesson } = use(params);
  const [data, setData] = useState<Notes>(null);
  const [loading, setLoading] = useState(true);
  const [missing, setMissing] = useState(false);

  useEffect(() => {
    apiJson<{ data: Notes }>(`/api/v1/me/lessons/${lesson}/notes`)
      .then((r) => setData(r.data))
      .catch(() => setMissing(true))
      .finally(() => setLoading(false));
  }, [lesson]);

  if (loading) return <div className="mx-auto max-w-2xl"><div className="shimmer h-72 rounded-[14px]" /></div>;

  if (missing || !data) {
    return (
      <div className="mx-auto max-w-2xl">
        <h1 className="display text-2xl text-ink">Study notes</h1>
        <p className="mt-3 text-sm text-muted">Notes for this class are being prepared — check back soon.</p>
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-2xl">
      <p className="kicker text-trust">Study notes</p>
      <h1 className="display mt-2 text-2xl text-ink">{data.title ?? "Class notes"}</h1>
      <div className="mt-6 rounded-2xl border border-line bg-white p-6">
        {data.notes ? renderNotes(data.notes) : <p className="text-sm text-muted">No notes yet.</p>}
      </div>
    </div>
  );
}
