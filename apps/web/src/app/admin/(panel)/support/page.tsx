"use client";

import Link from "next/link";
import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { apiJson } from "@/lib/api";
import { EmptyState } from "@/components/ui/EmptyState";

type Ticket = {
  id: number;
  reference: string;
  subject: string;
  category: string;
  status: string;
  breached: boolean;
  escalated: boolean;
  first_response_due_at: string | null;
  student: { name: string } | null;
  assignee: { name: string } | null;
};

const TABS = [
  { key: "mine", label: "Mine", query: "assignee=me" },
  { key: "open", label: "Open", query: "status=open" },
  { key: "breached", label: "Breached", query: "breached=1" },
  { key: "all", label: "All", query: "" },
] as const;

const STATUS_STYLES: Record<string, string> = {
  open: "bg-sky text-deep",
  in_progress: "bg-sky text-deep",
  waiting_on_student: "bg-amber/15 text-ink2",
  resolved: "bg-verify-bg text-verify",
  closed: "bg-paper text-muted",
};

function dueLabel(iso: string | null): string {
  if (!iso) return "";
  const mins = Math.round((new Date(iso).getTime() - Date.now()) / 60000);
  if (mins < 0) return `${Math.abs(mins)}m over`;
  if (mins < 60) return `${mins}m left`;
  return `${Math.round(mins / 60)}h left`;
}

export default function AdminSupportPage() {
  const [tab, setTab] = useState<(typeof TABS)[number]["key"]>("mine");
  const active = TABS.find((t) => t.key === tab)!;

  const { data, isLoading } = useQuery({
    queryKey: ["admin", "tickets", tab],
    queryFn: () => apiJson<{ data: Ticket[] }>(`/api/v1/admin/tickets?${active.query}`),
  });
  const tickets = data?.data ?? [];

  return (
    <div className="mx-auto max-w-4xl">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <p className="kicker text-trust">Support Desk</p>
          <h1 className="display mt-2 text-3xl text-ink">Tickets</h1>
        </div>
        <Link href="/admin/support/settings" className="rounded-full border border-line px-4 py-2 text-sm font-medium text-ink hover:bg-paper">
          Routing &amp; canned responses
        </Link>
      </div>

      <div className="mt-6 flex gap-2">
        {TABS.map((t) => (
          <button
            key={t.key}
            onClick={() => setTab(t.key)}
            className={`rounded-full px-4 py-1.5 text-sm font-medium transition-colors ${tab === t.key ? "bg-ink text-white" : "bg-paper text-muted hover:text-ink"}`}
          >
            {t.label}
          </button>
        ))}
      </div>

      {isLoading ? (
        <div className="mt-6 space-y-3">{Array.from({ length: 4 }).map((_, i) => <div key={i} className="shimmer h-16 rounded-[14px]" />)}</div>
      ) : tickets.length === 0 ? (
        <div className="mt-6"><EmptyState title="No tickets here" body="Tickets routed to you (or matching this filter) will appear here with their SLA countdown." /></div>
      ) : (
        <div className="mt-6 divide-y divide-line rounded-[14px] border border-line bg-white">
          {tickets.map((t) => (
            <Link key={t.id} href={`/admin/support/${t.id}`} className="flex flex-wrap items-center gap-3 px-5 py-4 transition-colors hover:bg-paper">
              <span className="mono text-xs text-muted">{t.reference}</span>
              <span className="min-w-[140px] flex-1">
                <span className="block font-semibold text-ink">{t.subject}</span>
                <span className="mono block text-xs text-muted">{t.student?.name} · {t.category.replace(/_/g, " ")}</span>
              </span>
              {t.breached ? (
                <span className="mono rounded-full bg-warn px-2.5 py-0.5 text-[10px] uppercase tracking-widest text-white">breached</span>
              ) : (
                <span className="mono text-xs text-muted">{dueLabel(t.first_response_due_at)}</span>
              )}
              <span className={`mono rounded-full px-2.5 py-0.5 text-[10px] uppercase tracking-widest ${STATUS_STYLES[t.status] ?? "bg-paper text-muted"}`}>
                {t.status.replace(/_/g, " ")}
              </span>
              <span className="text-trust">→</span>
            </Link>
          ))}
        </div>
      )}
    </div>
  );
}
