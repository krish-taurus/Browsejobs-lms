"use client";

import { formatPaise } from "@/lib/money";
import { type FeeStatus, feeUrgency, useFeeStatus } from "@/lib/fee-status";

const STYLES: Record<string, string> = {
  clear: "border-verify/30 bg-verify-bg text-verify",
  info: "border-line bg-sky text-deep",
  warning: "border-trust/40 bg-sky text-deep",
  urgent: "border-warn/30 bg-warn/10 text-warn",
  overdue: "border-warn bg-warn text-white",
};

function countdownLabel(s: FeeStatus): string {
  const d = s.days_to_due ?? 0;
  if (s.overdue || d < 0) return `overdue by ${Math.abs(d)} day${Math.abs(d) === 1 ? "" : "s"}`;
  if (d === 0) return "due today";
  return `due in ${d} day${d === 1 ? "" : "s"}`;
}

export function FeeWidget() {
  const { status, loading } = useFeeStatus();

  if (loading) return <div className="shimmer mt-6 h-24 rounded-2xl" />;
  if (!status || !status.has_plan) return null;

  const urgency = feeUrgency(status);
  if (urgency === "clear") {
    return (
      <div className="mt-6 rounded-2xl border border-verify/30 bg-verify-bg p-5 text-verify">
        <p className="kicker">Fees</p>
        <p className="mt-1 text-sm font-semibold">You’re all clear — no dues outstanding.</p>
      </div>
    );
  }

  const cls = STYLES[urgency];
  const onDark = urgency === "overdue";

  return (
    <div className={`mt-6 rounded-2xl border p-5 ${cls}`}>
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <p className="kicker">{status.block ? "Fees — access locked" : "Fees due"}</p>
          <p className="mono mt-1 text-2xl font-bold">
            {formatPaise(status.next_amount_paise ?? 0)}
            <span className={`ml-2 text-sm font-medium ${onDark ? "text-white/80" : "opacity-80"}`}>
              {countdownLabel(status)}
            </span>
          </p>
          {status.block === "soft" && (
            <p className={`mt-1 text-xs ${onDark ? "text-white/80" : "opacity-80"}`}>
              Live classes and recordings are locked until you pay.
            </p>
          )}
        </div>
        {status.pay_url ? (
          <a
            href={status.pay_url}
            target="_blank"
            rel="noreferrer"
            className={`rounded-full px-5 py-2.5 text-sm font-semibold ${onDark ? "bg-white text-warn" : "bg-trust text-white hover:bg-deep"}`}
          >
            Pay now
          </a>
        ) : (
          <span className={`text-xs ${onDark ? "text-white/80" : "opacity-80"}`}>
            Your payment link is being prepared — refresh shortly, or ask your counselor.
          </span>
        )}
      </div>
    </div>
  );
}
