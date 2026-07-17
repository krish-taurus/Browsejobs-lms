"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { apiJson } from "@/lib/api";
import { EmptyState } from "@/components/ui/EmptyState";

type Grade = { assignment: string | null; lesson_id: number | null; score: number; max_points: number; approved_at: string | null };

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
        <div className="mt-8"><EmptyState title="No grades yet" body="Submitted assignments appear here once your trainer releases the grade." /></div>
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
