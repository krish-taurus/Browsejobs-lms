"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useCallback, useEffect, useState } from "react";
import { ApiError, apiJson } from "@/lib/api";

type GapItem = {
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

type MockSummary = {
  enabled: boolean;
  in_progress_id: number | null;
  best_score: number;
  human_mock_unlocked: boolean;
  module_mocks: { module: string | null; required: number; completed: number; remaining: number; cleared: boolean }[];
  blueprints: { id: number; skill: string | null; role_title: string }[];
  gap_report: { role_title: string | null; items: GapItem[] };
  voice: {
    credits: number;
    provider_ready: boolean;
    max_minutes: number;
    in_progress: { id: number; join_url: string | null } | null;
    topups: VoiceTopup[];
  };
  mocks: { id: number; overall_score: number | null; completed_at: string | null }[];
};

function scoreTone(score: number | null): string {
  if (score === null) return "text-muted";
  if (score >= 70) return "text-verify";
  if (score >= 40) return "text-ink";
  return "text-warn";
}

export default function MockHubPage() {
  const router = useRouter();
  const [summary, setSummary] = useState<MockSummary | null>(null);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [voiceBusy, setVoiceBusy] = useState<number | "start" | null>(null);
  const [voiceError, setVoiceError] = useState<string | null>(null);
  const [voiceNotice, setVoiceNotice] = useState<string | null>(null);
  const [chosen, setChosen] = useState<string>("");

  // A dispatched mock links here as /mock?start=<blueprintId> — preselect it.
  useEffect(() => {
    const start = new URLSearchParams(window.location.search).get("start");
    if (start) setChosen(start);
  }, []);

  const load = useCallback(() => {
    apiJson<{ data: MockSummary }>("/api/v1/me/mocks")
      .then((r) => setSummary(r.data))
      .catch(() => setSummary(null))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => { load(); }, [load]);

  async function start(destination: "session" | "room" = "session") {
    setError(null);
    setVoiceError(null);
    setBusy(true);
    try {
      const payload: Record<string, unknown> = {};
      if (chosen) payload.blueprint_id = Number(chosen);
      if (destination === "room") payload.room = true;
      const r = await apiJson<{ data: { id: number } }>("/api/v1/me/mocks", {
        method: "POST",
        body: Object.keys(payload).length > 0 ? JSON.stringify(payload) : undefined,
      });
      router.push(destination === "room" ? `/mock/${r.data.id}/room` : `/mock/${r.data.id}`);
    } catch (err) {
      const message = err instanceof ApiError ? (err.firstError ?? err.message) : "Something went wrong.";
      if (destination === "room") {
        setVoiceError(err instanceof ApiError && err.status === 402 ? "You're out of sessions — top up below." : message);
        load(); // refresh credits so the top-up buttons appear
      } else {
        setError(message);
      }
      setBusy(false);
    }
  }

  async function startVoice() {
    setVoiceError(null);
    setVoiceBusy("start");
    try {
      const r = await apiJson<{ data: { join_url: string | null } }>("/api/v1/me/mocks/voice", { method: "POST" });
      if (r.data.join_url) window.open(r.data.join_url, "_blank", "noopener");
      load();
    } catch (err) {
      setVoiceError(err instanceof ApiError ? (err.firstError ?? err.message) : "Something went wrong.");
    } finally {
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

  if (loading) return <div className="mx-auto max-w-2xl"><div className="shimmer h-64 rounded-[14px]" /></div>;

  if (!summary) {
    return (
      <div className="mx-auto max-w-2xl">
        <h1 className="display text-2xl text-ink">Mock Interviews</h1>
        <div className="mt-6 rounded-2xl border border-line bg-white p-8 text-center">
          <p className="text-sm text-ink">Practice interviews aren&apos;t available right now.</p>
          <p className="mt-1 text-sm text-muted">Ask your counselor — or check back soon.</p>
        </div>
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-2xl">
      <h1 className="display text-2xl text-ink">Mock Interviews</h1>
      <p className="mt-1 text-sm text-muted">
        A realistic text interview for your target role. You get a scorecard with model answers and
        the three things to fix next.
      </p>

      {summary.human_mock_unlocked && (
        <div className="mt-6 rounded-2xl border border-verify/30 bg-verify-bg p-5">
          <p className="text-sm font-semibold text-verify">Human mock unlocked 🎉</p>
          <p className="mt-1 text-sm text-ink">
            Your best score is {summary.best_score} — you&apos;re ready for a live mock with a mentor.
            Your counselor will reach out to schedule it.
          </p>
        </div>
      )}

      {summary.module_mocks.length > 0 && (
        <div className="mt-6 rounded-2xl border border-line bg-white p-5">
          <p className="text-xs font-semibold uppercase tracking-widest text-muted">Module mocks</p>
          <p className="mt-1 text-sm text-muted">
            Each module you finish unlocks a set of mocks to clear. Any mock you complete counts toward
            the next one due.
          </p>
          <ul className="mt-3 space-y-2.5">
            {summary.module_mocks.map((m, i) => (
              <li key={i} className="flex items-center justify-between gap-3">
                <span className="min-w-0 flex-1 truncate text-sm text-ink">{m.module ?? "Module"}</span>
                <span className="flex items-center gap-2.5">
                  <span className="h-1.5 w-24 overflow-hidden rounded-full bg-line">
                    <span
                      className={`block h-full rounded-full ${m.cleared ? "bg-verify" : "bg-trust"}`}
                      style={{ width: `${Math.round((m.completed / Math.max(1, m.required)) * 100)}%` }}
                    />
                  </span>
                  {m.cleared ? (
                    <span className="mono text-[11px] font-semibold uppercase tracking-widest text-verify">Cleared ✓</span>
                  ) : (
                    <span className="mono text-xs text-muted">{m.completed}/{m.required}</span>
                  )}
                </span>
              </li>
            ))}
          </ul>
        </div>
      )}

      {summary.enabled ? (
        <div className="mt-6 rounded-2xl border border-line bg-white p-6">
          <p className="text-xs font-semibold uppercase tracking-widest text-muted">Text practice · free</p>
          {summary.in_progress_id ? (
            <>
              <p className="mt-2 text-sm text-ink">You have an interview in progress.</p>
              <Link
                href={`/mock/${summary.in_progress_id}`}
                className="mt-3 inline-block rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white"
              >
                Resume interview →
              </Link>
            </>
          ) : (
            <>
              <p className="mt-2 text-sm text-ink">Ready when you are — it takes about 10 minutes.</p>
              <p className="mt-1 text-xs text-muted">
                Prefer the real thing? Inside the interview, tap 📹 Join interview room — a video-call
                style room where the interviewer speaks and you answer out loud, camera on.
              </p>
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
                onClick={() => start()}
                disabled={busy || (summary.blueprints.length > 1 && !chosen)}
                className="mt-3 rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white disabled:opacity-50"
              >
                {busy ? "Setting up…" : "Start a mock interview"}
              </button>
            </>
          )}
        </div>
      ) : (
        <div className="mt-6 rounded-2xl border border-line bg-white p-6">
          <p className="text-sm text-muted">Text practice isn&apos;t switched on for your batch yet — voice interviews below are ready when you are.</p>
        </div>
      )}

      <div className="mt-4 rounded-2xl border border-line bg-white p-6">
        <div className="flex items-baseline justify-between">
          <p className="text-xs font-semibold uppercase tracking-widest text-muted">
            Voice interview · up to {summary.voice.max_minutes} min
          </p>
          <span className="mono text-xs text-muted">
            {summary.voice.credits} session{summary.voice.credits === 1 ? "" : "s"} left
          </span>
        </div>
        {voiceError && <p className="mt-2 text-sm text-warn">{voiceError}</p>}
        {voiceNotice && <p className="mt-2 text-sm text-verify">{voiceNotice}</p>}

        {!summary.voice.provider_ready ? (
          <>
            <p className="mt-2 text-sm text-ink">
              Interview out loud in the video-style room — the interviewer speaks every question and
              listens to your answers, camera on, right in your browser.
            </p>
            {summary.in_progress_id !== null ? (
              <>
                <Link
                  href={`/mock/${summary.in_progress_id}/room`}
                  className="mt-3 inline-block rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white"
                >
                  📹 Return to the interview room →
                </Link>
                <p className="mt-2 text-xs text-muted">Rejoining your open interview is free — it&apos;s the same session.</p>
              </>
            ) : summary.voice.credits > 0 ? (
              <>
                <button
                  onClick={() => start("room")}
                  disabled={busy || !summary.enabled || (summary.blueprints.length > 1 && !chosen)}
                  className="mt-3 rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white disabled:opacity-50"
                >
                  {busy ? "Opening the room…" : "📹 Start in the interview room"}
                </button>
                <p className="mt-2 text-xs text-muted">Each room interview uses 1 session.</p>
                {summary.enabled && summary.blueprints.length > 1 && !chosen && (
                  <p className="mt-1 text-xs text-muted">Pick a skill in the text practice card first.</p>
                )}
              </>
            ) : (
              <>
                <p className="mt-2 text-sm text-ink">You&apos;ve used your free sessions. Top up to keep going:</p>
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
          </>
        ) : summary.voice.in_progress?.join_url ? (
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

      {summary.gap_report.items.length > 0 && (
        <div className="mt-8">
          <h2 className="text-sm font-semibold uppercase tracking-widest text-muted">
            What real {summary.gap_report.role_title ?? ""} interviews test
          </h2>
          <p className="mt-1 text-xs text-muted">
            Weights come from questions asked in actual interviews. Your score is from your best mock —
            close the red gaps first.
          </p>
          <div className="mt-3 space-y-3 rounded-2xl border border-line bg-white p-5">
            {summary.gap_report.items.map((item) => (
              <div key={item.topic}>
                <div className="flex items-baseline justify-between text-sm">
                  <span className="text-ink">{item.topic}</span>
                  <span className="mono text-xs text-muted">
                    real weight {item.real_weight_pct}% ·{" "}
                    {item.your_score !== null ? (
                      <span className={item.gap ? "text-warn" : "text-verify"}>you: {item.your_score}</span>
                    ) : (
                      <span className="text-warn">untested</span>
                    )}
                  </span>
                </div>
                <div className="mt-1 h-1.5 rounded-full bg-paper">
                  <div
                    className={`h-1.5 rounded-full ${item.gap ? "bg-warn/70" : "bg-verify"}`}
                    style={{ width: `${Math.max(4, item.real_weight_pct)}%` }}
                  />
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      <h2 className="mt-8 text-sm font-semibold uppercase tracking-widest text-muted">Past interviews</h2>
      {summary.mocks.length === 0 ? (
        <p className="mt-3 text-sm text-muted">No completed interviews yet. Your scorecards will appear here.</p>
      ) : (
        <div className="mt-3 space-y-2">
          {summary.mocks.map((m) => (
            <Link
              key={m.id}
              href={`/mock/${m.id}`}
              className="flex items-center justify-between rounded-[14px] border border-line bg-white p-4 hover:border-trust"
            >
              <span className="text-sm text-ink">
                {m.completed_at ? new Date(m.completed_at).toLocaleDateString("en-IN", { day: "numeric", month: "short", year: "numeric" }) : "Completed"}
              </span>
              <span className={`text-sm font-semibold ${scoreTone(m.overall_score)}`}>
                {m.overall_score ?? "—"}/100
              </span>
            </Link>
          ))}
        </div>
      )}
    </div>
  );
}
