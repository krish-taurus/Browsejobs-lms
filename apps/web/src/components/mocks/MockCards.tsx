"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import { ApiError, apiJson } from "@/lib/api";
import { mockPath, type MockKind } from "@/lib/mockKinds";

export type GapItem = {
  topic: string;
  real_weight_pct: number;
  your_score: number | null;
  gap: boolean;
};

export type VoiceTopup = {
  product_id: number;
  sku: string;
  name: string;
  price_paise: number;
  sessions: number;
};

export type MockSummary = {
  enabled: boolean;
  in_progress_id: number | null;
  best_score: number;
  human_mock_unlocked: boolean;
  module_mocks: { module: string | null; required: number; completed: number; remaining: number; cleared: boolean }[];
  blueprints: { id: number; skill: string | null; role_title: string }[];
  gap_report: { role_title: string | null; items: GapItem[] };
  voice: {
    credits: number;
    max_minutes: number;
    in_progress: { id: number; join_url: string | null } | null;
    topups: VoiceTopup[];
    provider_ready: boolean;
    room_attempts_used: number;
    room_attempts_limit: number;
  };
  kind_counts?: Partial<Record<MockKind, number>>;
};

/** The /me/mocks summary shared by the hub and the Voice page. */
export function useMockSummary() {
  const [summary, setSummary] = useState<MockSummary | null>(null);
  const [loading, setLoading] = useState(true);

  const reload = useCallback(() => {
    apiJson<{ data: MockSummary }>("/api/v1/me/mocks")
      .then((r) => setSummary(r.data))
      .catch(() => setSummary(null))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => { reload(); }, [reload]);

  return { summary, loading, reload };
}

/**
 * Where a student stands on voice interviews, from the server's own numbers.
 * "browser" = the in-browser room (capped attempts, no credits); "call" = a
 * telephony provider is configured and each session spends one credit.
 */
export function voiceStatus(summary: MockSummary) {
  const v = summary.voice;
  const mode: "browser" | "call" = v.provider_ready ? "call" : "browser";
  const remaining = mode === "call" ? v.credits : Math.max(0, v.room_attempts_limit - v.room_attempts_used);

  return {
    mode,
    used: v.room_attempts_used,
    limit: v.room_attempts_limit,
    credits: v.credits,
    remaining,
    exhausted: remaining <= 0,
    joinUrl: v.in_progress?.join_url ?? null,
    maxMinutes: v.max_minutes,
    topups: v.topups,
  };
}

export type VoiceStatus = ReturnType<typeof voiceStatus>;

/**
 * Start (or resume) a voice interview. The server resumes an unfinished
 * voice session instead of creating a second one; the ref guard also stops a
 * double click from firing two requests before the first one answers.
 */
export function useVoiceInterview(summary: MockSummary | null, reload: () => void) {
  const [busy, setBusy] = useState<number | "start" | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const inFlight = useRef(false);

  const start = useCallback(async () => {
    if (!summary || inFlight.current) return;
    inFlight.current = true;
    setError(null);
    setBusy("start");
    try {
      if (summary.voice.provider_ready) {
        const r = await apiJson<{ data: { join_url: string | null } }>("/api/v1/me/mocks/voice", { method: "POST" });
        if (r.data.join_url) window.open(r.data.join_url, "_blank", "noopener");
        reload();
        setBusy(null);
        inFlight.current = false;
      } else {
        // No telephony provider: the spoken interview runs in the browser —
        // same interviewer and scorecard, capped at a flat number of attempts
        // (is_room is what the server counts against that cap).
        const r = await apiJson<{ data: { id: number } }>("/api/v1/me/mocks", {
          method: "POST",
          body: JSON.stringify({ is_room: true }),
        });
        window.location.href = mockPath("voice", r.data.id, true);
      }
    } catch (err) {
      setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Something went wrong.");
      setBusy(null);
      inFlight.current = false;
    }
  }, [summary, reload]);

  const buyTopup = useCallback(async (t: VoiceTopup) => {
    setError(null);
    setBusy(t.product_id);
    try {
      await apiJson("/api/v1/me/purchases", { method: "POST", body: JSON.stringify({ product_id: t.product_id }) });
      setNotice("Order created — complete the payment from the Store page and your sessions land instantly.");
    } catch (err) {
      setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Could not start the purchase.");
    } finally {
      setBusy(null);
    }
  }, []);

  return { busy, error, notice, start, buyTopup };
}

export function VoiceInterviewCard({ summary, reload }: { summary: MockSummary; reload: () => void }) {
  const { busy, error, notice, start, buyTopup } = useVoiceInterview(summary, reload);

  return (
    <div className="mt-4 rounded-2xl border border-line bg-white p-6">
      <div className="flex items-baseline justify-between">
        <p className="text-xs font-semibold uppercase tracking-widest text-muted">
          Voice interview · up to {summary.voice.max_minutes} min
        </p>
        <span className="mono text-xs text-muted">
          {summary.voice.provider_ready
            ? `${summary.voice.credits} session${summary.voice.credits === 1 ? "" : "s"} left`
            : `${summary.voice.room_attempts_used}/${summary.voice.room_attempts_limit} attempts used`}
        </span>
      </div>
      {error && <p className="mt-2 text-sm text-warn">{error}</p>}
      {notice && <p className="mt-2 text-sm text-verify">{notice}</p>}

      {summary.voice.in_progress?.join_url ? (
        <>
          <p className="mt-2 text-sm text-ink">Your voice interview room is open.</p>
          <a
            href={summary.voice.in_progress.join_url}
            target="_blank"
            rel="noopener noreferrer"
            className="mt-3 inline-block rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white"
          >
            Rejoin the call →
          </a>
        </>
      ) : !summary.voice.provider_ready ? (
        summary.voice.room_attempts_used >= summary.voice.room_attempts_limit ? (
          <p className="mt-2 text-sm text-ink">
            You&apos;ve used all {summary.voice.room_attempts_limit} attempts for this interview.
          </p>
        ) : (
          <>
            <p className="mt-2 text-sm text-ink">
              A spoken interview with the AI interviewer — questions are read aloud and your answers
              are transcribed as you speak. You get the same scorecard afterwards.
            </p>
            <button
              onClick={start}
              disabled={busy === "start"}
              className="mt-3 rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white disabled:opacity-50"
            >
              {busy === "start" ? "Opening the room…" : "Start a voice interview"}
            </button>
            <p className="mt-2 text-xs text-muted">
              Runs in your browser — no credit is used. Works best in Chrome or Edge, with your
              microphone and camera allowed.
            </p>
          </>
        )
      ) : summary.voice.credits > 0 ? (
        <>
          <p className="mt-2 text-sm text-ink">
            A spoken interview with the AI interviewer — the closest thing to the real room. You get
            the same scorecard afterwards.
          </p>
          <button
            onClick={start}
            disabled={busy === "start"}
            className="mt-3 rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white disabled:opacity-50"
          >
            {busy === "start" ? "Opening the room…" : "Start a voice interview"}
          </button>
          <p className="mt-2 text-xs text-muted">
            Each session uses 1 credit. Finish a module to unlock another — dropped calls are refunded.
          </p>
        </>
      ) : (
        <>
          <p className="mt-2 text-sm text-ink">You&apos;re out of voice sessions. Finish a module to unlock one, or top up:</p>
          <div className="mt-3 flex flex-wrap gap-2">
            {summary.voice.topups.map((t) => (
              <button
                key={t.product_id}
                onClick={() => buyTopup(t)}
                disabled={busy === t.product_id}
                className="rounded-full border border-line bg-white px-4 py-2 text-sm font-semibold text-ink hover:border-trust disabled:opacity-50"
              >
                {busy === t.product_id
                  ? "Creating order…"
                  : `${t.sessions} session${t.sessions === 1 ? "" : "s"} · ₹${Math.round(t.price_paise / 100)}`}
              </button>
            ))}
          </div>
        </>
      )}
    </div>
  );
}
