"use client";

import { useEffect, useState } from "react";
import { apiJson } from "@/lib/api";

/** Student fee/dunning status (PRD §6.8), from GET /api/v1/me/fee-status. */
export type FeeStatus = {
  has_plan: boolean;
  fee_plan_id?: number;
  batch?: string | null;
  status?: "active" | "paid";
  outstanding_paise?: number;
  next_amount_paise?: number;
  next_due_on?: string | null;
  days_to_due?: number; // negative == overdue by |n|
  overdue?: boolean;
  block?: "soft" | "hard" | null;
  pay_url?: string | null;
  instalments?: { seq: number; amount_paise: number; due_on: string | null; status: string }[];
};

/** Escalation level for banner styling: info → warning → urgent → overdue. */
export type FeeUrgency = "clear" | "info" | "warning" | "urgent" | "overdue";

export function feeUrgency(s: FeeStatus): FeeUrgency {
  if (!s.has_plan || s.status === "paid" || s.next_amount_paise === 0) return "clear";
  if (s.block) return "overdue";
  const d = s.days_to_due ?? 99;
  if (d < 0) return "overdue";
  if (d <= 1) return "urgent";
  if (d <= 3) return "warning";
  return "info";
}

export function useFeeStatus() {
  const [status, setStatus] = useState<FeeStatus | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let alive = true;
    apiJson<{ data: FeeStatus }>("/api/v1/me/fee-status")
      .then((r) => { if (alive) setStatus(r.data); })
      .catch(() => { if (alive) setStatus({ has_plan: false }); })
      .finally(() => { if (alive) setLoading(false); });
    return () => { alive = false; };
  }, []);

  return { status, loading };
}
