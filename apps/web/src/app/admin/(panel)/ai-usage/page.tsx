"use client";

import { useQuery } from "@tanstack/react-query";
import { apiJson } from "@/lib/api";
import { EmptyState } from "@/components/ui/EmptyState";

type Usage = {
  today: { calls: number; tokens: number; cost_micros: number; budget_per_student: number };
  by_purpose: { purpose: string; calls: number; tokens: number; cost_micros: number }[];
  recent: { purpose: string; model: string; total_tokens: number; cost_micros: number; latency_ms: number; status: string; created_at: string | null }[];
};

/** Micro-rupees (₹1 = 1e6) → ₹ string. */
function formatMicros(micros: number): string {
  return `₹${(micros / 1_000_000).toFixed(2)}`;
}

function Tile({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-[14px] border border-line bg-white p-5">
      <div className="mono text-2xl text-ink">{value}</div>
      <p className="mt-1 text-sm text-muted">{label}</p>
    </div>
  );
}

const STATUS_STYLES: Record<string, string> = {
  ok: "bg-verify-bg text-verify",
  failed: "bg-warn/10 text-warn",
  budget_exceeded: "bg-amber/15 text-ink2",
};

export default function AiUsagePage() {
  const { data, isLoading } = useQuery({
    queryKey: ["admin", "ai-usage"],
    queryFn: () => apiJson<{ data: Usage }>("/api/v1/admin/ai-usage"),
  });
  const d = data?.data;

  return (
    <div className="mx-auto max-w-4xl">
      <p className="kicker text-trust">AI Service Layer</p>
      <h1 className="display mt-2 text-3xl text-ink">AI usage &amp; cost</h1>

      {isLoading || !d ? (
        <div className="mt-8 grid gap-4 sm:grid-cols-3">{Array.from({ length: 3 }).map((_, i) => <div key={i} className="shimmer h-24 rounded-[14px]" />)}</div>
      ) : (
        <>
          <div className="mt-8 grid gap-4 sm:grid-cols-3">
            <Tile label="Calls today" value={String(d.today.calls)} />
            <Tile label="Tokens today" value={d.today.tokens.toLocaleString("en-IN")} />
            <Tile label="Cost today" value={formatMicros(d.today.cost_micros)} />
          </div>
          <p className="mt-2 text-xs text-muted">Per-student daily token budget: <span className="mono">{d.today.budget_per_student.toLocaleString("en-IN")}</span></p>

          <h2 className="display mt-10 text-xl text-ink">By purpose</h2>
          {d.by_purpose.length === 0 ? (
            <div className="mt-4"><EmptyState title="No AI calls yet" body="Gateway calls will break down here by purpose once features start using it." /></div>
          ) : (
            <div className="mt-4 divide-y divide-line rounded-[14px] border border-line bg-white">
              <div className="flex items-center gap-3 px-5 py-2 text-[11px] uppercase tracking-widest text-muted">
                <span className="flex-1">Purpose</span><span className="mono w-16 text-right">Calls</span><span className="mono w-24 text-right">Tokens</span><span className="mono w-24 text-right">Cost</span>
              </div>
              {d.by_purpose.map((p) => (
                <div key={p.purpose} className="flex items-center gap-3 px-5 py-3 text-sm">
                  <span className="flex-1 font-semibold text-ink">{p.purpose.replace(/_/g, " ")}</span>
                  <span className="mono w-16 text-right text-muted">{p.calls}</span>
                  <span className="mono w-24 text-right text-ink">{p.tokens.toLocaleString("en-IN")}</span>
                  <span className="mono w-24 text-right text-ink">{formatMicros(p.cost_micros)}</span>
                </div>
              ))}
            </div>
          )}

          <h2 className="display mt-10 text-xl text-ink">Recent calls</h2>
          <div className="mt-4 divide-y divide-line rounded-[14px] border border-line bg-white">
            {d.recent.map((e, i) => (
              <div key={i} className="flex flex-wrap items-center gap-3 px-5 py-3 text-sm">
                <span className="min-w-28 flex-1 font-semibold text-ink">{e.purpose.replace(/_/g, " ")}</span>
                <span className="mono text-xs text-muted">{e.model}</span>
                <span className="mono text-xs text-muted">{e.total_tokens} tok · {e.latency_ms}ms</span>
                <span className={`mono rounded-full px-2.5 py-0.5 text-[10px] uppercase tracking-widest ${STATUS_STYLES[e.status] ?? "bg-paper text-muted"}`}>{e.status.replace(/_/g, " ")}</span>
              </div>
            ))}
          </div>
        </>
      )}
    </div>
  );
}
