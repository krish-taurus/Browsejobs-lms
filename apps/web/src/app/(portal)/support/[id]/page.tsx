"use client";

import Link from "next/link";
import { use, useCallback, useEffect, useState } from "react";
import { ApiError, apiJson } from "@/lib/api";

type Message = {
  id: number;
  author_type: string;
  author_name?: string | null;
  body: string;
  channel: string;
  created_at: string | null;
  attachments?: { id: number; name: string; url: string | null }[];
};

type Ticket = {
  id: number;
  reference: string;
  subject: string;
  status: string;
  reopenable: boolean;
  csat_rating: number | null;
  assignee: { name: string; role?: string | null } | null;
  messages: Message[];
};

export default function TicketThreadPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const [ticket, setTicket] = useState<Ticket | null>(null);
  const [loading, setLoading] = useState(true);
  const [reply, setReply] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const load = useCallback(() => {
    apiJson<{ data: Ticket }>(`/api/v1/me/tickets/${id}`)
      .then((r) => setTicket(r.data))
      .catch(() => setTicket(null))
      .finally(() => setLoading(false));
  }, [id]);

  useEffect(() => { load(); }, [load]);

  async function act(fn: () => Promise<unknown>) {
    setError(null);
    setBusy(true);
    try { await fn(); load(); }
    catch (err) { setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Something went wrong."); }
    finally { setBusy(false); }
  }

  const sendReply = () =>
    act(async () => {
      await apiJson(`/api/v1/me/tickets/${id}/reply`, { method: "POST", body: JSON.stringify({ body: reply }) });
      setReply("");
    });

  const reopen = () => act(() => apiJson(`/api/v1/me/tickets/${id}/reopen`, { method: "POST", body: JSON.stringify({}) }));
  const rate = (rating: number) => act(() => apiJson(`/api/v1/me/tickets/${id}/csat`, { method: "POST", body: JSON.stringify({ rating }) }));

  if (loading) return <div className="mx-auto max-w-2xl"><div className="shimmer h-64 rounded-[14px]" /></div>;
  if (!ticket) return <div className="mx-auto max-w-2xl text-sm text-muted">Ticket not found.</div>;

  const resolved = ticket.status === "resolved" || ticket.status === "closed";

  return (
    <div className="mx-auto max-w-2xl">
      <Link href="/support" className="text-sm text-trust hover:underline">← My Tickets</Link>
      <div className="mt-4 flex flex-wrap items-center justify-between gap-2">
        <div>
          <p className="mono text-xs text-muted">{ticket.reference}</p>
          <h1 className="display mt-1 text-2xl text-ink">{ticket.subject}</h1>
        </div>
        {ticket.assignee && (
          <p className="text-sm text-muted">Assigned to <span className="font-semibold text-ink">{ticket.assignee.name}</span>{ticket.assignee.role ? `, ${ticket.assignee.role}` : ""}</p>
        )}
      </div>

      {error && <p className="mt-4 rounded-[10px] bg-warn/10 px-3 py-2 text-sm text-warn">{error}</p>}

      <div className="mt-6 space-y-3">
        {ticket.messages.map((m) => {
          const mine = m.author_type === "student";
          return (
            <div key={m.id} className={`rounded-[14px] border border-line p-4 ${mine ? "bg-sky/40" : "bg-white"}`}>
              <div className="flex items-center justify-between">
                <span className="text-xs font-semibold text-ink">{mine ? "You" : (m.author_name ?? "Support")}</span>
                <span className="mono text-[10px] uppercase tracking-widest text-muted">{m.channel}</span>
              </div>
              <p className="mt-2 whitespace-pre-wrap text-sm text-ink">{m.body}</p>
              {m.attachments && m.attachments.length > 0 && (
                <div className="mt-2 flex flex-wrap gap-2">
                  {m.attachments.map((a) => a.url && (
                    <a key={a.id} href={a.url} target="_blank" rel="noreferrer" className="mono text-xs text-trust hover:underline">📎 {a.name}</a>
                  ))}
                </div>
              )}
            </div>
          );
        })}
      </div>

      {resolved ? (
        <div className="mt-6 rounded-[14px] border border-line bg-white p-5 text-center">
          {ticket.csat_rating ? (
            <p className="text-sm text-muted">You rated this <span className="text-amber">{"★".repeat(ticket.csat_rating)}</span>. Thanks!</p>
          ) : (
            <>
              <p className="text-sm text-ink">How did we do?</p>
              <div className="mt-2 flex justify-center gap-1">
                {[1, 2, 3, 4, 5].map((n) => (
                  <button key={n} onClick={() => rate(n)} disabled={busy} className="text-2xl text-amber hover:scale-110">★</button>
                ))}
              </div>
            </>
          )}
          {ticket.reopenable && (
            <button onClick={reopen} disabled={busy} className="mt-4 rounded-full border border-line px-4 py-1.5 text-sm text-ink hover:bg-paper">
              Reopen ticket
            </button>
          )}
        </div>
      ) : (
        <div className="mt-6">
          <textarea value={reply} onChange={(e) => setReply(e.target.value)} rows={3} placeholder="Write a reply…" className="w-full rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust" />
          <button onClick={sendReply} disabled={busy || !reply.trim()} className="mt-2 rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white disabled:opacity-50">
            {busy ? "Sending…" : "Send reply"}
          </button>
        </div>
      )}
    </div>
  );
}
