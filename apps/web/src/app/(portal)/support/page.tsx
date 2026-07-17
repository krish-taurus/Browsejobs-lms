"use client";

import Link from "next/link";
import { useCallback, useEffect, useState } from "react";
import { ApiError, apiJson } from "@/lib/api";
import { EmptyState } from "@/components/ui/EmptyState";

type Ticket = {
  id: number;
  reference: string;
  subject: string;
  category: string;
  status: string;
  created_at: string | null;
  assignee: { name: string; role?: string | null } | null;
};

type Citation = { label: string; href: string | null };

/** The AI first-response offered before a ticket is created (PRD §6.13). */
type Deflection = {
  id: number;
  category: string;
  answer: string;
  confidence: string;
  citations: Citation[];
};

const CATEGORIES = [
  { value: "payments", label: "Payments" },
  { value: "technical", label: "Technical" },
  { value: "mentorship", label: "Mentorship" },
  { value: "training", label: "Training" },
  { value: "interview_prep", label: "Interview Prep" },
  { value: "other", label: "Other" },
];

const STATUS_STYLES: Record<string, string> = {
  open: "bg-sky text-deep",
  in_progress: "bg-sky text-deep",
  waiting_on_student: "bg-amber/15 text-ink2",
  resolved: "bg-verify-bg text-verify",
  closed: "bg-paper text-muted",
};

const inputCls =
  "rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust";

export default function SupportPage() {
  const [tickets, setTickets] = useState<Ticket[]>([]);
  const [loading, setLoading] = useState(true);
  const [showForm, setShowForm] = useState(false);
  const [form, setForm] = useState({ category: "technical", subject: "", body: "", priority: "normal" });
  const [files, setFiles] = useState<FileList | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [checking, setChecking] = useState(false);
  const [deflection, setDeflection] = useState<Deflection | null>(null);
  const [answered, setAnswered] = useState(false);

  const load = useCallback(() => {
    apiJson<{ data: Ticket[] }>("/api/v1/me/tickets")
      .then((r) => setTickets(r.data))
      .catch(() => setTickets([]))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => { load(); }, [load]);

  function resetForm() {
    setForm({ category: "technical", subject: "", body: "", priority: "normal" });
    setFiles(null);
    setDeflection(null);
    setShowForm(false);
  }

  async function createTicket(deflectionId?: number) {
    setError(null);
    setSubmitting(true);
    try {
      const fd = new FormData();
      fd.set("category", form.category);
      fd.set("subject", form.subject);
      fd.set("body", form.body);
      fd.set("priority", form.priority);
      if (deflectionId) fd.set("deflection_id", String(deflectionId));
      if (files) Array.from(files).forEach((f) => fd.append("attachments[]", f));
      await apiJson("/api/v1/me/tickets", { method: "POST", body: fd });
      resetForm();
      load();
    } catch (err) {
      setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Could not raise the ticket.");
    } finally {
      setSubmitting(false);
    }
  }

  /**
   * Ask the AI first-response layer before creating the ticket. If it has nothing to
   * say — or is unreachable — the ticket is raised immediately, so the assistive layer
   * never adds a step for the students it cannot help.
   */
  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setChecking(true);
    try {
      const r = await apiJson<{ data: Deflection | null }>("/api/v1/me/tickets/deflect", {
        method: "POST",
        body: JSON.stringify({ category: form.category, body: form.body }),
      });
      if (r.data) {
        setDeflection(r.data);
        return;
      }
    } catch {
      // Deflection is a convenience, never a gate — fall through and raise the ticket.
    } finally {
      setChecking(false);
    }
    await createTicket();
  }

  async function acceptAnswer() {
    if (!deflection) return;
    try {
      await apiJson(`/api/v1/me/tickets/deflect/${deflection.id}/accept`, { method: "POST" });
    } catch {
      // Bookkeeping only — the student is done either way.
    }
    resetForm();
    setAnswered(true);
  }

  return (
    <div className="mx-auto max-w-2xl">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <p className="kicker text-trust">Help &amp; Support</p>
          <h1 className="display mt-2 text-3xl text-ink">My Tickets</h1>
        </div>
        <button
          onClick={() => { setShowForm(!showForm); setError(null); setAnswered(false); }}
          className="rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white hover:bg-deep"
        >
          Raise a concern
        </button>
      </div>

      {answered && (
        <p className="mt-5 rounded-[14px] border border-line bg-verify-bg px-5 py-3 text-sm text-ink">
          Glad that helped. Raise a concern any time if something else comes up.
        </p>
      )}

      {showForm && deflection && (
        <div className="mt-5 rounded-[14px] border border-line bg-white p-5">
          <p className="kicker text-trust">Answer</p>
          <p className="mt-3 whitespace-pre-line text-sm leading-relaxed text-ink">{deflection.answer}</p>

          {deflection.citations.length > 0 && (
            <div className="mt-3 flex flex-wrap items-center gap-2">
              <span className="mono text-[10px] uppercase tracking-widest text-muted">Based on</span>
              {deflection.citations.map((c) =>
                c.href ? (
                  <Link key={c.label} href={c.href} className="mono text-xs text-trust underline underline-offset-2">
                    {c.label}
                  </Link>
                ) : (
                  <span key={c.label} className="mono text-xs text-muted">{c.label}</span>
                ),
              )}
            </div>
          )}

          <p className="mt-4 text-sm font-semibold text-ink">Does this answer it?</p>
          <div className="mt-2.5 flex flex-wrap gap-2.5">
            <button
              onClick={acceptAnswer}
              className="rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white hover:bg-deep"
            >
              Yes, that answers it
            </button>
            {/* Never buried: one tap to a human, and the AI answer is not a prerequisite. */}
            <button
              onClick={() => createTicket(deflection.id)}
              disabled={submitting}
              className="rounded-full border border-line px-5 py-2 text-sm font-semibold text-ink hover:border-trust disabled:opacity-50"
            >
              {submitting ? "Sending…" : "No — raise the ticket anyway"}
            </button>
          </div>
          {error && <p className="mt-3 rounded-[10px] bg-warn/10 px-3 py-2 text-sm text-warn">{error}</p>}
        </div>
      )}

      {showForm && !deflection && (
        <form onSubmit={submit} className="mt-5 rounded-[14px] border border-line bg-white p-5">
          <p className="kicker text-muted">New concern</p>
          <div className="mt-3 grid gap-2.5">
            <div className="flex gap-2.5">
              <select value={form.category} onChange={(e) => setForm({ ...form, category: e.target.value })} className={`${inputCls} flex-1`}>
                {CATEGORIES.map((c) => <option key={c.value} value={c.value}>{c.label}</option>)}
              </select>
              <select value={form.priority} onChange={(e) => setForm({ ...form, priority: e.target.value })} className={`${inputCls} w-36`}>
                <option value="low">Low</option>
                <option value="normal">Normal</option>
                <option value="high">High</option>
                <option value="urgent">Urgent</option>
              </select>
            </div>
            <input required value={form.subject} onChange={(e) => setForm({ ...form, subject: e.target.value })} placeholder="Subject" className={inputCls} maxLength={200} />
            <textarea required value={form.body} onChange={(e) => setForm({ ...form, body: e.target.value })} rows={4} placeholder="Describe your concern…" className={inputCls} />
            <input type="file" multiple accept="image/png,image/jpeg,application/pdf" onChange={(e) => setFiles(e.target.files)} className="block w-full text-sm text-muted file:mr-3 file:rounded-full file:border file:border-line file:bg-paper file:px-4 file:py-1.5 file:text-sm file:text-ink" />
          </div>
          {error && <p className="mt-3 rounded-[10px] bg-warn/10 px-3 py-2 text-sm text-warn">{error}</p>}
          <button disabled={checking || submitting || !form.subject || !form.body} className="mt-3 rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white disabled:opacity-50">
            {checking ? "Checking…" : submitting ? "Sending…" : "Submit ticket"}
          </button>
        </form>
      )}

      {loading ? (
        <div className="mt-6 space-y-3">{Array.from({ length: 3 }).map((_, i) => <div key={i} className="shimmer h-16 rounded-[14px]" />)}</div>
      ) : tickets.length === 0 ? (
        <div className="mt-6"><EmptyState title="No tickets yet" body="Raise a concern above and our team will reach you fast — you can also reply right here or on WhatsApp." /></div>
      ) : (
        <div className="mt-6 divide-y divide-line rounded-[14px] border border-line bg-white">
          {tickets.map((t) => (
            <Link key={t.id} href={`/support/${t.id}`} className="flex flex-wrap items-center gap-3 px-5 py-4 transition-colors hover:bg-paper">
              <span className="mono text-xs text-muted">{t.reference}</span>
              <span className="min-w-[140px] flex-1 font-semibold text-ink">{t.subject}</span>
              {t.assignee && <span className="mono text-xs text-muted">{t.assignee.name}{t.assignee.role ? ` · ${t.assignee.role}` : ""}</span>}
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
