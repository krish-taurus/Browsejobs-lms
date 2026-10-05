/**
 * Score bands (PRD-E pipeline kit): Strong 80+, Fair 60 up to but below 80,
 * Below bar under 60. Tied to the job-specific interview score alone — never
 * to stage. A valid 0 is scored ("below"), not unscored; only a genuinely
 * missing value (null/undefined) reads as unscored.
 */
export type ScoreBand = "strong" | "fair" | "below" | "unscored";

export function scoreBand(score: number | null | undefined): ScoreBand {
  if (score === null || score === undefined) return "unscored";
  if (score >= 80) return "strong";
  if (score >= 60) return "fair";
  return "below";
}

export const BAND_LABEL: Record<ScoreBand, string> = {
  strong: "Strong",
  fair: "Fair",
  below: "Below bar",
  unscored: "Unscored",
};

export const BAND_FG: Record<ScoreBand, string> = {
  strong: "var(--bj-dash-score-strong)",
  fair: "var(--bj-dash-score-fair)",
  below: "var(--bj-dash-score-below)",
  unscored: "var(--bj-dash-score-unscored)",
};

export const BAND_BG: Record<ScoreBand, string> = {
  strong: "var(--bj-dash-score-strong-bg)",
  fair: "var(--bj-dash-score-fair-bg)",
  below: "var(--bj-dash-score-below-bg)",
  unscored: "var(--bj-dash-score-unscored-bg)",
};
