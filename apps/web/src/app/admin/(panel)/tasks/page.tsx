"use client";

import Link from "next/link";
import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiJson } from "@/lib/api";
import { type CrmTask } from "@/lib/crm";
import { EmptyState } from "@/components/ui/EmptyState";

export default function MyTasksPage() {
  const queryClient = useQueryClient();
  const [includeDone, setIncludeDone] = useState(false);

  const { data: tasks, isLoading } = useQuery({
    queryKey: ["admin", "crm-tasks", { includeDone }],
    queryFn: () => apiJson<{ data: CrmTask[] }>(`/api/v1/admin/crm-tasks${includeDone ? "?include_done=1" : ""}`),
  });

  const complete = useMutation({
    mutationFn: (taskId: number) => apiJson(`/api/v1/admin/crm-tasks/${taskId}/complete`, { method: "POST" }),
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: ["admin", "crm-tasks"] }),
  });

  function dueLabel(t: CrmTask): { text: string; overdue: boolean } {
    if (!t.due_at) return { text: "No due date", overdue: false };
    const due = new Date(t.due_at);
    return { text: due.toLocaleString(), overdue: !t.completed_at && due.getTime() < Date.now() };
  }

  return (
    <div className="mx-auto max-w-3xl">
      <div className="flex items-center justify-between">
        <div>
          <p className="kicker text-trust">CRM</p>
          <h1 className="display mt-2 text-3xl text-ink">My tasks</h1>
        </div>
        <label className="flex items-center gap-2 text-sm text-muted">
          <input type="checkbox" checked={includeDone} onChange={(e) => setIncludeDone(e.target.checked)} className="accent-[var(--bj-trust)]" />
          Show completed
        </label>
      </div>

      {isLoading ? (
        <div className="mt-6 space-y-3">{Array.from({ length: 4 }).map((_, i) => <div key={i} className="shimmer h-14 rounded-[14px]" />)}</div>
      ) : tasks && tasks.data.length === 0 ? (
        <div className="mt-6"><EmptyState title="Inbox zero" body="No open tasks assigned to you. New follow-ups and SLA breaches will appear here." /></div>
      ) : (
        <div className="mt-6 divide-y divide-line rounded-[14px] border border-line bg-white">
          {tasks?.data.map((t) => {
            const due = dueLabel(t);
            return (
              <div key={t.id} className="flex items-center gap-3 px-5 py-4">
                <input type="checkbox" checked={!!t.completed_at} onChange={() => !t.completed_at && complete.mutate(t.id)} className="accent-[var(--bj-trust)]" />
                <div className="flex-1">
                  <p className={`text-sm ${t.completed_at ? "text-muted line-through" : "text-ink"}`}>{t.title}</p>
                  <p className="mono text-[11px] text-muted">
                    {t.lead ? t.lead.name : "—"} · <span className={due.overdue ? "text-warn" : ""}>{due.text}</span>
                  </p>
                </div>
                {t.source === "sla" && <span className="mono rounded-full bg-warn/10 px-2 py-0.5 text-[9px] uppercase tracking-widest text-warn">SLA</span>}
                {t.lead && <Link href={`/admin/leads/${t.lead.id}`} className="text-sm text-trust hover:underline">Open</Link>}
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}
