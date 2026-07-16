"use client";

import { formatPaise } from "@/lib/money";
import { type FeeStatus } from "@/lib/fee-status";

/**
 * Full-screen fee lockout for a HARD block (PRD §6.8: "fee screen only").
 * Shown by PortalShell in place of the app; the only action is to pay.
 */
export function FeeBlockedScreen({ status, onSignOut }: { status: FeeStatus; onSignOut: () => void }) {
  return (
    <div className="grid min-h-screen place-items-center bg-paper px-5">
      <div className="w-full max-w-md rounded-2xl border border-warn/30 bg-white p-8 text-center shadow-soft">
        <div className="mx-auto grid h-12 w-12 place-items-center rounded-full bg-warn/10">
          <svg viewBox="0 0 24 24" className="h-6 w-6 text-warn" fill="none">
            <path d="M12 9v4m0 4h.01M12 3l9 16H3L12 3Z" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" />
          </svg>
        </div>
        <h1 className="display mt-4 text-2xl text-ink">Your access is paused</h1>
        <p className="mt-2 text-sm text-muted">
          Your registration fee is overdue, so classes and recordings are locked. Clear the balance to restore access instantly.
        </p>
        <p className="mono mt-5 text-3xl font-bold text-ink">{formatPaise(status.outstanding_paise ?? 0)}</p>
        <p className="mono text-[11px] uppercase tracking-widest text-muted">Outstanding</p>

        {status.pay_url && (
          <a
            href={status.pay_url}
            target="_blank"
            rel="noreferrer"
            className="mt-6 inline-block rounded-full bg-trust px-6 py-3 text-sm font-semibold text-white hover:bg-deep"
          >
            Pay now to unlock
          </a>
        )}
        <p className="mt-4 text-xs text-muted">A counselor will also reach out to help. Paid already? Access restores within moments.</p>
        <button onClick={onSignOut} className="mt-5 text-xs text-muted underline hover:text-ink">Sign out</button>
      </div>
    </div>
  );
}
