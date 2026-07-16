"use client";

import Link from "next/link";
import { useQuery } from "@tanstack/react-query";
import { apiJson } from "@/lib/api";
import { formatPaise } from "@/lib/money";
import { EmptyState } from "@/components/ui/EmptyState";

type Dunning = {
  expected_collections_paise: number;
  overdue_count: number;
  failed_count: number;
  active_blocks_count: number;
  aging: { bucket_0_7: number; bucket_8_30: number; bucket_30_plus: number };
  overdue: {
    fee_plan_id: number;
    student: string | null;
    batch: string | null;
    overdue_days: number;
    outstanding_paise: number;
    block: "soft" | "hard" | null;
  }[];
};

const BLOCK_STYLES: Record<string, string> = {
  soft: "bg-warn/10 text-warn",
  hard: "bg-warn text-white",
};

function Tile({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-[14px] border border-line bg-white p-5">
      <div className="mono text-2xl text-ink">{value}</div>
      <p className="mt-1 text-sm text-muted">{label}</p>
    </div>
  );
}

export default function DunningPage() {
  const { data, isLoading } = useQuery({
    queryKey: ["admin", "dunning"],
    queryFn: () => apiJson<{ data: Dunning }>("/api/v1/admin/dunning"),
  });
  const d = data?.data;

  return (
    <div className="mx-auto max-w-5xl">
      <p className="kicker text-trust">Payments</p>
      <h1 className="display mt-2 text-3xl text-ink">Dunning</h1>

      {isLoading || !d ? (
        <div className="mt-8 grid gap-4 sm:grid-cols-4">
          {Array.from({ length: 4 }).map((_, i) => <div key={i} className="shimmer h-24 rounded-[14px]" />)}
        </div>
      ) : (
        <>
          <div className="mt-8 grid gap-4 sm:grid-cols-4">
            <Tile label="Expected collections" value={formatPaise(d.expected_collections_paise)} />
            <Tile label="Overdue students" value={String(d.overdue_count)} />
            <Tile label="Failed instalments" value={String(d.failed_count)} />
            <Tile label="Active blocks" value={String(d.active_blocks_count)} />
          </div>

          <div className="mt-4 flex flex-wrap gap-3">
            <span className="mono rounded-full bg-sky px-3 py-1 text-xs text-deep">0–7d: {d.aging.bucket_0_7}</span>
            <span className="mono rounded-full bg-warn/10 px-3 py-1 text-xs text-warn">8–30d: {d.aging.bucket_8_30}</span>
            <span className="mono rounded-full bg-warn px-3 py-1 text-xs text-white">30d+: {d.aging.bucket_30_plus}</span>
          </div>

          {d.overdue.length === 0 ? (
            <div className="mt-6">
              <EmptyState title="No overdue students" body="Everyone with an active fee plan is on schedule. Overdue accounts will surface here with their aging and block status." />
            </div>
          ) : (
            <div className="mt-6 divide-y divide-line rounded-[14px] border border-line bg-white">
              {d.overdue.map((row) => (
                <Link key={row.fee_plan_id} href={`/admin/payments/${row.fee_plan_id}`} className="flex flex-wrap items-center gap-3 px-5 py-4 transition-colors hover:bg-paper">
                  <span className="min-w-[150px] flex-1 font-semibold text-ink">{row.student ?? "—"}</span>
                  <span className="mono text-xs text-muted">{row.batch}</span>
                  <span className="mono rounded-full bg-warn/10 px-2.5 py-0.5 text-[10px] uppercase tracking-widest text-warn">
                    {row.overdue_days}d overdue
                  </span>
                  {row.block && (
                    <span className={`mono rounded-full px-2.5 py-0.5 text-[10px] uppercase tracking-widest ${BLOCK_STYLES[row.block]}`}>
                      {row.block} block
                    </span>
                  )}
                  <span className="mono w-28 text-right text-sm text-ink">{formatPaise(row.outstanding_paise)}</span>
                  <span className="text-trust">→</span>
                </Link>
              ))}
            </div>
          )}
        </>
      )}
    </div>
  );
}
