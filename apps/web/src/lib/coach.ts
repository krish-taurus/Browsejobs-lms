"use client";

import { useEffect, useState } from "react";
import { apiJson } from "@/lib/api";

/** A module's mastery, from the rule-based ScoreCalculator (PRD §6.4). */
export type MasteryRow = {
  module_id: number;
  name: string;
  pct: number;
  band: "weak" | "developing" | "strong";
};

/** The single Next Best Action — the loudest element on the dashboard. */
export type NextAction = {
  key: "clear_fee" | "practice_module" | "re_engage" | "book_mock" | "next_topic";
  title: string;
  detail: string;
  href: string | null;
};

/** Coach Panel payload from GET /api/v1/me/coach. */
export type Coach = {
  pri: number;
  engagement: number;
  risk: { dropout: number; placement: number; dropout_band: "low" | "medium" | "high" };
  streak_days: number;
  mastery: MasteryRow[];
  went_well: string[];
  needs_work: string[];
  next_action: NextAction;
  trajectory: { on: string; pri: number }[];
  /** Evidence-backed claims from anonymised cohort aggregates (PRD §6.21). */
  insights: string[];
};

export function useCoach() {
  const [coach, setCoach] = useState<Coach | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(false);

  useEffect(() => {
    let alive = true;
    apiJson<{ data: Coach }>("/api/v1/me/coach")
      .then((r) => { if (alive) setCoach(r.data); })
      .catch(() => { if (alive) setError(true); })
      .finally(() => { if (alive) setLoading(false); });
    return () => { alive = false; };
  }, []);

  return { coach, loading, error };
}
