"use client";

import { MockKindTabs } from "@/components/mocks/MockKindTabs";
import { TextPracticeCard, VoiceInterviewCard, useMockSummary } from "@/components/mocks/MockCards";

export default function MockHubPage() {
  const { summary, loading, reload } = useMockSummary();

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

      <TextPracticeCard summary={summary} />
      <VoiceInterviewCard summary={summary} reload={reload} />

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

      <h2 className="mt-8 text-sm font-semibold uppercase tracking-widest text-muted">Your interviews</h2>
      <p className="mt-1 text-xs text-muted">Each type keeps its own list — pick one to see its scorecards.</p>
      <div className="mt-3">
        <MockKindTabs counts={summary.kind_counts ?? {}} />
      </div>
    </div>
  );
}
