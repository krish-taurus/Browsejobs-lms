"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useCallback, useEffect, useState } from "react";
import { ApiError, apiJson } from "@/lib/api";

type MockSummary = {
  enabled: boolean;
  in_progress_id: number | null;
  best_score: number;
  human_mock_unlocked: boolean;
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

  const load = useCallback(() => {
    apiJson<{ data: MockSummary }>("/api/v1/me/mocks")
      .then((r) => setSummary(r.data))
      .catch(() => setSummary(null))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => { load(); }, [load]);

  async function start() {
    setError(null);
    setBusy(true);
    try {
      const r = await apiJson<{ data: { id: number } }>("/api/v1/me/mocks", { method: "POST" });
      router.push(`/mock/${r.data.id}`);
    } catch (err) {
      setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Something went wrong.");
      setBusy(false);
    }
  }

  if (loading) return <div className="mx-auto max-w-2xl"><div className="shimmer h-64 rounded-[14px]" /></div>;

  if (!summary || !summary.enabled) {
    return (
      <div className="mx-auto max-w-2xl">
        <h1 className="display text-2xl text-ink">Mock Interviews</h1>
        <div className="mt-6 rounded-2xl border border-line bg-white p-8 text-center">
          <p className="text-sm text-ink">Practice interviews aren&apos;t switched on for your batch yet.</p>
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

      <div className="mt-6 rounded-2xl border border-line bg-white p-6">
        {summary.in_progress_id ? (
          <>
            <p className="text-sm text-ink">You have an interview in progress.</p>
            <Link
              href={`/mock/${summary.in_progress_id}`}
              className="mt-3 inline-block rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white"
            >
              Resume interview →
            </Link>
          </>
        ) : (
          <>
            <p className="text-sm text-ink">Ready when you are — it takes about 10 minutes.</p>
            {error && <p className="mt-2 text-sm text-warn">{error}</p>}
            <button
              onClick={start}
              disabled={busy}
              className="mt-3 rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white disabled:opacity-50"
            >
              {busy ? "Setting up…" : "Start a mock interview"}
            </button>
          </>
        )}
      </div>

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
