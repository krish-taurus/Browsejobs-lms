"use client";

import { useQuery } from "@tanstack/react-query";
import { apiJson } from "@/lib/api";
import { EmptyState } from "@/components/ui/EmptyState";

type Funnel = {
  total_leads: number;
  funnel: { key: string; label: string; count: number }[];
  campaigns: { campaign: string; leads: number; paid: number }[];
};

function pct(n: number, d: number): string {
  return d === 0 ? "0%" : `${Math.round((n / d) * 100)}%`;
}

export default function FunnelPage() {
  const { data, isLoading } = useQuery({
    queryKey: ["admin", "funnel"],
    queryFn: () => apiJson<{ data: Funnel }>("/api/v1/admin/funnel"),
  });
  const d = data?.data;

  return (
    <div className="mx-auto max-w-4xl">
      <p className="kicker text-trust">CRM</p>
      <h1 className="display mt-2 text-3xl text-ink">Conversion funnel</h1>

      {isLoading || !d ? (
        <div className="mt-8 space-y-3">{Array.from({ length: 5 }).map((_, i) => <div key={i} className="shimmer h-12 rounded-[14px]" />)}</div>
      ) : (
        <>
          {/* Funnel bars: ad → masterclass → bootcamp → reserved → paid */}
          <div className="mt-8 space-y-2">
            {d.funnel.map((s, i) => {
              const top = d.funnel[0].count || 1;
              const width = Math.max(4, Math.round((s.count / top) * 100));
              const prev = i === 0 ? null : d.funnel[i - 1];
              return (
                <div key={s.key} className="flex items-center gap-3">
                  <span className="w-24 text-sm text-muted">{s.label}</span>
                  <div className="flex-1">
                    <div className="h-9 rounded-[10px] bg-sky" style={{ width: `${width}%` }}>
                      <span className="mono flex h-full items-center px-3 text-sm font-semibold text-deep">{s.count}</span>
                    </div>
                  </div>
                  {prev && <span className="mono w-12 text-right text-xs text-muted">{pct(s.count, prev.count)}</span>}
                </div>
              );
            })}
          </div>
          <p className="mt-3 text-xs text-muted">Each stage counts leads at or past it; the % is conversion from the previous step.</p>

          {/* By campaign */}
          <h2 className="display mt-10 text-xl text-ink">By campaign</h2>
          {d.campaigns.length === 0 ? (
            <div className="mt-4"><EmptyState title="No campaign data yet" body="Leads captured with a UTM campaign will break down here by lead → paid conversion." /></div>
          ) : (
            <div className="mt-4 divide-y divide-line rounded-[14px] border border-line bg-white">
              <div className="flex items-center gap-3 px-5 py-2 text-[11px] uppercase tracking-widest text-muted">
                <span className="flex-1">Campaign</span><span className="mono w-16 text-right">Leads</span><span className="mono w-16 text-right">Paid</span><span className="mono w-16 text-right">Rate</span>
              </div>
              {d.campaigns.map((c) => (
                <div key={c.campaign} className="flex items-center gap-3 px-5 py-3 text-sm">
                  <span className="mono flex-1 truncate text-ink">{c.campaign}</span>
                  <span className="mono w-16 text-right text-muted">{c.leads}</span>
                  <span className="mono w-16 text-right text-verify">{c.paid}</span>
                  <span className="mono w-16 text-right font-semibold text-ink">{pct(c.paid, c.leads)}</span>
                </div>
              ))}
            </div>
          )}
        </>
      )}
    </div>
  );
}
