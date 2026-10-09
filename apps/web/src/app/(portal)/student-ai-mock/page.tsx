"use client";

import { AiInterviewsHub } from "@/components/mocks/AiInterviewsHub";
import { useMockSummary } from "@/components/mocks/MockCards";

export default function MockHubPage() {
  const { summary, loading, reload } = useMockSummary();

  if (loading) {
    return (
      <div className="mx-auto max-w-6xl" aria-busy="true">
        <div className="shimmer h-24 rounded-[18px]" />
        <div className="mt-6 grid gap-6 lg:grid-cols-3">
          <div className="shimmer h-80 rounded-[18px] lg:col-span-2" />
          <div className="shimmer h-80 rounded-[18px]" />
        </div>
      </div>
    );
  }

  if (!summary) {
    return (
      <div className="mx-auto max-w-2xl">
        <h1 className="display text-2xl text-ink">Mock Interviews</h1>
        <div className="mt-6 rounded-[18px] border border-line bg-white p-8 text-center">
          <p className="text-sm text-ink">Practice interviews aren&apos;t available right now.</p>
          <p className="mt-1 text-sm text-muted">Ask your counselor — or check back soon.</p>
        </div>
      </div>
    );
  }

  return (
    <AiInterviewsHub summary={summary} reload={reload}>
      {summary.module_mocks.length > 0 && (
        <section aria-labelledby="module-mocks-title" className="mt-6 rounded-[18px] border border-line bg-white p-6 shadow-soft sm:p-7">
          <h2 id="module-mocks-title" className="display text-xl text-ink">Module mocks</h2>
          <p className="mt-1 text-sm text-muted">
            Each module you finish unlocks a set of mocks to clear. Any mock you complete counts toward the next one due.
          </p>
          <ul className="mt-4 space-y-3">
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
                    <span className="mono text-[11px] font-semibold uppercase tracking-widest text-verify">Cleared</span>
                  ) : (
                    <span className="mono text-xs text-muted">{m.completed}/{m.required}</span>
                  )}
                </span>
              </li>
            ))}
          </ul>
        </section>
      )}

      {summary.gap_report.items.length > 0 && (
        <section aria-labelledby="gap-title" className="mt-6 rounded-[18px] border border-line bg-white p-6 shadow-soft sm:p-7">
          <h2 id="gap-title" className="display text-xl text-ink">
            What real {summary.gap_report.role_title ?? ""} interviews test
          </h2>
          <p className="mt-1 text-sm text-muted">
            Weights come from questions asked in actual interviews. Your score is from your best mock — close the red gaps first.
          </p>
          <div className="mt-4 space-y-3">
            {summary.gap_report.items.map((item) => (
              <div key={item.topic}>
                <div className="flex items-baseline justify-between gap-3 text-sm">
                  <span className="text-ink">{item.topic}</span>
                  <span className="mono shrink-0 text-xs text-muted">
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
        </section>
      )}
    </AiInterviewsHub>
  );
}
