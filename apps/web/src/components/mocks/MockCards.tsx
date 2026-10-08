"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useCallback, useEffect, useState } from "react";
import { ApiError, apiJson } from "@/lib/api";
import { mockPath, type MockKind } from "@/lib/mockKinds";

export type GapItem = {
  topic: string;
  real_weight_pct: number;
  your_score: number | null;
  gap: boolean;
};

type VoiceTopup = {
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

/** The /me/mocks summary shared by the hub and the Practice / Voice pages. */
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

export function TextPracticeCard({ summary }: { summary: MockSummary }) {
  const router = useRouter();
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [chosen, setChosen] = useState<string>("");

  // A dispatched mock links here as ?start=<blueprintId> — preselect it.
  useEffect(() => {
    const start = new URLSearchParams(window.location.search).get("start");
    if (start) setChosen(start);
  }, []);

  async function start() {
    setError(null);
    setBusy(true);
    try {
      const body = chosen ? JSON.stringify({ blueprint_id: Number(chosen) }) : undefined;
      const r = await apiJson<{ data: { id: number } }>("/api/v1/me/mocks", { method: "POST", body });
      router.push(mockPath("practice", r.data.id));
    } catch (err) {
      setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Something went wrong.");
      setBusy(false);
    }
  }

  if (!summary.enabled) {
    return (
      <div className="mt-6 rounded-2xl border border-line bg-white p-6">
        <p className="text-sm text-muted">Text practice isn&apos;t switched on for your batch yet — voice interviews are ready when you are.</p>
      </div>
    );
  }

  return (
    <div className="mt-6 rounded-2xl border border-line bg-white p-6">
      <p className="text-xs font-semibold uppercase tracking-widest text-muted">Text practice · free</p>
      {summary.in_progress_id ? (
        <>
          <p className="mt-2 text-sm text-ink">You have a practice interview in progress.</p>
          <Link
            href={mockPath("practice", summary.in_progress_id)}
            className="mt-3 inline-block rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white"
          >
            Resume interview →
          </Link>
        </>
      ) : (
        <>
          <p className="mt-2 text-sm text-ink">Ready when you are — it takes about 10 minutes.</p>
          {summary.blueprints.length > 1 && (
            <select
              value={chosen}
              onChange={(e) => setChosen(e.target.value)}
              className="mt-3 block rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust"
            >
              <option value="">Pick a skill…</option>
              {summary.blueprints.map((b) => (
                <option key={b.id} value={b.id}>{b.skill ?? b.role_title}</option>
              ))}
            </select>
          )}
          {error && <p className="mt-2 text-sm text-warn">{error}</p>}
          <button
            onClick={start}
            disabled={busy || (summary.blueprints.length > 1 && !chosen)}
            className="mt-3 rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white disabled:opacity-50"
          >
            {busy ? "Setting up…" : "Start a mock interview"}
          </button>
        </>
      )}
    </div>
  );
}

export function VoiceInterviewCard({ summary, reload }: { summary: MockSummary; reload: () => void }) {
  const [voiceBusy, setVoiceBusy] = useState<number | "start" | null>(null);
  const [voiceError, setVoiceError] = useState<string | null>(null);
  const [voiceNotice, setVoiceNotice] = useState<string | null>(null);

  async function startVoice() {
    setVoiceError(null);
    setVoiceBusy("start");
    try {
      const r = await apiJson<{ data: { join_url: string | null } }>("/api/v1/me/mocks/voice", { method: "POST" });
      if (r.data.join_url) window.open(r.data.join_url, "_blank", "noopener");
      reload();
    } catch (err) {
      setVoiceError(err instanceof ApiError ? (err.firstError ?? err.message) : "Something went wrong.");
    } finally {
      setVoiceBusy(null);
    }
  }

  // No telephony provider configured: run the spoken interview in the browser
  // instead — same interviewer and scorecard, using the browser's own speech
  // engine. Free of the voice_mock credit wallet entirely, but capped at a
  // flat number of attempts per blueprint (CRM-editable, separate from the
  // employer-JD interview cap) — is_room is what tells the backend to count
  // and gate this one against that cap, rather than treating it as
  // unmetered text practice.
  async function startBrowserVoice() {
    setVoiceError(null);
    setVoiceBusy("start");
    try {
      const r = await apiJson<{ data: { id: number } }>("/api/v1/me/mocks", {
        method: "POST",
        body: JSON.stringify({ is_room: true }),
      });
      window.location.href = mockPath("voice", r.data.id, true);
    } catch (err) {
      setVoiceError(err instanceof ApiError ? (err.firstError ?? err.message) : "Something went wrong.");
      setVoiceBusy(null);
    }
  }

  async function buyTopup(t: VoiceTopup) {
    setVoiceError(null);
    setVoiceBusy(t.product_id);
    try {
      await apiJson("/api/v1/me/purchases", {
        method: "POST",
        body: JSON.stringify({ product_id: t.product_id }),
      });
      setVoiceNotice("Order created — complete the payment from the Store page and your sessions land instantly.");
    } catch (err) {
      setVoiceError(err instanceof ApiError ? (err.firstError ?? err.message) : "Could not start the purchase.");
    } finally {
      setVoiceBusy(null);
    }
  }

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
      {voiceError && <p className="mt-2 text-sm text-warn">{voiceError}</p>}
      {voiceNotice && <p className="mt-2 text-sm text-verify">{voiceNotice}</p>}

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
              onClick={startBrowserVoice}
              disabled={voiceBusy === "start"}
              className="mt-3 rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white disabled:opacity-50"
            >
              {voiceBusy === "start" ? "Opening the room…" : "Start a voice interview"}
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
            onClick={startVoice}
            disabled={voiceBusy === "start"}
            className="mt-3 rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white disabled:opacity-50"
          >
            {voiceBusy === "start" ? "Opening the room…" : "Start a voice interview"}
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
                disabled={voiceBusy === t.product_id}
                className="rounded-full border border-line bg-white px-4 py-2 text-sm font-semibold text-ink hover:border-trust disabled:opacity-50"
              >
                {voiceBusy === t.product_id
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
