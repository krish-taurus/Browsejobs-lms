"use client";

import { formatPaise } from "@/lib/money";
import { type FeeStatus, useFeeStatus } from "@/lib/fee-status";

/**
 * The whole fee picture, in the student's own words: what the course costs,
 * what they have paid, what is left, and every instalment with its date. The
 * urgent nudge lives in FeeWidget above — this is the part they can trust and
 * check, so it stays calm even when a payment is late.
 */

function dueLabel(due: string | null): string {
  if (!due) return "Date to be set";

  return new Date(due).toLocaleDateString("en-IN", {
    day: "2-digit",
    month: "short",
    year: "numeric",
  });
}

function totals(status: FeeStatus) {
  const instalments = status.instalments ?? [];
  const total = instalments.reduce((sum, i) => sum + i.amount_paise, 0);
  const paid = instalments
    .filter((i) => i.status === "paid")
    .reduce((sum, i) => sum + i.amount_paise, 0);

  // The server's outstanding is the truth when it is there; the subtraction is
  // only a fallback so the card never shows a blank.
  const remaining = status.outstanding_paise ?? Math.max(0, total - paid);

  return { total, paid, remaining, instalments };
}

export function FeeSummary() {
  const { status, loading } = useFeeStatus();

  if (loading) return <div className="shimmer mt-6 h-56 rounded-2xl" />;
  if (!status?.has_plan) return null;

  const { total, paid, remaining, instalments } = totals(status);
  if (total === 0) return null;

  const paidPct = Math.min(100, Math.round((paid / total) * 100));
  const nextSeq = instalments.find((i) => i.status !== "paid")?.seq ?? null;

  return (
    <section className="mt-6 rounded-2xl border border-line bg-white p-5">
      <div className="flex flex-wrap items-baseline justify-between gap-2">
        <p className="kicker">Your fees</p>
        {status.batch && <p className="text-xs text-muted">Batch {status.batch}</p>}
      </div>

      <dl className="mt-4 grid gap-3 sm:grid-cols-3">
        <div className="rounded-xl border border-line bg-paper px-4 py-3">
          <dt className="text-xs text-muted">Course fee</dt>
          <dd className="mono mt-1 text-xl font-bold text-ink">{formatPaise(total)}</dd>
        </div>
        <div className="rounded-xl border border-verify/30 bg-verify-bg px-4 py-3">
          <dt className="text-xs text-verify">Paid so far</dt>
          <dd className="mono mt-1 text-xl font-bold text-verify">{formatPaise(paid)}</dd>
        </div>
        <div className={`rounded-xl border px-4 py-3 ${remaining > 0 ? "border-trust/30 bg-sky" : "border-line bg-paper"}`}>
          <dt className={`text-xs ${remaining > 0 ? "text-deep" : "text-muted"}`}>Remaining</dt>
          <dd className={`mono mt-1 text-xl font-bold ${remaining > 0 ? "text-deep" : "text-ink"}`}>
            {formatPaise(remaining)}
          </dd>
        </div>
      </dl>

      <div className="mt-4">
        <div className="h-2 w-full overflow-hidden rounded-full bg-line" role="presentation">
          <div className="h-full rounded-full bg-verify transition-all" style={{ width: `${paidPct}%` }} />
        </div>
        <p className="mt-2 text-xs text-muted">
          {paidPct}% paid
          {instalments.length > 0 && (
            <> · {instalments.filter((i) => i.status === "paid").length} of {instalments.length} instalments cleared</>
          )}
        </p>
      </div>

      {instalments.length > 0 && (
        <ol className="mt-5 divide-y divide-line border-t border-line">
          {instalments.map((instalment) => {
            const isPaid = instalment.status === "paid";
            const isNext = !isPaid && instalment.seq === nextSeq;

            return (
              <li key={instalment.seq} className="flex flex-wrap items-center justify-between gap-2 py-3">
                <div>
                  <p className="text-sm font-medium text-ink">
                    Instalment {instalment.seq} of {instalments.length}
                  </p>
                  <p className="text-xs text-muted">{dueLabel(instalment.due_on)}</p>
                </div>

                <div className="flex items-center gap-3">
                  <span className="mono text-sm font-semibold text-ink">{formatPaise(instalment.amount_paise)}</span>

                  {isPaid ? (
                    <span className="rounded-full bg-verify-bg px-2.5 py-1 text-xs font-semibold text-verify">Paid</span>
                  ) : isNext ? (
                    <span className="rounded-full bg-sky px-2.5 py-1 text-xs font-semibold text-deep">Next</span>
                  ) : (
                    <span className="rounded-full bg-paper px-2.5 py-1 text-xs font-semibold text-muted">Upcoming</span>
                  )}

                  {isNext && status.pay_url && (
                    <a
                      href={status.pay_url}
                      target="_blank"
                      rel="noreferrer"
                      className="rounded-full bg-trust px-4 py-1.5 text-xs font-semibold text-white hover:bg-deep"
                    >
                      Pay
                    </a>
                  )}
                </div>
              </li>
            );
          })}
        </ol>
      )}

      <p className="mt-4 text-xs text-muted">
        Payments are receipted the moment they clear. Something look wrong? Raise it under Support and we will check it
        with you.
      </p>
    </section>
  );
}
