"use client";

import Link from "next/link";
import { useMemo, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ApiError, apiJson } from "@/lib/api";
import {
  type Lead,
  type LeadStage,
  leadTypeLabel,
  slaStatus,
} from "@/lib/crm";
import { EmptyState } from "@/components/ui/EmptyState";

const LEAD_TYPES = ["masterclass", "counselling", "bootcamp", "contact", "waitlist"];
const COURSES = ["data-engineering", "devops-cloud", "python-backend", "data-analytics"];

const inputCls =
  "rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust";

function SlaBadge({ lead }: { lead: Lead }) {
  const status = slaStatus(lead);
  if (status.kind === "breached")
    return <span className="mono rounded-full bg-warn/10 px-2 py-0.5 text-[10px] uppercase tracking-widest text-warn">SLA breached</span>;
  if (status.kind === "touched")
    return <span className="mono rounded-full bg-verify-bg px-2 py-0.5 text-[10px] uppercase tracking-widest text-verify">Touched</span>;
  if (status.kind === "due")
    return (
      <span className={`mono rounded-full px-2 py-0.5 text-[10px] uppercase tracking-widest ${status.minutesLeft <= 0 ? "bg-warn/10 text-warn" : "bg-sky text-deep"}`}>
        {status.minutesLeft <= 0 ? "Due now" : `${status.minutesLeft}m left`}
      </span>
    );
  return null;
}

export default function AdminLeadsPage() {
  const queryClient = useQueryClient();
  const [filters, setFilters] = useState({ lead_type: "", lead_stage_id: "", course_slug: "", search: "" });
  const [panel, setPanel] = useState<null | "create" | "import">(null);
  const [banner, setBanner] = useState<{ kind: "ok" | "err"; text: string } | null>(null);

  const { data: stages } = useQuery({
    queryKey: ["admin", "lead-stages"],
    queryFn: () => apiJson<{ data: LeadStage[] }>("/api/v1/admin/lead-stages"),
  });

  const query = useMemo(() => {
    const p = new URLSearchParams();
    Object.entries(filters).forEach(([k, v]) => v && p.set(k, v));
    return p.toString();
  }, [filters]);

  const { data: leads, isLoading } = useQuery({
    queryKey: ["admin", "leads", filters],
    queryFn: () => apiJson<{ data: Lead[] }>(`/api/v1/admin/leads${query ? `?${query}` : ""}`),
  });

  const refresh = () => void queryClient.invalidateQueries({ queryKey: ["admin", "leads"] });

  return (
    <div className="mx-auto max-w-6xl">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <p className="kicker text-trust">CRM</p>
          <h1 className="display mt-2 text-3xl text-ink">Leads inbox</h1>
        </div>
        <div className="flex items-center gap-2">
          <Link href="/admin/leads/board" className="rounded-full border border-line px-4 py-2 text-sm font-medium text-ink hover:bg-paper">
            Pipeline board →
          </Link>
          <button
            onClick={() => { setPanel(panel === "import" ? null : "import"); setBanner(null); }}
            className="rounded-full border border-line px-4 py-2 text-sm font-medium text-ink hover:bg-paper"
          >
            Import CSV
          </button>
          <button
            onClick={() => { setPanel(panel === "create" ? null : "create"); setBanner(null); }}
            className="rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white hover:bg-deep"
          >
            New lead
          </button>
        </div>
      </div>

      {banner && (
        <p className={`mt-4 rounded-[10px] px-3 py-2 text-sm ${banner.kind === "ok" ? "bg-verify-bg text-verify" : "bg-warn/10 text-warn"}`}>
          {banner.text}
        </p>
      )}

      {panel === "create" && (
        <CreateLeadPanel
          onDone={(msg) => { setPanel(null); setBanner({ kind: "ok", text: msg }); refresh(); }}
          onError={(msg) => setBanner({ kind: "err", text: msg })}
        />
      )}
      {panel === "import" && (
        <ImportPanel
          onDone={(msg) => { setPanel(null); setBanner({ kind: "ok", text: msg }); refresh(); }}
          onError={(msg) => setBanner({ kind: "err", text: msg })}
        />
      )}

      {/* Filters */}
      <div className="mt-6 flex flex-wrap items-end gap-3 rounded-[14px] border border-line bg-white p-4">
        <input
          placeholder="Search name, phone, email…"
          value={filters.search}
          onChange={(e) => setFilters({ ...filters, search: e.target.value })}
          className={`${inputCls} min-w-[220px] flex-1`}
        />
        <select value={filters.lead_type} onChange={(e) => setFilters({ ...filters, lead_type: e.target.value })} className={inputCls}>
          <option value="">All types</option>
          {LEAD_TYPES.map((t) => <option key={t} value={t}>{leadTypeLabel(t)}</option>)}
        </select>
        <select value={filters.lead_stage_id} onChange={(e) => setFilters({ ...filters, lead_stage_id: e.target.value })} className={inputCls}>
          <option value="">All stages</option>
          {stages?.data.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
        </select>
        <select value={filters.course_slug} onChange={(e) => setFilters({ ...filters, course_slug: e.target.value })} className={inputCls}>
          <option value="">All courses</option>
          {COURSES.map((c) => <option key={c} value={c}>{c}</option>)}
        </select>
        {(filters.lead_type || filters.lead_stage_id || filters.course_slug || filters.search) && (
          <button onClick={() => setFilters({ lead_type: "", lead_stage_id: "", course_slug: "", search: "" })} className="text-sm text-muted hover:text-ink">
            Clear
          </button>
        )}
      </div>

      {/* List */}
      {isLoading ? (
        <div className="mt-6 space-y-3">
          {Array.from({ length: 6 }).map((_, i) => <div key={i} className="shimmer h-16 rounded-[14px]" />)}
        </div>
      ) : leads && leads.data.length === 0 ? (
        <div className="mt-6">
          <EmptyState title="No leads match" body="Adjust the filters, import a CSV, or add a lead by hand to start working the pipeline." />
        </div>
      ) : (
        <div className="mt-6 divide-y divide-line rounded-[14px] border border-line bg-white">
          {leads?.data.map((lead) => (
            <Link key={lead.id} href={`/admin/leads/${lead.id}`} className="flex flex-wrap items-center gap-3 px-5 py-4 transition-colors hover:bg-paper">
              <span className="min-w-[160px] flex-1">
                <span className="font-semibold text-ink">{lead.name}</span>
                <span className="mono ml-2 text-xs text-muted">{lead.phone}</span>
              </span>
              <span className="mono rounded-full bg-sky px-2.5 py-0.5 text-[10px] uppercase tracking-widest text-deep">
                {leadTypeLabel(lead.lead_type)}
              </span>
              {lead.stage && (
                <span className="mono rounded-full border border-line px-2.5 py-0.5 text-[10px] uppercase tracking-widest text-muted">
                  {lead.stage.name}
                </span>
              )}
              <SlaBadge lead={lead} />
              <span className="mono w-10 text-right text-sm text-ink" title="Lead score">{lead.score}</span>
              <span className="w-28 truncate text-right text-xs text-muted">{lead.assignee?.name ?? "Unassigned"}</span>
              <span className="text-trust">→</span>
            </Link>
          ))}
        </div>
      )}
    </div>
  );
}

function CreateLeadPanel({ onDone, onError }: { onDone: (msg: string) => void; onError: (msg: string) => void }) {
  const [form, setForm] = useState({ lead_type: "masterclass", name: "", phone: "", email: "", course_slug: "" });
  const create = useMutation({
    mutationFn: () =>
      apiJson("/api/v1/admin/leads", {
        method: "POST",
        body: JSON.stringify({
          lead_type: form.lead_type,
          name: form.name,
          phone: form.phone,
          email: form.email || null,
          course_slug: form.course_slug || null,
        }),
      }),
    onSuccess: () => onDone(`Lead "${form.name}" created.`),
    onError: (err) => onError(err instanceof ApiError ? (err.firstError ?? err.message) : "Could not create lead."),
  });

  return (
    <form
      onSubmit={(e) => { e.preventDefault(); create.mutate(); }}
      className="mt-4 flex flex-wrap items-end gap-3 rounded-[14px] border border-line bg-white p-5"
    >
      <label className="flex flex-col gap-1 text-xs text-muted">Type
        <select value={form.lead_type} onChange={(e) => setForm({ ...form, lead_type: e.target.value })} className={inputCls}>
          {LEAD_TYPES.map((t) => <option key={t} value={t}>{leadTypeLabel(t)}</option>)}
        </select>
      </label>
      <label className="flex flex-col gap-1 text-xs text-muted">Name
        <input required value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} className={inputCls} />
      </label>
      <label className="flex flex-col gap-1 text-xs text-muted">Phone
        <input required value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} className={`${inputCls} mono`} />
      </label>
      <label className="flex flex-col gap-1 text-xs text-muted">Email
        <input type="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} className={inputCls} />
      </label>
      <label className="flex flex-col gap-1 text-xs text-muted">Course
        <select value={form.course_slug} onChange={(e) => setForm({ ...form, course_slug: e.target.value })} className={inputCls}>
          <option value="">—</option>
          {COURSES.map((c) => <option key={c} value={c}>{c}</option>)}
        </select>
      </label>
      <button disabled={create.isPending || !form.name || !form.phone} className="rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white disabled:opacity-50">
        {create.isPending ? "Saving…" : "Create"}
      </button>
    </form>
  );
}

function ImportPanel({ onDone, onError }: { onDone: (msg: string) => void; onError: (msg: string) => void }) {
  const [csv, setCsv] = useState("name,phone,email,lead_type,course_slug\n");
  const importCsv = useMutation({
    mutationFn: () => apiJson<{ data: { imported: number; duplicates: number; skipped: { row: number; reason: string }[] } }>("/api/v1/admin/leads/import", {
      method: "POST",
      body: JSON.stringify({ csv }),
    }),
    onSuccess: (res) => onDone(`Imported ${res.data.imported} · ${res.data.duplicates} duplicate(s) skipped · ${res.data.skipped.length} invalid.`),
    onError: (err) => onError(err instanceof ApiError ? (err.firstError ?? err.message) : "Import failed."),
  });

  return (
    <div className="mt-4 rounded-[14px] border border-line bg-white p-5">
      <p className="kicker text-muted">Paste CSV — header row <span className="mono">name,phone,email,lead_type,course_slug</span></p>
      <textarea
        value={csv}
        onChange={(e) => setCsv(e.target.value)}
        rows={5}
        className="mono mt-3 w-full rounded-[10px] border border-line bg-paper px-3 py-2 text-xs text-ink outline-none focus:border-trust"
      />
      <button onClick={() => importCsv.mutate()} disabled={importCsv.isPending} className="mt-3 rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white disabled:opacity-50">
        {importCsv.isPending ? "Importing…" : "Import leads"}
      </button>
    </div>
  );
}
