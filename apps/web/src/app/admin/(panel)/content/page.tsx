"use client";

import Link from "next/link";
import { useQuery } from "@tanstack/react-query";
import { apiJson } from "@/lib/api";
import { EmptyState } from "@/components/ui/EmptyState";

type NoteLesson = {
  lesson_id: number;
  title: string;
  course: string | null;
  module: string | null;
  has_transcript: boolean;
  has_notes: boolean;
  status: "draft" | "approved" | null;
};

function StatusPill({ l }: { l: NoteLesson }) {
  if (!l.has_transcript) return <span className="mono rounded-full bg-paper px-2.5 py-0.5 text-[11px] uppercase tracking-widest text-muted">none</span>;
  if (l.status === "approved") return <span className="mono rounded-full bg-verify-bg px-2.5 py-0.5 text-[11px] uppercase tracking-widest text-verify">approved</span>;
  return <span className="mono rounded-full bg-sky px-2.5 py-0.5 text-[11px] uppercase tracking-widest text-deep">draft</span>;
}

export default function AdminContentPage() {
  const { data, isLoading } = useQuery({
    queryKey: ["admin", "note-lessons"],
    queryFn: () => apiJson<{ data: NoteLesson[] }>("/api/v1/admin/note-lessons"),
  });
  const lessons = data?.data ?? [];

  return (
    <div className="mx-auto max-w-3xl">
      <p className="kicker text-trust">Content AI</p>
      <h1 className="display mt-2 text-3xl text-ink">Class notes</h1>
      <p className="mt-1 text-sm text-muted">Add a class transcript to a notes lesson; AI drafts study notes. Approve to publish them to students and feed the AI Tutor.</p>

      {isLoading ? (
        <div className="mt-8 space-y-2">{Array.from({ length: 4 }).map((_, i) => <div key={i} className="shimmer h-14 rounded-[14px]" />)}</div>
      ) : lessons.length === 0 ? (
        <div className="mt-8"><EmptyState title="No notes lessons yet" body="Add a lesson of type “notes” under a topic in Curriculum, then attach its transcript here." /></div>
      ) : (
        <div className="mt-8 divide-y divide-line rounded-[14px] border border-line bg-white">
          {lessons.map((l) => (
            <Link key={l.lesson_id} href={`/admin/content/${l.lesson_id}`} className="flex items-center gap-3 px-5 py-3 hover:bg-paper">
              <div className="min-w-0 flex-1">
                <p className="truncate text-sm text-ink">{l.title}</p>
                <p className="mono text-[11px] uppercase tracking-widest text-muted">{l.course ?? "—"} · {l.module ?? "—"}</p>
              </div>
              <StatusPill l={l} />
            </Link>
          ))}
        </div>
      )}
    </div>
  );
}
