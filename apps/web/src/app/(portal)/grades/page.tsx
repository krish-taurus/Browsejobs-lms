"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { apiJson } from "@/lib/api";
import { AiMascot } from "@/components/ui/AiMascot";

type Grade = { assignment: string | null; lesson_id: number | null; score: number; max_points: number; approved_at: string | null };

function GradesEmptyState() {
  return (
    <div className="relative mt-8 overflow-hidden rounded-2xl border border-line bg-white px-6 py-14">
      <div aria-hidden className="pointer-events-none absolute -bottom-10 -left-10 size-40 rounded-full bg-sky/60 blur-2xl" />
      <div aria-hidden className="pointer-events-none absolute -right-8 top-1/3 size-28 rounded-full bg-trust/10 blur-2xl" />

      <div className="relative mx-auto flex max-w-sm flex-col items-center text-center">
        <AiMascot variant="grade" className="h-48 w-full max-w-sm" />
        <h3 className="display mt-4 text-xl text-ink">No grades yet</h3>
        <p className="mt-2 text-sm text-muted">Submitted assignments appear here once your trainer releases the grade.</p>
        <Link
          href="/assignments"
          className="mt-6 rounded-full bg-trust px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-deep"
        >
          View my assignments
        </Link>
      </div>
    </div>
  );
}

export default function GradesPage() {
  const [grades, setGrades] = useState<Grade[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    apiJson<{ data: Grade[] }>("/api/v1/me/grades")
      .then((r) => setGrades(r.data))
      .catch(() => setGrades([]))
      .finally(() => setLoading(false));
  }, []);

  return (
    <div className="mx-auto max-w-2xl">
      <p className="kicker text-trust">Assessments</p>
      <h1 className="display mt-2 text-3xl text-ink">My grades</h1>

      {loading ? (
        <div className="mt-8 space-y-2">{Array.from({ length: 3 }).map((_, i) => <div key={i} className="shimmer h-14 rounded-[14px]" />)}</div>
      ) : grades.length === 0 ? (
        <GradesEmptyState />
      ) : (
        <div className="mt-8 divide-y divide-line rounded-[14px] border border-line bg-white">
          {grades.map((g, i) => (
            <Link key={i} href={g.lesson_id ? `/assignments/${g.lesson_id}` : "#"} className="flex items-center justify-between gap-3 px-5 py-3 hover:bg-paper">
              <span className="truncate text-sm text-ink">{g.assignment ?? "Assignment"}</span>
              <span className="mono text-sm font-semibold text-verify">{g.score}/{g.max_points}</span>
            </Link>
          ))}
        </div>
      )}
    </div>
  );
}
