"use client";

import Link from "next/link";
import { use, useEffect, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ApiError, apiJson } from "@/lib/api";

/**
 * Flashcard authoring (admins with manage-curriculum). Draft a set with AI from
 * the lesson's notes/topics, edit each card by hand, then save — the save is the
 * human-in-the-loop gate that publishes the deck to students (SM-2 spaced
 * repetition schedules them from there).
 */

type Card = { front: string; back: string };
type SavedCard = { id: number; front: string; back: string; source: string };

const inputCls = "w-full rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust";

export default function FlashcardsEditor({ params }: { params: Promise<{ lesson: string }> }) {
  const { lesson } = use(params);
  const qc = useQueryClient();
  const [error, setError] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);
  const [cards, setCards] = useState<Card[]>([]);

  const { data } = useQuery({
    queryKey: ["admin", "flashcards", lesson],
    queryFn: () => apiJson<{ data: SavedCard[] }>(`/api/v1/admin/lessons/${lesson}/flashcards`),
  });

  useEffect(() => {
    if (data?.data) setCards(data.data.map((c) => ({ front: c.front, back: c.back })));
  }, [data]);

  const onError = (err: unknown) => setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Request failed.");

  const generate = useMutation({
    mutationFn: () => apiJson<{ data: Card[] }>(`/api/v1/admin/lessons/${lesson}/flashcards/generate`, { method: "POST", body: JSON.stringify({ count: 10 }) }),
    onSuccess: (res) => { setError(null); setSaved(false); setCards((prev) => [...prev, ...res.data]); },
    onError,
  });

  const save = useMutation({
    mutationFn: () => apiJson<{ data: SavedCard[] }>(`/api/v1/admin/lessons/${lesson}/flashcards`, {
      method: "PUT",
      body: JSON.stringify({ cards: cards.filter((c) => c.front.trim() && c.back.trim()) }),
    }),
    onSuccess: () => { setError(null); setSaved(true); void qc.invalidateQueries({ queryKey: ["admin", "flashcards", lesson] }); },
    onError,
  });

  const update = (i: number, patch: Partial<Card>) => {
    setSaved(false);
    setCards((prev) => prev.map((c, idx) => (idx === i ? { ...c, ...patch } : c)));
  };
  const remove = (i: number) => { setSaved(false); setCards((prev) => prev.filter((_, idx) => idx !== i)); };
  const add = () => { setSaved(false); setCards((prev) => [...prev, { front: "", back: "" }]); };

  const usable = cards.filter((c) => c.front.trim() && c.back.trim()).length;

  return (
    <div className="mx-auto max-w-2xl">
      <Link href="/admin/curriculum" className="text-sm text-trust hover:underline">← Curriculum</Link>
      <h1 className="display mt-3 text-2xl text-ink">Flashcards</h1>
      <p className="mt-1 text-sm text-ink2/70">
        Short recall prompts students review on a spaced-repetition schedule. Draft with AI from the lesson notes, edit, then save to publish.
      </p>

      {error && <p className="mt-4 rounded-[10px] bg-warn/10 px-3 py-2 text-sm text-warn">{error}</p>}

      <div className="mt-4 flex flex-wrap items-center gap-3">
        <button onClick={() => generate.mutate()} disabled={generate.isPending}
          className="rounded-full border border-line px-4 py-2 text-sm font-semibold text-ink transition-colors hover:bg-paper disabled:opacity-50">
          {generate.isPending ? "Drafting…" : "✨ Generate with AI"}
        </button>
        <button onClick={add}
          className="rounded-full border border-line px-4 py-2 text-sm font-semibold text-ink transition-colors hover:bg-paper">
          + Add card
        </button>
        <span className="mono text-xs text-muted">{usable} card{usable === 1 ? "" : "s"}</span>
      </div>

      <div className="mt-4 space-y-3">
        {cards.length === 0 ? (
          <p className="rounded-2xl border border-dashed border-line bg-white px-4 py-10 text-center text-sm text-muted">
            No cards yet. Generate a set with AI or add one by hand.
          </p>
        ) : (
          cards.map((card, i) => (
            <div key={i} className="rounded-2xl border border-line bg-white p-4">
              <div className="flex items-center justify-between">
                <span className="mono text-[11px] uppercase tracking-widest text-muted">Card {i + 1}</span>
                <button onClick={() => remove(i)} className="text-xs font-semibold text-warn hover:underline">Remove</button>
              </div>
              <div className="mt-2 grid gap-2 sm:grid-cols-2">
                <div>
                  <label className="text-xs font-semibold text-muted">Front (prompt)</label>
                  <textarea className={`${inputCls} mt-1`} rows={3} value={card.front}
                    onChange={(e) => update(i, { front: e.target.value })} placeholder="What is a base case?" />
                </div>
                <div>
                  <label className="text-xs font-semibold text-muted">Back (answer)</label>
                  <textarea className={`${inputCls} mt-1`} rows={3} value={card.back}
                    onChange={(e) => update(i, { back: e.target.value })} placeholder="The condition that stops the recursion." />
                </div>
              </div>
            </div>
          ))
        )}
      </div>

      <div className="mt-6 flex items-center gap-3">
        <button onClick={() => save.mutate()} disabled={save.isPending}
          className="rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white transition-colors hover:bg-deep disabled:opacity-50">
          {save.isPending ? "Saving…" : "Save & publish"}
        </button>
        {saved && <span className="mono text-xs text-verify">Saved ✓</span>}
        <span className="text-xs text-muted">Saving replaces the whole deck. Blank cards are dropped.</span>
      </div>
    </div>
  );
}
