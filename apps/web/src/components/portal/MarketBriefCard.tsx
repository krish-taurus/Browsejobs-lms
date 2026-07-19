"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { apiJson } from "@/lib/api";

/**
 * Dashboard Market Brief card: today's headline plus a hiring/cuts/funding
 * count strip, linking to the full /brief. Students see the same intelligence
 * the public gets teased — proof the syllabus tracks a moving market. Hidden
 * when no brief exists.
 */

type Brief = {
  date: string;
  headline: string | null;
  items: Record<"hiring" | "layoff" | "funding", unknown[]>;
  total: number;
};

export function MarketBriefCard() {
  const [brief, setBrief] = useState<Brief | null>(null);

  useEffect(() => {
    apiJson<{ data: Brief }>("/api/v1/brief").then((r) => setBrief(r.data)).catch(() => {});
  }, []);

  if (!brief || brief.total === 0) return null;

  const counts: [string, number, string][] = [
    ["hiring", brief.items.hiring.length, "text-verify"],
    ["cuts", brief.items.layoff.length, "text-warn"],
    ["funding", brief.items.funding.length, "text-trust"],
  ];

  return (
    <Link
      href="/brief"
      className="group mt-6 block rounded-[14px] border border-line bg-white p-5 shadow-soft transition-all duration-300 hover:-translate-y-0.5 hover:border-trust/40"
    >
      <div className="flex items-center justify-between">
        <span className="mono text-[10px] font-semibold uppercase tracking-[0.18em] text-trust">
          Daily market brief · {brief.date}
        </span>
        <span className="text-sm font-semibold text-trust transition-transform duration-300 group-hover:translate-x-1">→</span>
      </div>
      <p className="mt-2 font-semibold text-ink">{brief.headline}</p>
      <div className="mono mt-3 flex gap-4 text-xs">
        {counts.map(([label, n, tone]) =>
          n === 0 ? null : (
            <span key={label} className={tone}>
              {n} {label}
            </span>
          ),
        )}
      </div>
    </Link>
  );
}
