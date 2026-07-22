"use client";

import Link from "next/link";
import { use, useEffect, useMemo, useState } from "react";
import { ApiError, apiJson } from "@/lib/api";

/**
 * Student flashcard review (SM-2 spaced repetition). Works through the lesson's
 * due cards one at a time: flip to check the answer, then self-rate. The rating
 * reschedules the card on the server; the next due card surfaces. Motion is
 * transform/opacity only and disabled under prefers-reduced-motion.
 */

type Card = { id: number; front: string; back: string; due: boolean; due_at: string | null };
type Deck = { data: Card[]; meta: { due_count: number; total: number } };
type Rating = "again" | "hard" | "good" | "easy";

const RATINGS: { key: Rating; label: string; cls: string }[] = [
  { key: "again", label: "Again", cls: "border-warn/40 text-warn hover:bg-warn/10" },
  { key: "hard", label: "Hard", cls: "border-line text-ink hover:bg-paper" },
  { key: "good", label: "Good", cls: "border-trust/40 text-trust hover:bg-sky/50" },
  { key: "easy", label: "Easy", cls: "border-verify/40 text-verify hover:bg-verify-bg" },
];

export default function FlashcardReviewPage({ params }: { params: Promise<{ lesson: string }> }) {
  const { lesson } = use(params);
  const [deck, setDeck] = useState<Deck | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const [idx, setIdx] = useState(0);
  const [flipped, setFlipped] = useState(false);
  const [busy, setBusy] = useState(false);
  const [reviewed, setReviewed] = useState(0);

  useEffect(() => {
    apiJson<Deck>(`/api/v1/me/lessons/${lesson}/flashcards`)
      .then((r) => setDeck(r))
      .catch(() => setError("This deck could not be loaded."))
      .finally(() => setLoading(false));
  }, [lesson]);

  // The queue is the set of cards due at load, worked front-to-back.
  const queue = useMemo(() => deck?.data.filter((c) => c.due) ?? [], [deck]);
  const card = queue[idx] ?? null;

  async function rate(rating: Rating) {
    if (!card || busy) return;
    setBusy(true);
    setError(null);
    try {
      await apiJson(`/api/v1/me/flashcards/${card.id}/review`, { method: "POST", body: JSON.stringify({ rating }) });
      setReviewed((n) => n + 1);
      setFlipped(false);
      setIdx((i) => i + 1);
    } catch (err) {
      setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Could not save that review.");
    } finally {
      setBusy(false);
    }
  }

  if (loading) return <div className="mx-auto max-w-xl"><div className="shimmer h-80 rounded-[14px]" /></div>;

  if (error && !deck) {
    return (
      <div className="mx-auto max-w-xl">
        <h1 className="display text-2xl text-ink">Flashcards</h1>
        <p className="mt-3 text-sm text-muted">{error}</p>
      </div>
    );
  }

  const total = deck?.meta.total ?? 0;

  // Nothing to author, or everything reviewed for now.
  if (total === 0) {
    return (
      <div className="mx-auto max-w-xl">
        <p className="kicker text-trust">Recall</p>
        <h1 className="display mt-2 text-2xl text-ink">Flashcards</h1>
        <p className="mt-3 text-sm text-muted">No flashcards for this lesson yet — they&apos;ll appear here once your trainer publishes a set.</p>
        <Link href="/dashboard" className="mt-5 inline-block rounded-full bg-trust px-5 py-2.5 text-sm font-semibold text-white">Back to dashboard</Link>
      </div>
    );
  }

  if (!card) {
    return (
      <div className="mx-auto max-w-xl">
        <p className="kicker text-verify">All caught up</p>
        <h1 className="display mt-2 text-2xl text-ink">Nothing due right now</h1>
        <p className="mt-3 text-sm text-muted">
          {reviewed > 0
            ? `You reviewed ${reviewed} card${reviewed === 1 ? "" : "s"}. They'll resurface on their spaced-repetition schedule — come back when they're due.`
            : "You've reviewed everything due in this deck. Check back later when cards come up for review."}
        </p>
        <Link href="/dashboard" className="mt-5 inline-block rounded-full bg-trust px-5 py-2.5 text-sm font-semibold text-white">Back to dashboard</Link>
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-xl">
      <div className="flex items-center justify-between">
        <div>
          <p className="kicker text-trust">Recall</p>
          <h1 className="display mt-1 text-2xl text-ink">Flashcards</h1>
        </div>
        <span className="mono text-xs text-muted">{idx + 1} / {queue.length} due</span>
      </div>

      {/* The card — flips on click. transform/opacity only; reduced-motion disables the flip. */}
      <div className="mt-6 [perspective:1600px]">
        <button
          type="button"
          onClick={() => setFlipped((f) => !f)}
          className="relative block h-72 w-full text-left [transform-style:preserve-3d] motion-safe:transition-transform motion-safe:duration-[400ms] motion-safe:ease-[cubic-bezier(0.22,1,0.36,1)]"
          style={{ transform: flipped ? "rotateY(180deg)" : "rotateY(0deg)" }}
          aria-label={flipped ? "Show question" : "Show answer"}
        >
          {/* Front */}
          <span className="absolute inset-0 grid place-items-center rounded-[22px] border border-line bg-white p-8 text-center [backface-visibility:hidden] shadow-[0_10px_40px_rgba(10,18,32,.10)]">
            <span>
              <span className="mono block text-[11px] uppercase tracking-widest text-muted">Prompt</span>
              <span className="mt-3 block text-lg font-semibold text-ink">{card.front}</span>
              <span className="mt-6 block text-xs text-muted">Tap to reveal the answer</span>
            </span>
          </span>
          {/* Back */}
          <span className="absolute inset-0 grid place-items-center rounded-[22px] border border-trust/30 bg-sky/40 p-8 text-center [backface-visibility:hidden] [transform:rotateY(180deg)] shadow-[0_10px_40px_rgba(10,18,32,.10)]">
            <span>
              <span className="mono block text-[11px] uppercase tracking-widest text-trust">Answer</span>
              <span className="mt-3 block text-base text-ink">{card.back}</span>
            </span>
          </span>
        </button>
      </div>

      {error && <p className="mt-4 text-sm text-warn">{error}</p>}

      {/* Rating — only after the answer is shown, so students recall honestly first. */}
      {flipped ? (
        <div className="mt-6">
          <p className="text-center text-xs text-muted">How well did you recall it?</p>
          <div className="mt-3 grid grid-cols-4 gap-2">
            {RATINGS.map((r) => (
              <button key={r.key} onClick={() => rate(r.key)} disabled={busy}
                className={`rounded-full border px-2 py-2.5 text-sm font-semibold transition-colors disabled:opacity-50 ${r.cls}`}>
                {r.label}
              </button>
            ))}
          </div>
        </div>
      ) : (
        <p className="mt-6 text-center text-xs text-muted">Try to answer from memory, then tap the card.</p>
      )}
    </div>
  );
}
