"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import { ApiError, apiJson } from "@/lib/api";
import { type TutorConversation, isBudgetError } from "@/lib/tutor";

export default function TutorPage() {
  const router = useRouter();
  const [conversations, setConversations] = useState<TutorConversation[]>([]);
  const [loading, setLoading] = useState(true);
  const [question, setQuestion] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    apiJson<{ data: TutorConversation[] }>("/api/v1/me/tutor")
      .then((r) => setConversations(r.data))
      .catch(() => setConversations([]))
      .finally(() => setLoading(false));
  }, []);

  async function ask() {
    if (!question.trim()) return;
    setError(null);
    setBusy(true);
    try {
      const r = await apiJson<{ data: TutorConversation }>("/api/v1/me/tutor", {
        method: "POST",
        body: JSON.stringify({ question }),
      });
      router.push(`/tutor/${r.data.id}`);
    } catch (err) {
      if (isBudgetError(err)) {
        setError("You've reached today's AI tutor limit. It resets tomorrow.");
      } else {
        setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Something went wrong.");
      }
      setBusy(false);
    }
  }

  return (
    <div className="mx-auto max-w-2xl">
      <p className="kicker text-trust">AI Tutor</p>
      <h1 className="display mt-2 text-3xl text-ink">Ask your tutor</h1>
      <p className="mt-1 text-sm text-muted">
        Stuck on a concept or a lab? Ask in plain words — answers are grounded in your
        course material and cite where they came from.
      </p>

      {/* Composer — the primary action */}
      <div className="mt-6 rounded-2xl border border-line bg-white p-5">
        <textarea
          value={question}
          onChange={(e) => setQuestion(e.target.value)}
          rows={3}
          placeholder="e.g. How do for-loops work in Python?"
          className="w-full resize-none rounded-[10px] border border-line bg-paper px-3 py-2 text-sm text-ink outline-none focus:border-trust"
        />
        {error && <p className="mt-2 text-sm text-warn">{error}</p>}
        <button
          onClick={ask}
          disabled={busy || !question.trim()}
          className="mt-3 rounded-full bg-trust px-5 py-2.5 text-sm font-semibold text-white transition-transform hover:-translate-y-0.5 disabled:opacity-50"
        >
          {busy ? "Thinking…" : "Ask the tutor"}
        </button>
      </div>

      {/* History */}
      <h2 className="display mt-10 text-lg text-ink">Recent conversations</h2>
      {loading ? (
        <div className="mt-4 space-y-2">{Array.from({ length: 3 }).map((_, i) => <div key={i} className="shimmer h-14 rounded-[14px]" />)}</div>
      ) : conversations.length === 0 ? (
        <p className="mt-4 text-sm text-muted">No questions yet — your conversations will appear here.</p>
      ) : (
        <div className="mt-4 divide-y divide-line rounded-[14px] border border-line bg-white">
          {conversations.map((c) => (
            <Link key={c.id} href={`/tutor/${c.id}`} className="flex items-center justify-between gap-3 px-5 py-3 hover:bg-paper">
              <span className="truncate text-sm text-ink">{c.title ?? "Conversation"}</span>
              <span className="mono text-xs text-muted">{c.message_count ?? 0} msgs</span>
            </Link>
          ))}
        </div>
      )}
    </div>
  );
}
