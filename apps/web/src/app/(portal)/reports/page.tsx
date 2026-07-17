"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { apiJson } from "@/lib/api";
import { EmptyState } from "@/components/ui/EmptyState";

type Report = {
  id: number;
  title: string;
  period_start: string | null;
  read_at: string | null;
};

export default function ReportsPage() {
  const [reports, setReports] = useState<Report[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    apiJson<{ data: Report[] }>("/api/v1/me/reports")
      .then((r) => setReports(r.data))
      .catch(() => setReports([]))
      .finally(() => setLoading(false));
  }, []);

  return (
    <div className="mx-auto max-w-2xl">
      <p className="kicker text-trust">Coach</p>
      <h1 className="display mt-2 text-3xl text-ink">Weekly reports</h1>
      <p className="mt-1 text-sm text-muted">A weekly read on what went well, what to work on, and your one focus.</p>

      {loading ? (
        <div className="mt-8 space-y-2">{Array.from({ length: 3 }).map((_, i) => <div key={i} className="shimmer h-16 rounded-[14px]" />)}</div>
      ) : reports.length === 0 ? (
        <div className="mt-8"><EmptyState title="No reports yet" body="Your first weekly report arrives once you have a week of activity — you'll get a nudge when it's ready." /></div>
      ) : (
        <div className="mt-8 divide-y divide-line rounded-[14px] border border-line bg-white">
          {reports.map((r) => (
            <Link key={r.id} href={`/reports/${r.id}`} className="flex items-center justify-between gap-3 px-5 py-4 hover:bg-paper">
              <div>
                <p className="text-sm font-semibold text-ink">{r.title}</p>
                <p className="mono text-[11px] uppercase tracking-widest text-muted">Week of {r.period_start ?? "—"}</p>
              </div>
              {r.read_at === null && <span className="mono rounded-full bg-sky px-2.5 py-0.5 text-[10px] uppercase tracking-widest text-deep">new</span>}
            </Link>
          ))}
        </div>
      )}
    </div>
  );
}
