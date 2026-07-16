"use client";

import Link from "next/link";
import { useCallback, useEffect, useState } from "react";
import { apiJson } from "@/lib/api";
import { EmptyState } from "@/components/ui/EmptyState";

type Lab = { lesson_id: number; title: string | null; language: string };

export default function LabsPage() {
  const [labs, setLabs] = useState<Lab[]>([]);
  const [loading, setLoading] = useState(true);

  const load = useCallback(() => {
    apiJson<{ data: Lab[] }>("/api/v1/me/labs")
      .then((r) => setLabs(r.data))
      .catch(() => setLabs([]))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => { load(); }, [load]);

  return (
    <div className="mx-auto max-w-2xl">
      <p className="kicker text-trust">Practice</p>
      <h1 className="display mt-2 text-3xl text-ink">Coding labs</h1>
      <p className="mt-2 text-sm text-muted">Write, run, and submit code right in the browser. Every run sharpens your mastery map.</p>

      {loading ? (
        <div className="mt-6 space-y-3">{Array.from({ length: 3 }).map((_, i) => <div key={i} className="shimmer h-16 rounded-[14px]" />)}</div>
      ) : labs.length === 0 ? (
        <div className="mt-6"><EmptyState title="No labs yet" body="Coding labs will appear here as your trainer publishes them." /></div>
      ) : (
        <div className="mt-6 divide-y divide-line rounded-[14px] border border-line bg-white">
          {labs.map((l) => (
            <Link key={l.lesson_id} href={`/labs/${l.lesson_id}`} className="flex items-center gap-3 px-5 py-4 transition-colors hover:bg-paper">
              <span className="flex-1 font-semibold text-ink">{l.title ?? "Coding lab"}</span>
              <span className="mono rounded-full bg-sky px-2.5 py-0.5 text-[10px] uppercase tracking-widest text-deep">{l.language}</span>
              <span className="text-trust">→</span>
            </Link>
          ))}
        </div>
      )}
    </div>
  );
}
