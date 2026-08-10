"use client";

import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { ApiError, apiJson } from "@/lib/api";
import { formatPaise } from "@/lib/money";

/**
 * Dashboard fee choice: shown to a student holding a paid seat that has no fee
 * plan yet. They pick pay-in-full or an EMI schedule, see the real dates and
 * amounts before committing, and land on the normal FeeWidget with a payment
 * link once they choose. Hidden the moment a plan exists.
 */

type Row = { seq: number; amount_paise: number; due_on: string };
type Option = { type: "single" | "emi"; emi_count: number; label: string; rows: Row[] };
type Offer = {
  batch: string | null;
  course: string | null;
  total_paise: number;
  options: Option[];
};

const dateLabel = (iso: string) =>
  new Intl.DateTimeFormat("en-IN", { timeZone: "Asia/Kolkata", day: "numeric", month: "short", year: "numeric" })
    .format(new Date(`${iso}T00:00:00`));

export function FeeChoiceCard() {
  const [picked, setPicked] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const { data, isLoading } = useQuery({
    queryKey: ["me", "fee-offer"],
    queryFn: () => apiJson<{ data: { has_plan: boolean; offer: Offer | null } }>("/api/v1/me/fee-offer"),
  });

  const offer = data?.data.offer ?? null;

  if (isLoading || !offer || offer.options.length === 0) return null;

  const key = (o: Option) => `${o.type}:${o.emi_count}`;
  const selected = offer.options.find((o) => key(o) === picked) ?? offer.options[0];

  const confirm = async () => {
    setBusy(true);
    setError(null);
    try {
      await apiJson("/api/v1/me/fee-plan", {
        method: "POST",
        body: JSON.stringify({ type: selected.type, emi_count: selected.emi_count }),
      });
      // The FeeWidget and the shell's fee gate both read fee-status on mount
      // without React Query, so a reload is what actually hands over to them.
      window.location.reload();
      return;
    } catch (err) {
      setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Could not set that up. Try again.");
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="mt-6 rounded-2xl border border-trust/40 bg-white p-5">
      <p className="kicker text-trust">Course fee</p>
      <p className="display mt-1 text-2xl text-ink">
        {formatPaise(offer.total_paise)}
        {offer.course && <span className="ml-2 text-sm font-medium text-muted">{offer.course}</span>}
      </p>
      <p className="mt-1 text-sm text-muted">
        Your seat in {offer.batch ?? "this batch"} is held. Choose how you&apos;d like to pay.
      </p>

      <div className="mt-4 grid gap-2 sm:grid-cols-2">
        {offer.options.map((option) => {
          const active = key(option) === key(selected);
          const first = option.rows[0];
          return (
            <button
              key={key(option)}
              type="button"
              onClick={() => setPicked(key(option))}
              className={`rounded-[14px] border p-4 text-left transition-colors ${
                active ? "border-trust bg-sky/40" : "border-line hover:bg-paper"
              }`}
            >
              <span className="text-sm font-semibold text-ink">{option.label}</span>
              <span className="mono mt-1 block text-lg font-bold text-ink">
                {formatPaise(first?.amount_paise ?? 0)}
                {option.rows.length > 1 && <span className="text-xs font-medium text-muted"> now</span>}
              </span>
              {option.rows.length > 1 && (
                <span className="block text-xs text-muted">then {option.rows.length - 1} more monthly</span>
              )}
            </button>
          );
        })}
      </div>

      {/* The exact schedule, before they commit to it. */}
      <div className="mt-4 divide-y divide-line rounded-[14px] border border-line">
        {selected.rows.map((row) => (
          <div key={row.seq} className="flex items-center justify-between px-4 py-2 text-sm">
            <span className="text-muted">
              {selected.rows.length === 1 ? "Due" : `Instalment ${row.seq}`} · {dateLabel(row.due_on)}
            </span>
            <span className="mono font-semibold text-ink">{formatPaise(row.amount_paise)}</span>
          </div>
        ))}
      </div>

      {error && <p className="mt-3 text-sm text-warn">{error}</p>}

      <button
        onClick={confirm}
        disabled={busy}
        className="mt-4 rounded-full bg-trust px-6 py-2.5 text-sm font-semibold text-white transition-transform hover:-translate-y-0.5 disabled:opacity-50"
      >
        {busy ? "Setting up…" : selected.rows.length === 1 ? "Confirm and pay" : "Confirm this plan"}
      </button>
      <p className="mt-2 text-xs text-muted">
        You&apos;ll get a payment link for the first instalment straight after confirming.
      </p>
    </div>
  );
}
