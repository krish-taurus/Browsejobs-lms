"use client";

import Link from "next/link";
import { useMemo, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { apiJson } from "@/lib/api";
import { EmptyState } from "@/components/ui/EmptyState";

type Message = {
  id: number;
  direction: "out" | "in";
  channel: "whatsapp" | "email" | "inapp";
  category: string;
  template_key: string | null;
  recipient: string | null;
  body: string | null;
  status: string;
  suppressed_reason: string | null;
  created_at: string | null;
};

const inputCls =
  "rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust";

const STATUS_STYLES: Record<string, string> = {
  sent: "bg-sky text-deep",
  delivered: "bg-verify-bg text-verify",
  read: "bg-verify-bg text-verify",
  queued: "bg-paper text-muted",
  failed: "bg-warn/10 text-warn",
  suppressed: "bg-warn/10 text-warn",
  blocked: "bg-warn text-white",
};

export default function AdminMessagesPage() {
  const [filters, setFilters] = useState({ direction: "", channel: "", status: "", search: "" });

  const query = useMemo(() => {
    const p = new URLSearchParams();
    Object.entries(filters).forEach(([k, v]) => v && p.set(k, v));
    return p.toString();
  }, [filters]);

  const { data, isLoading } = useQuery({
    queryKey: ["admin", "messages", filters],
    queryFn: () => apiJson<{ data: Message[] }>(`/api/v1/admin/messages${query ? `?${query}` : ""}`),
  });

  return (
    <div className="mx-auto max-w-6xl">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <p className="kicker text-trust">Messaging</p>
          <h1 className="display mt-2 text-3xl text-ink">Delivery log</h1>
        </div>
        <Link href="/admin/messages/templates" className="rounded-full border border-line px-4 py-2 text-sm font-medium text-ink hover:bg-paper">
          Templates →
        </Link>
      </div>

      <div className="mt-6 flex flex-wrap items-end gap-3 rounded-[14px] border border-line bg-white p-4">
        <input placeholder="Search recipient or body…" value={filters.search} onChange={(e) => setFilters({ ...filters, search: e.target.value })} className={`${inputCls} min-w-[220px] flex-1`} />
        <select value={filters.direction} onChange={(e) => setFilters({ ...filters, direction: e.target.value })} className={inputCls}>
          <option value="">All directions</option><option value="out">Outbound</option><option value="in">Inbound</option>
        </select>
        <select value={filters.channel} onChange={(e) => setFilters({ ...filters, channel: e.target.value })} className={inputCls}>
          <option value="">All channels</option><option value="whatsapp">WhatsApp</option><option value="email">Email</option><option value="inapp">In-app</option>
        </select>
        <select value={filters.status} onChange={(e) => setFilters({ ...filters, status: e.target.value })} className={inputCls}>
          <option value="">All statuses</option>
          {["queued", "sent", "delivered", "read", "failed", "suppressed", "blocked"].map((s) => <option key={s} value={s}>{s}</option>)}
        </select>
      </div>

      {isLoading ? (
        <div className="mt-6 space-y-3">{Array.from({ length: 6 }).map((_, i) => <div key={i} className="shimmer h-14 rounded-[14px]" />)}</div>
      ) : data && data.data.length === 0 ? (
        <div className="mt-6"><EmptyState title="No messages yet" body="Outbound sends and inbound WhatsApp replies will appear here with their delivery status." /></div>
      ) : (
        <div className="mt-6 divide-y divide-line rounded-[14px] border border-line bg-white">
          {data?.data.map((m) => (
            <div key={m.id} className="flex flex-wrap items-center gap-3 px-5 py-3">
              <span className={`mono rounded-full px-2 py-0.5 text-[10px] uppercase tracking-widest ${m.direction === "in" ? "bg-sky text-deep" : "bg-paper text-muted"}`}>{m.direction}</span>
              <span className="mono rounded-full bg-sky px-2 py-0.5 text-[10px] uppercase tracking-widest text-deep">{m.channel}</span>
              <span className="mono w-40 truncate text-xs text-muted">{m.recipient ?? "—"}</span>
              <span className="flex-1 truncate text-sm text-ink">{m.body}</span>
              {m.template_key && <span className="mono text-[11px] text-muted">{m.template_key}</span>}
              <span className={`mono rounded-full px-2.5 py-0.5 text-[10px] uppercase tracking-widest ${STATUS_STYLES[m.status] ?? "bg-paper text-muted"}`}>
                {m.status}{m.suppressed_reason ? `: ${m.suppressed_reason}` : ""}
              </span>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
