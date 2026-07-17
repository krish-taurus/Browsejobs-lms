"use client";

import Link from "next/link";
import { use, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ApiError, apiJson } from "@/lib/api";
import { formatPaise } from "@/lib/money";

type Message = {
  id: number;
  author_type: string;
  author_name?: string | null;
  body: string;
  is_internal: boolean;
  channel: string;
};

/** Staff-only triage signals (PRD §6.13). Absent from the student's own view. */
type Triage = {
  category: string | null;
  urgency: string | null;
  sentiment: string | null;
  priority_raised: boolean;
  duplicate_of_id: number | null;
  triaged_at: string | null;
  category_differs: boolean;
};

type Ticket = {
  id: number;
  reference: string;
  subject: string;
  status: string;
  category: string;
  priority: string;
  escalated: boolean;
  assignee: { id: number; name: string } | null;
  messages: Message[];
  ai?: Triage;
};

type Context = {
  student: { name: string; phone: string | null; email: string | null };
  batch: { number: string; course: string | null } | null;
  fee: { outstanding_paise: number; blocked: boolean };
  attendance_pct: number;
  recent_tickets: { id: number; reference: string; status: string }[];
};

type Canned = { id: number; title: string; body: string };
type Staff = { id: number; name: string };

const STATUSES = ["open", "in_progress", "waiting_on_student", "resolved", "closed"];
const PRIORITIES = ["low", "normal", "high", "urgent"];

/**
 * Amber is reserved for coach notes and review stars, and red for danger — so a
 * sentiment read, which is advisory and often wrong, gets neither. Ink on paper: staff
 * see it, it does not shout.
 */
const SENTIMENT_LABEL: Record<string, string> = {
  calm: "sounds calm",
  neutral: "sounds neutral",
  frustrated: "sounds frustrated",
  angry: "sounds angry",
};

export default function AdminTicketWorkspace({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const qc = useQueryClient();
  const [reply, setReply] = useState("");
  const [internal, setInternal] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const { data, isLoading } = useQuery({
    queryKey: ["admin", "ticket", id],
    queryFn: () => apiJson<{ data: Ticket; context: Context | null }>(`/api/v1/admin/tickets/${id}`),
  });
  const canned = useQuery({ queryKey: ["admin", "canned"], queryFn: () => apiJson<{ data: Canned[] }>("/api/v1/admin/canned-responses") });
  const staff = useQuery({ queryKey: ["admin", "ticket-staff"], queryFn: () => apiJson<{ data: Staff[] }>("/api/v1/admin/tickets/staff") });

  const refresh = () => qc.invalidateQueries({ queryKey: ["admin", "ticket", id] });
  const onError = (err: unknown) => setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Request failed.");

  const post = useMutation({
    mutationFn: (b: { path: string; body: object }) => apiJson(`/api/v1/admin/tickets/${id}/${b.path}`, { method: "POST", body: JSON.stringify(b.body) }),
    onSuccess: () => { setError(null); setReply(""); void refresh(); },
    onError,
  });

  // Drafts into the reply box; the staff member edits and decides whether to send.
  const draft = useMutation({
    mutationFn: () => apiJson<{ data: string | null }>(`/api/v1/admin/tickets/${id}/draft-reply`, { method: "POST" }),
    onSuccess: (r) => {
      setError(r.data ? null : "No draft available right now — write the reply yourself.");
      if (r.data) setReply(r.data);
    },
    onError,
  });

  const t = data?.data;
  const ctx = data?.context;
  const ai = t?.ai;

  if (isLoading || !t) return <div className="mx-auto max-w-5xl"><div className="shimmer h-64 rounded-[14px]" /></div>;

  return (
    <div className="mx-auto max-w-5xl">
      <Link href="/admin/support" className="text-sm text-trust hover:underline">← Tickets</Link>

      <div className="mt-4 grid gap-6 lg:grid-cols-[1fr_280px]">
        {/* Thread */}
        <div>
          <div className="flex flex-wrap items-center justify-between gap-2">
            <div>
              <p className="mono text-xs text-muted">{t.reference} · {t.category.replace(/_/g, " ")}</p>
              <h1 className="display mt-1 text-2xl text-ink">{t.subject}</h1>
            </div>
            {t.escalated && <span className="mono rounded-full bg-warn px-2.5 py-0.5 text-[10px] uppercase tracking-widest text-white">escalated</span>}
          </div>

          {error && <p className="mt-4 rounded-[10px] bg-warn/10 px-3 py-2 text-sm text-warn">{error}</p>}

          <div className="mt-5 space-y-3">
            {t.messages.map((m) => (
              <div key={m.id} className={`rounded-[14px] border p-4 ${m.is_internal ? "border-amber/40 bg-amber/10" : m.author_type === "student" ? "border-line bg-sky/40" : "border-line bg-white"}`}>
                <div className="flex items-center justify-between">
                  <span className="text-xs font-semibold text-ink">{m.author_type === "student" ? (m.author_name ?? "Student") : (m.author_name ?? "Staff")}{m.is_internal ? " · internal note" : ""}</span>
                  <span className="mono text-[10px] uppercase tracking-widest text-muted">{m.channel}</span>
                </div>
                <p className="mt-2 whitespace-pre-wrap text-sm text-ink">{m.body}</p>
              </div>
            ))}
          </div>

          {/* Reply box */}
          <div className="mt-5 rounded-[14px] border border-line bg-white p-4">
            {(canned.data?.data.length ?? 0) > 0 && (
              <select
                onChange={(e) => { const c = canned.data?.data.find((x) => String(x.id) === e.target.value); if (c) setReply(c.body); }}
                className="mb-2 rounded-[10px] border border-line bg-white px-3 py-1.5 text-xs text-muted"
                defaultValue=""
              >
                <option value="" disabled>Insert canned response…</option>
                {canned.data?.data.map((c) => <option key={c.id} value={c.id}>{c.title}</option>)}
              </select>
            )}
            <textarea value={reply} onChange={(e) => setReply(e.target.value)} rows={3} placeholder="Reply to the student…" className="w-full rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust" />
            <div className="mt-2 flex flex-wrap items-center gap-3">
              {/* Ghost, not primary: Send stays the one blue action in this view. */}
              <button
                onClick={() => draft.mutate()}
                disabled={draft.isPending}
                className="rounded-full border border-line px-4 py-1.5 text-xs font-semibold text-ink hover:border-trust disabled:opacity-50"
              >
                {draft.isPending ? "Drafting…" : "Draft with AI"}
              </button>
              <label className="flex items-center gap-1.5 text-xs text-muted">
                <input type="checkbox" checked={internal} onChange={(e) => setInternal(e.target.checked)} className="h-3.5 w-3.5 rounded border-line text-trust" />
                Internal note
              </label>
              <button
                onClick={() => post.mutate({ path: "reply", body: { body: reply, is_internal: internal } })}
                disabled={post.isPending || !reply.trim()}
                className="rounded-full bg-trust px-5 py-1.5 text-sm font-semibold text-white disabled:opacity-50"
              >
                {internal ? "Add note" : "Send reply"}
              </button>
            </div>
          </div>
        </div>

        {/* Sidebar */}
        <aside className="space-y-4">
          {ai?.triaged_at && (
            <div className="rounded-[14px] border border-line bg-paper p-4">
              <p className="kicker text-muted">AI triage</p>

              <dl className="mt-3 space-y-1.5 text-sm">
                {ai.sentiment && (
                  <div className="flex justify-between">
                    <dt className="text-muted">Tone</dt>
                    <dd className="text-ink">{SENTIMENT_LABEL[ai.sentiment] ?? ai.sentiment}</dd>
                  </div>
                )}
                {/* A suggestion is only worth the space when it disagrees with the human. */}
                {ai.category_differs && ai.category && (
                  <div className="flex justify-between">
                    <dt className="text-muted">Suggests</dt>
                    <dd className="text-ink">{ai.category.replace(/_/g, " ")}</dd>
                  </div>
                )}
              </dl>

              {ai.duplicate_of_id && (
                <p className="mt-3 text-xs text-muted">
                  May repeat{" "}
                  <Link href={`/admin/support/${ai.duplicate_of_id}`} className="text-trust hover:underline">
                    ticket #{ai.duplicate_of_id}
                  </Link>
                </p>
              )}

              {ai.priority_raised && (
                <div className="mt-3 border-t border-line pt-3">
                  <p className="text-xs text-ink">
                    AI raised this to <span className="mono">{t.priority}</span>. A person did not.
                  </p>
                  <select
                    defaultValue=""
                    onChange={(e) => e.target.value && post.mutate({ path: "clear-ai-priority", body: { priority: e.target.value } })}
                    className="mt-2 w-full rounded-[10px] border border-line bg-white px-3 py-1.5 text-xs text-ink"
                  >
                    <option value="" disabled>Put it back to…</option>
                    {PRIORITIES.map((p) => <option key={p} value={p}>{p}</option>)}
                  </select>
                </div>
              )}

              <p className="mt-3 text-[11px] leading-snug text-muted">
                Suggestions only. Nothing here routed, replied, or resolved anything.
              </p>
            </div>
          )}

          <div className="rounded-[14px] border border-line bg-white p-4">
            <p className="kicker text-muted">Actions</p>
            <div className="mt-3 space-y-2">
              <select
                value={t.status}
                onChange={(e) => post.mutate({ path: "status", body: { status: e.target.value } })}
                className="w-full rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink"
              >
                {STATUSES.map((s) => <option key={s} value={s}>{s.replace(/_/g, " ")}</option>)}
              </select>
              <select
                value={t.assignee?.id ?? ""}
                onChange={(e) => e.target.value && post.mutate({ path: "assign", body: { assignee_id: Number(e.target.value) } })}
                className="w-full rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink"
              >
                <option value="">Reassign…</option>
                {staff.data?.data.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
              </select>
              {!t.escalated && (
                <button onClick={() => post.mutate({ path: "escalate", body: {} })} className="w-full rounded-full border border-warn px-3 py-2 text-sm font-semibold text-warn hover:bg-warn/10">
                  Escalate to admin
                </button>
              )}
            </div>
          </div>

          {ctx && (
            <div className="rounded-[14px] border border-line bg-white p-4">
              <p className="kicker text-muted">Student</p>
              <p className="mt-2 font-semibold text-ink">{ctx.student.name}</p>
              <p className="mono text-xs text-muted">{ctx.student.phone ?? ctx.student.email ?? "—"}</p>
              <dl className="mt-3 space-y-1.5 text-sm">
                <div className="flex justify-between"><dt className="text-muted">Batch</dt><dd className="text-ink">{ctx.batch ? `${ctx.batch.number}` : "—"}</dd></div>
                <div className="flex justify-between"><dt className="text-muted">Attendance</dt><dd className="mono text-ink">{ctx.attendance_pct}%</dd></div>
                <div className="flex justify-between"><dt className="text-muted">Outstanding</dt><dd className="mono text-ink">{formatPaise(ctx.fee.outstanding_paise)}</dd></div>
                <div className="flex justify-between"><dt className="text-muted">Access</dt><dd className={ctx.fee.blocked ? "text-warn" : "text-verify"}>{ctx.fee.blocked ? "Blocked" : "OK"}</dd></div>
              </dl>
              {ctx.recent_tickets.length > 1 && (
                <p className="mt-3 text-xs text-muted">{ctx.recent_tickets.length} tickets total</p>
              )}
            </div>
          )}
        </aside>
      </div>
    </div>
  );
}
