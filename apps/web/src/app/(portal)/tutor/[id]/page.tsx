"use client";

import Link from "next/link";
import { use, useCallback, useEffect, useState } from "react";
import { ApiError, apiJson } from "@/lib/api";
import { type TutorConversation, type TutorMessage, isBudgetError } from "@/lib/tutor";

function Citations({ items }: { items: TutorMessage["citations"] }) {
  if (!items || items.length === 0) return null;
  return (
    <div className="mt-3 flex flex-wrap items-center gap-2 border-t border-line pt-2">
      <span className="mono text-[10px] uppercase tracking-widest text-muted">Sources</span>
      {items.map((c, i) =>
        c.href ? (
          <Link key={i} href={c.href} className="rounded-full bg-sky px-2.5 py-0.5 text-xs text-deep hover:underline">
            {c.label}
          </Link>
        ) : (
          <span key={i} className="rounded-full bg-paper px-2.5 py-0.5 text-xs text-muted">{c.label}</span>
        ),
      )}
    </div>
  );
}

export default function TutorThreadPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const [conversation, setConversation] = useState<TutorConversation | null>(null);
  const [loading, setLoading] = useState(true);
  const [question, setQuestion] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [budgetHit, setBudgetHit] = useState(false);

  const load = useCallback(() => {
    apiJson<{ data: TutorConversation }>(`/api/v1/me/tutor/${id}`)
      .then((r) => setConversation(r.data))
      .catch(() => setConversation(null))
      .finally(() => setLoading(false));
  }, [id]);

  useEffect(() => { load(); }, [load]);

  async function ask() {
    if (!question.trim()) return;
    setError(null);
    setBusy(true);
    try {
      const r = await apiJson<{ data: TutorConversation }>("/api/v1/me/tutor", {
        method: "POST",
        body: JSON.stringify({ question, conversation_id: Number(id) }),
      });
      setConversation(r.data);
      setQuestion("");
    } catch (err) {
      if (isBudgetError(err)) setBudgetHit(true);
      else setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Something went wrong.");
    } finally {
      setBusy(false);
    }
  }

  if (loading) return <div className="mx-auto max-w-2xl"><div className="shimmer h-64 rounded-[14px]" /></div>;
  if (!conversation) return <div className="mx-auto max-w-2xl text-sm text-muted">Conversation not found.</div>;

  return (
    <div className="mx-auto max-w-2xl">
      <Link href="/tutor" className="text-sm text-trust hover:underline">← AI Tutor</Link>
      <h1 className="display mt-3 text-2xl text-ink">{conversation.title ?? "Conversation"}</h1>

      <div className="mt-6 space-y-3">
        {(conversation.messages ?? []).map((m) => {
          const mine = m.role === "student";
          return (
            <div key={m.id} className={`rounded-[14px] border border-line p-4 ${mine ? "bg-sky/40" : "bg-white"}`}>
              <span className="mono text-[10px] uppercase tracking-widest text-muted">{mine ? "You" : "Tutor"}</span>
              <p className="mt-2 whitespace-pre-wrap text-sm text-ink">{m.body}</p>
              {!mine && <Citations items={m.citations} />}
              {m.escalated && (
                <p className="mt-3 rounded-[10px] bg-verify-bg px-3 py-2 text-xs text-verify">
                  Raised to your trainer — they&apos;ll follow up.
                  {m.ticket_id && (
                    <Link href={`/support/${m.ticket_id}`} className="ml-1 font-semibold underline">View ticket</Link>
                  )}
                </p>
              )}
            </div>
          );
        })}
      </div>

      {budgetHit ? (
        <div className="mt-6 rounded-2xl border border-line bg-white p-6 text-center">
          <p className="text-sm text-ink">You&apos;ve reached today&apos;s AI tutor limit.</p>
          <p className="mt-1 text-sm text-muted">It resets tomorrow. For something urgent, raise it with your trainer via Support.</p>
        </div>
      ) : (
        <div className="mt-6">
          {error && <p className="mb-2 text-sm text-warn">{error}</p>}
          <textarea
            value={question}
            onChange={(e) => setQuestion(e.target.value)}
            rows={3}
            placeholder="Ask a follow-up…"
            className="w-full resize-none rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust"
          />
          <button
            onClick={ask}
            disabled={busy || !question.trim()}
            className="mt-2 rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white disabled:opacity-50"
          >
            {busy ? "Thinking…" : "Send"}
          </button>
        </div>
      )}
    </div>
  );
}
