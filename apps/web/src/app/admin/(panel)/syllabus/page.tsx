"use client";

import Link from "next/link";
import { useQuery } from "@tanstack/react-query";
import { apiJson } from "@/lib/api";
import { EmptyState } from "@/components/ui/EmptyState";

type CourseSyllabus = {
  course_id: number;
  name: string;
  slug: string | null;
  has_syllabus: boolean;
  status: "draft" | "approved" | null;
  is_stale: boolean;
  render_status: string | null;
  version: number | null;
  downloadable: boolean;
};

function StatusPill({ c }: { c: CourseSyllabus }) {
  if (!c.has_syllabus) return <span className="mono rounded-full bg-paper px-2.5 py-0.5 text-[11px] uppercase tracking-widest text-muted">none</span>;
  if (c.status === "approved") return <span className="mono rounded-full bg-verify-bg px-2.5 py-0.5 text-[11px] uppercase tracking-widest text-verify">approved</span>;
  return <span className="mono rounded-full bg-sky px-2.5 py-0.5 text-[11px] uppercase tracking-widest text-deep">draft</span>;
}

export default function AdminSyllabusPage() {
  const { data, isLoading } = useQuery({
    queryKey: ["admin", "course-syllabuses"],
    queryFn: () => apiJson<{ data: CourseSyllabus[] }>("/api/v1/admin/course-syllabuses"),
  });
  const courses = data?.data ?? [];

  return (
    <div className="mx-auto max-w-3xl">
      <p className="kicker text-trust">Content AI</p>
      <h1 className="display mt-2 text-3xl text-ink">Course syllabus</h1>
      <p className="mt-1 text-sm text-muted">Generate a syllabus from the curriculum with AI, edit it, then approve — only an approved syllabus is rendered and downloadable on the course page and student dashboard.</p>

      {isLoading ? (
        <div className="mt-8 space-y-2">{Array.from({ length: 4 }).map((_, i) => <div key={i} className="shimmer h-14 rounded-[14px]" />)}</div>
      ) : courses.length === 0 ? (
        <div className="mt-8"><EmptyState title="No courses yet" body="Add a course in Curriculum, then build its syllabus here." /></div>
      ) : (
        <div className="mt-8 divide-y divide-line rounded-[14px] border border-line bg-white">
          {courses.map((c) => (
            <Link key={c.course_id} href={`/admin/syllabus/${c.course_id}`} className="flex items-center gap-3 px-5 py-3 hover:bg-paper">
              <div className="min-w-0 flex-1">
                <p className="truncate text-sm text-ink">{c.name}</p>
                <p className="mono text-[11px] uppercase tracking-widest text-muted">
                  {c.has_syllabus ? `v${c.version} · ${c.render_status}` : "no syllabus"}{c.downloadable ? " · live" : ""}
                </p>
              </div>
              {c.is_stale && <span className="mono rounded-full bg-warn/10 px-2.5 py-0.5 text-[11px] uppercase tracking-widest text-warn">stale</span>}
              <StatusPill c={c} />
            </Link>
          ))}
        </div>
      )}
    </div>
  );
}
