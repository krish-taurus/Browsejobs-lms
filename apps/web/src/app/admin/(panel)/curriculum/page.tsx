"use client";

import Link from "next/link";
import { useQuery } from "@tanstack/react-query";
import { apiJson } from "@/lib/api";
import { EmptyState } from "@/components/ui/EmptyState";

type CourseRow = {
  id: number;
  code: string;
  name: string;
  status: string;
  tagline: string | null;
  modules_count: number;
  program: { id: number; name: string } | null;
};

export default function AdminCurriculumPage() {
  const { data, isLoading } = useQuery({
    queryKey: ["admin", "courses"],
    queryFn: () => apiJson<{ data: CourseRow[] }>("/api/v1/admin/courses"),
  });

  return (
    <div className="mx-auto max-w-5xl">
      <p className="kicker text-trust">Curriculum</p>
      <h1 className="display mt-2 text-3xl text-ink">Programs &amp; syllabi</h1>

      {isLoading ? (
        <div className="mt-8 space-y-3">
          {Array.from({ length: 4 }).map((_, i) => (
            <div key={i} className="shimmer h-16 rounded-[14px]" />
          ))}
        </div>
      ) : !data?.data.length ? (
        <div className="mt-8">
          <EmptyState title="No courses yet" body="Seed or create courses to start building curriculum." />
        </div>
      ) : (
        <div className="mt-8 divide-y divide-line rounded-[14px] border border-line bg-white">
          {data.data.map((c) => (
            <Link
              key={c.id}
              href={`/admin/curriculum/${c.id}`}
              className="flex items-center gap-4 px-5 py-4 transition-colors hover:bg-paper"
            >
              <span className="mono rounded-full bg-sky px-3 py-1 text-xs font-semibold text-deep">{c.code}</span>
              <span className="flex-1">
                <span className="block font-semibold text-ink">{c.name}</span>
                <span className="block text-sm text-muted">{c.tagline}</span>
              </span>
              <span
                className={`mono rounded-full px-2.5 py-1 text-[10px] uppercase tracking-widest ${
                  c.status === "live" ? "bg-verify-bg text-verify" : "bg-paper text-muted"
                }`}
              >
                {c.status === "live" ? "Live" : "Waitlist"}
              </span>
              <span className="mono text-sm text-muted">{c.modules_count} modules</span>
              <span className="text-trust">→</span>
            </Link>
          ))}
        </div>
      )}
    </div>
  );
}
