"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import {
  DndContext,
  type DragEndEvent,
  PointerSensor,
  KeyboardSensor,
  useDraggable,
  useDroppable,
  useSensor,
  useSensors,
} from "@dnd-kit/core";
import { CSS } from "@dnd-kit/utilities";
import { apiJson, ApiError } from "@/lib/api";
import { type Lead, type LeadStage, leadTypeLabel } from "@/lib/crm";

type BoardData = { stages: LeadStage[]; leads: Lead[] };

function LeadCard({ lead }: { lead: Lead }) {
  const { attributes, listeners, setNodeRef, transform, isDragging } = useDraggable({ id: lead.id });
  const style = transform
    ? { transform: CSS.Translate.toString(transform), zIndex: 30 }
    : undefined;

  return (
    <div
      ref={setNodeRef}
      style={style}
      {...listeners}
      {...attributes}
      className={`cursor-grab rounded-[10px] border border-line bg-white p-3 shadow-soft transition-shadow ${isDragging ? "opacity-80 shadow-lg" : "hover:shadow-md"}`}
    >
      <div className="flex items-center justify-between gap-2">
        <span className="truncate text-sm font-semibold text-ink">{lead.name}</span>
        <span className="mono text-xs text-muted" title="Score">{lead.score}</span>
      </div>
      <p className="mono mt-0.5 text-[11px] text-muted">{lead.phone}</p>
      <div className="mt-2 flex items-center justify-between gap-2">
        <span className="mono rounded-full bg-sky px-2 py-0.5 text-[9px] uppercase tracking-widest text-deep">
          {leadTypeLabel(lead.lead_type)}
        </span>
        <Link href={`/admin/leads/${lead.id}`} className="text-[11px] text-trust hover:underline" onPointerDown={(e) => e.stopPropagation()}>
          Open
        </Link>
      </div>
    </div>
  );
}

function Column({ stage, leads }: { stage: LeadStage; leads: Lead[] }) {
  const { setNodeRef, isOver } = useDroppable({ id: `stage-${stage.id}` });
  return (
    <div className="flex w-64 shrink-0 flex-col">
      <div className="flex items-center justify-between px-1 pb-2">
        <span className="mono text-[11px] uppercase tracking-widest text-ink">{stage.name}</span>
        <span className="mono text-[11px] text-muted">{leads.length}</span>
      </div>
      <div
        ref={setNodeRef}
        className={`flex min-h-[120px] flex-1 flex-col gap-2 rounded-[14px] border border-dashed p-2 transition-colors ${isOver ? "border-trust bg-sky/40" : "border-line bg-paper"}`}
      >
        {leads.map((lead) => <LeadCard key={lead.id} lead={lead} />)}
      </div>
    </div>
  );
}

export default function LeadsBoardPage() {
  const [leads, setLeads] = useState<Lead[]>([]);
  const [error, setError] = useState<string | null>(null);
  const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 5 } }), useSensor(KeyboardSensor));

  const { data, isLoading } = useQuery({
    queryKey: ["admin", "leads", "board"],
    queryFn: () => apiJson<BoardData>("/api/v1/admin/leads/board"),
  });

  useEffect(() => { if (data) setLeads(data.leads); }, [data]);

  async function onDragEnd(event: DragEndEvent) {
    const overId = event.over?.id;
    if (typeof overId !== "string" || !overId.startsWith("stage-")) return;
    const stageId = Number(overId.replace("stage-", ""));
    const leadId = Number(event.active.id);
    const lead = leads.find((l) => l.id === leadId);
    if (!lead || lead.lead_stage_id === stageId) return;

    const previous = leads;
    const stage = data?.stages.find((s) => s.id === stageId) ?? null;
    setLeads((cur) => cur.map((l) => (l.id === leadId ? { ...l, lead_stage_id: stageId, stage } : l)));
    setError(null);

    try {
      await apiJson(`/api/v1/admin/leads/${leadId}/stage`, {
        method: "POST",
        body: JSON.stringify({ lead_stage_id: stageId }),
      });
    } catch (err) {
      setLeads(previous); // rollback
      setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Could not move lead.");
    }
  }

  return (
    <div className="mx-auto max-w-[1400px]">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <p className="kicker text-trust">CRM</p>
          <h1 className="display mt-2 text-3xl text-ink">Pipeline board</h1>
        </div>
        <Link href="/admin/leads" className="rounded-full border border-line px-4 py-2 text-sm font-medium text-ink hover:bg-paper">
          ← Inbox
        </Link>
      </div>

      {error && <p className="mt-4 rounded-[10px] bg-warn/10 px-3 py-2 text-sm text-warn">{error}</p>}

      {isLoading ? (
        <div className="mt-6 flex gap-4">
          {Array.from({ length: 5 }).map((_, i) => <div key={i} className="shimmer h-64 w-64 rounded-[14px]" />)}
        </div>
      ) : (
        <DndContext sensors={sensors} onDragEnd={onDragEnd}>
          <div className="mt-6 flex gap-4 overflow-x-auto pb-4">
            {data?.stages.map((stage) => (
              <Column key={stage.id} stage={stage} leads={leads.filter((l) => l.lead_stage_id === stage.id)} />
            ))}
          </div>
        </DndContext>
      )}
    </div>
  );
}
