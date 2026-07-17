"use client";

import Link from "next/link";
import { useQuery } from "@tanstack/react-query";
import { apiJson } from "@/lib/api";
import { EmptyState } from "@/components/ui/EmptyState";

type AssignmentLesson = {
  lesson_id: number;
  title: string;
  course: string | null;
  module: string | null;
  has_assignment: boolean;
  criteria_count: number;
};

export default function AdminAssignmentsPage() {
  const { data, isLoading } = useQuery({
    queryKey: ["admin", "assignment-lessons"],
    queryFn: () => apiJson<{ data: AssignmentLesson[] }>("/api/v1/admin/assignment-lessons"),
  });
  const lessons = data?.data ?? [];

  return (
    <div className="mx-auto max-w-3xl">
      <p className="kicker text-trust">Assessments</p>
      <h1 className="display mt-2 text-3xl text-ink">Assignments</h1>
      <p className="mt-1 text-sm text-muted">Author a rubric for each assignment/project lesson. Submissions are AI-graded against it, then you review and release.</p>

      {isLoading ? (
        <div className="mt-8 space-y-2">{Array.from({ length: 4 }).map((_, i) => <div key={i} className="shimmer h-14 rounded-[14px]" />)}</div>
      ) : lessons.length === 0 ? (
        <div className="mt-8"><EmptyState title="No assignment lessons yet" body="Add a lesson of type “assignment” or “project” under a topic in Curriculum, then set its rubric here." /></div>
      ) : (
        <div className="mt-8 divide-y divide-line rounded-[14px] border border-line bg-white">
          {lessons.map((l) => (
            <Link key={l.lesson_id} href={`/admin/assignments/${l.lesson_id}`} className="flex items-center gap-3 px-5 py-3 hover:bg-paper">
              <div className="min-w-0 flex-1">
                <p className="truncate text-sm text-ink">{l.title}</p>
                <p className="mono text-[11px] uppercase tracking-widest text-muted">{l.course ?? "—"} · {l.module ?? "—"}</p>
              </div>
              <span className={`mono rounded-full px-2.5 py-0.5 text-[11px] uppercase tracking-widest ${l.has_assignment ? "bg-verify-bg text-verify" : "bg-paper text-muted"}`}>
                {l.has_assignment ? `${l.criteria_count} criteria` : "no rubric"}
              </span>
            </Link>
          ))}
        </div>
      )}
    </div>
  );
}
