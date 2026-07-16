/** Shared payment/EMI types for the admin UI (PRD §6.8). Mirrors the API resources. */

export type Instalment = {
  id: number;
  seq: number;
  amount_paise: number;
  due_on: string | null;
  status: "pending" | "paid" | "failed";
  paid_at: string | null;
  payment_link_url: string | null;
  razorpay_order_id: string | null;
};

export type LedgerEntry = {
  id: number;
  direction: "debit" | "credit";
  amount_paise: number;
  description: string;
  occurred_at: string | null;
};

export type Receipt = {
  id: number;
  number: string;
  taxable_paise: number;
  cgst_paise: number;
  sgst_paise: number;
  total_paise: number;
  status: string;
  created_at: string | null;
};

export type FeePlan = {
  id: number;
  type: "single" | "emi";
  status: "draft" | "active" | "paid" | "cancelled";
  total_paise: number;
  discount_paise: number;
  net_payable_paise: number;
  gst_rate_bps: number;
  currency: string;
  created_at: string | null;
  student?: { id: number; name: string; phone: string | null; email: string | null } | null;
  batch?: { id: number; number: string; course?: { id: number; name: string } | null } | null;
  instalments?: Instalment[];
  ledger?: LedgerEntry[];
  receipts?: Receipt[];
};

export type ScheduleRow = { seq: number; amount_paise: number; due_on: string };

export type BatchOption = { id: number; number: string; course?: { id: number; name: string } | null };
export type Candidate = { user_id: number; name: string | null; phone: string | null; status: string };
