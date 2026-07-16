"use client";

import Link from "next/link";
import { use, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ApiError, apiJson } from "@/lib/api";
import {
  type CrmTask,
  type Lead,
  type LeadStage,
  type TimelineEvent,
  leadTypeLabel,
  slaStatus,
} from "@/lib/crm";

const inputCls =
  "rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust";

const TIMELINE_LABEL: Record<string, string> = {
  created: "Captured",
  stage_changed: "Stage change",
  assigned: "Assigned",
  note: "Note",
  task: "Task",
  masterclass_registered: "Masterclass",
  merged: "Merged",
  sla_breached: "SLA breach",
};

export default function LeadDetailPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const queryClient = useQueryClient();
  const [error, setError] = useState<string | null>(null);
  const [note, setNote] = useState("");
  const [taskTitle, setTaskTitle] = useState("");

  const leadKey = ["admin", "leads", id];
  const { data, isLoading } = useQuery({
    queryKey: leadKey,
    queryFn: () => apiJson<{ data: Lead }>(`/api/v1/admin/leads/${id}`),
  });
  const lead = data?.data;

  const { data: stages } = useQuery({
    queryKey: ["admin", "lead-stages"],
    queryFn: () => apiJson<{ data: LeadStage[] }>("/api/v1/admin/lead-stages"),
  });
  const { data: counselors } = useQuery({
    queryKey: ["admin", "leads", "counselors"],
    queryFn: () => apiJson<{ data: { id: number; name: string }[] }>("/api/v1/admin/leads/counselors"),
  });

  const invalidate = () => {
    void queryClient.invalidateQueries({ queryKey: leadKey });
    void queryClient.invalidateQueries({ queryKey: ["admin", "leads"] });
  };
  const onErr = (err: unknown, fallback: string) =>
    setError(err instanceof ApiError ? (err.firstError ?? err.message) : fallback);

  const move = useMutation({
    mutationFn: (stageId: number) => apiJson(`/api/v1/admin/leads/${id}/stage`, { method: "POST", body: JSON.stringify({ lead_stage_id: stageId }) }),
    onSuccess: () => { setError(null); invalidate(); },
    onError: (e) => onErr(e, "Could not move stage."),
  });
  const assign = useMutation({
    mutationFn: (counselorId: number) => apiJson(`/api/v1/admin/leads/${id}/assign`, { method: "POST", body: JSON.stringify({ counselor_id: counselorId }) }),
    onSuccess: () => { setError(null); invalidate(); },
    onError: (e) => onErr(e, "Could not assign."),
  });
  const addNote = useMutation({
    mutationFn: () => apiJson(`/api/v1/admin/leads/${id}/notes`, { method: "POST", body: JSON.stringify({ body: note }) }),
    onSuccess: () => { setNote(""); setError(null); invalidate(); },
    onError: (e) => onErr(e, "Could not add note."),
  });
  const createTask = useMutation({
    mutationFn: () => apiJson("/api/v1/admin/crm-tasks", { method: "POST", body: JSON.stringify({ lead_id: Number(id), title: taskTitle }) }),
    onSuccess: () => { setTaskTitle(""); setError(null); invalidate(); },
    onError: (e) => onErr(e, "Could not create task."),
  });
  const completeTask = useMutation({
    mutationFn: (taskId: number) => apiJson(`/api/v1/admin/crm-tasks/${taskId}/complete`, { method: "POST" }),
    onSuccess: () => invalidate(),
    onError: (e) => onErr(e, "Could not complete task."),
  });
  const merge = useMutation({
    mutationFn: (loserId: number) => apiJson(`/api/v1/admin/leads/${id}/merge`, { method: "POST", body: JSON.stringify({ loser_id: loserId }) }),
    onSuccess: () => { setError(null); invalidate(); },
    onError: (e) => onErr(e, "Could not merge."),
  });

  if (isLoading) return <div className="mx-auto max-w-5xl space-y-3"><div className="shimmer h-24 rounded-[14px]" /><div className="shimmer h-64 rounded-[14px]" /></div>;
  if (!lead) return <p className="mx-auto max-w-5xl text-sm text-muted">Lead not found.</p>;

  const sla = slaStatus(lead);

  return (
    <div className="mx-auto max-w-5xl">
      <Link href="/admin/leads" className="text-sm text-muted hover:text-ink">← Leads</Link>
      <div className="mt-2 flex flex-wrap items-start justify-between gap-4">
        <div>
          <p className="kicker text-trust">{leadTypeLabel(lead.lead_type)} lead</p>
          <h1 className="display mt-1 text-3xl text-ink">{lead.name}</h1>
          <p className="mono mt-1 text-sm text-muted">{lead.phone}{lead.email ? ` · ${lead.email}` : ""}</p>
        </div>
        <div className="text-right">
          <p className="mono text-3xl text-ink" title="Lead score">{lead.score}</p>
          <p className="mono text-[10px] uppercase tracking-widest text-muted">Score</p>
        </div>
      </div>

      {error && <p className="mt-4 rounded-[10px] bg-warn/10 px-3 py-2 text-sm text-warn">{error}</p>}

      <div className="mt-6 grid gap-6 md:grid-cols-[1fr_320px]">
        {/* Left: timeline + notes */}
        <div className="space-y-6">
          <section className="rounded-[14px] border border-line bg-white p-5">
            <p className="kicker text-muted">Add a note</p>
            <div className="mt-3 flex gap-2">
              <input value={note} onChange={(e) => setNote(e.target.value)} placeholder="Log a call, a reply, context…" className={`${inputCls} flex-1`} />
              <button onClick={() => addNote.mutate()} disabled={!note || addNote.isPending} className="rounded-full bg-trust px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">
                Save
              </button>
            </div>
          </section>

          <section className="rounded-[14px] border border-line bg-white p-5">
            <p className="kicker text-muted">Contact timeline</p>
            <ol className="mt-4 space-y-4">
              {(lead.timeline ?? []).map((ev: TimelineEvent) => (
                <li key={ev.id} className="flex gap-3">
                  <span className="mono mt-0.5 w-24 shrink-0 rounded-full bg-sky px-2 py-0.5 text-center text-[9px] uppercase tracking-widest text-deep">
                    {TIMELINE_LABEL[ev.type] ?? ev.type}
                  </span>
                  <div className="flex-1">
                    <p className="text-sm text-ink">{ev.body}</p>
                    <p className="mono text-[11px] text-muted">
                      {ev.occurred_at ? new Date(ev.occurred_at).toLocaleString() : ""}{ev.actor ? ` · ${ev.actor.name}` : ""}
                    </p>
                  </div>
                </li>
              ))}
              {(lead.timeline ?? []).length === 0 && <li className="text-sm text-muted">No events yet.</li>}
            </ol>
          </section>
        </div>

        {/* Right: controls */}
        <div className="space-y-6">
          <section className="rounded-[14px] border border-line bg-white p-5">
            <p className="kicker text-muted">Speed-to-lead</p>
            <p className={`mono mt-2 text-sm ${sla.kind === "breached" ? "text-warn" : sla.kind === "touched" ? "text-verify" : "text-ink"}`}>
              {sla.kind === "breached" && "SLA breached before first touch"}
              {sla.kind === "touched" && `First touch ${lead.first_touch_at ? new Date(lead.first_touch_at).toLocaleString() : ""}`}
              {sla.kind === "due" && (sla.minutesLeft <= 0 ? "Due now" : `${sla.minutesLeft} min to first touch`)}
              {sla.kind === "none" && "No SLA armed"}
            </p>
          </section>

          <section className="rounded-[14px] border border-line bg-white p-5">
            <label className="kicker text-muted">Stage
              <select value={lead.lead_stage_id ?? ""} onChange={(e) => move.mutate(Number(e.target.value))} className={`${inputCls} mt-2 w-full`}>
                {stages?.data.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
              </select>
            </label>
            <label className="kicker mt-4 block text-muted">Counselor
              <select value={lead.assigned_to ?? ""} onChange={(e) => assign.mutate(Number(e.target.value))} className={`${inputCls} mt-2 w-full`}>
                <option value="" disabled>Unassigned</option>
                {counselors?.data.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
              </select>
            </label>
          </section>

          {/* Tasks */}
          <section className="rounded-[14px] border border-line bg-white p-5">
            <p className="kicker text-muted">Tasks</p>
            <ul className="mt-3 space-y-2">
              {(lead.tasks ?? []).map((t: CrmTask) => (
                <li key={t.id} className="flex items-center gap-2 text-sm">
                  <input type="checkbox" checked={!!t.completed_at} onChange={() => !t.completed_at && completeTask.mutate(t.id)} className="accent-[var(--bj-trust)]" />
                  <span className={t.completed_at ? "text-muted line-through" : "text-ink"}>{t.title}</span>
                  {t.source === "sla" && <span className="mono rounded-full bg-warn/10 px-1.5 text-[9px] uppercase text-warn">SLA</span>}
                </li>
              ))}
              {(lead.tasks ?? []).length === 0 && <li className="text-sm text-muted">No tasks.</li>}
            </ul>
            <div className="mt-3 flex gap-2">
              <input value={taskTitle} onChange={(e) => setTaskTitle(e.target.value)} placeholder="New task…" className={`${inputCls} flex-1`} />
              <button onClick={() => createTask.mutate()} disabled={!taskTitle || createTask.isPending} className="rounded-full border border-line px-3 py-2 text-sm font-medium text-ink disabled:opacity-50">
                Add
              </button>
            </div>
          </section>

          {/* Merge tool */}
          {lead.duplicates && lead.duplicates.length > 0 && (
            <section className="rounded-[14px] border border-amber/40 bg-white p-5">
              <p className="kicker text-amber">Possible duplicates</p>
              <ul className="mt-3 space-y-2">
                {lead.duplicates.map((d) => (
                  <li key={d.id} className="flex items-center justify-between gap-2 text-sm">
                    <span>
                      <span className="text-ink">{d.name}</span>
                      <span className="mono ml-2 text-xs text-muted">#{d.id}</span>
                    </span>
                    <button onClick={() => merge.mutate(d.id)} disabled={merge.isPending} className="rounded-full border border-line px-3 py-1 text-xs font-medium text-ink hover:bg-paper disabled:opacity-50">
                      Merge in
                    </button>
                  </li>
                ))}
              </ul>
              <p className="mt-2 text-xs text-muted">The older record survives; fields and timelines fold in.</p>
            </section>
          )}
        </div>
      </div>
    </div>
  );
}
