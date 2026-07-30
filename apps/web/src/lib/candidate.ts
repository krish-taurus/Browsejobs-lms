import { apiJson } from "@/lib/api";

/** Candidate-side API surface (PRD-E F9/F10/F11). */

export type VerificationCheck = {
  kind: "identity" | "education" | "employment" | "documents";
  label: string;
  status: "not_started" | "pending" | "verified" | "failed" | "expired";
  status_label: string;
  prompt: string;
  employer_value: string;
  required_for_badge: boolean;
  verified_at: string | null;
  expires_at: string | null;
  failure_reason: string | null;
};

export type VerificationSummary = {
  badge: boolean;
  verified_count: number;
  total_count: number;
  checks: VerificationCheck[];
  missing_for_badge: string[];
  /** Null whenever the sample is too small to state honestly. */
  observed_uplift: { verified_rate: number; unverified_rate: number; sample: number } | null;
};

export type CandidateApplication = {
  id: number;
  job_id: number;
  title: string | null;
  company: string | null;
  stage: string;
  mock_score: number | null;
  mock_attempts: number;
  graded_at: string | null;
  applied_at: string | null;
  awaiting_candidate: boolean;
};

export type CandidateDashboardData = {
  verification: VerificationSummary;
  applications: CandidateApplication[];
  funnel: Record<string, number>;
  mock_history: { date: string; score: number; role: string | null }[];
  profile_completeness: {
    pct: number;
    items: { key: string; label: string; done: boolean }[];
  };
  credits: { mock: number; cv: number };
  /** Null until enough offers exist to compare against. */
  cohort: {
    offer_median_mock: number;
    your_best_mock: number | null;
    sample: number;
    verified_share_pct: number;
  } | null;
};

export type InternalJob = {
  segment: "internal";
  id: number;
  title: string;
  company: string | null;
  locations: string[];
  remote: boolean;
  skills: string[];
  experience_min_years: number | null;
  experience_max_years: number | null;
  openings: number | null;
  posted_at: string | null;
  mock_ready: boolean;
  has_applied: boolean;
};

export type ExternalJob = {
  segment: "external";
  id: number;
  title: string;
  company: string;
  location: string | null;
  work_mode: string | null;
  skills: string[];
  seniority: string | null;
  posted_at: string | null;
  source_kind: string;
  question_count: number;
};

export type JobBoard = {
  internal: InternalJob[];
  external: ExternalJob[];
  counts: { internal: number; external: number };
};

export const STAGE_LABELS: Record<string, string> = {
  applied: "Applied",
  graded: "Graded",
  shortlisted: "Shortlisted",
  l1: "Round 1",
  l2: "Round 2",
  human_round: "Live round",
  offer: "Offer",
  hired: "Hired",
  rejected: "Closed",
  withdrawn: "Withdrawn",
};

const base = "/api/v1";

export const candidateApi = {
  dashboard: () => apiJson<{ data: CandidateDashboardData }>(`${base}/me/candidate-dashboard`),
  board: (q?: string) =>
    apiJson<{ data: JobBoard }>(`${base}/job-board${q ? `?q=${encodeURIComponent(q)}` : ""}`),
  verification: () => apiJson<{ data: VerificationSummary }>(`${base}/me/verification`),
  submitCheck: (kind: string, body: Record<string, unknown>) =>
    apiJson<{ data: { kind: string; status: string; status_label: string } }>(
      `${base}/me/verification/${kind}`,
      { method: "POST", body: JSON.stringify(body) },
    ),
  applyToJob: (jobId: number) =>
    apiJson<{ data: { id: number } }>(`${base}/me/employer-jobs/${jobId}/apply`, { method: "POST" }),
  startMock: (jobId: number) =>
    apiJson<{ data: { mock_id: number } }>(`${base}/me/employer-jobs/${jobId}/mock`, {
      method: "POST",
    }),
};
