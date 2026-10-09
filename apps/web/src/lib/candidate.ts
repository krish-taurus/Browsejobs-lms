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

export type Momentum = {
  nudge: {
    headline: string;
    body: string;
    cta_label: string;
    cta_kind: "mock" | "verify" | "apply" | "browse" | "package";
    /** "ai" when the model wrote it, "fallback" when we did. */
    source: "ai" | "fallback";
  };
  /** Real, consented placement stories. Never model-generated. */
  stories: {
    id: number;
    name: string;
    before: string | null;
    after: string | null;
    company: string | null;
    company_color: string | null;
    rounds: number | null;
    quote: string | null;
  }[];
  packages: {
    sku: string;
    name: string;
    feature: string;
    price_paise: number;
    grant_amount: number;
  }[];
};

export type CandidateDashboardData = {
  momentum: Momentum;
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
  // Public URL of the employer's logo (set in the CRM), or null.
  company_logo?: string | null;
  locations: string[];
  remote: boolean;
  skills: string[];
  experience_min_years: number | null;
  experience_max_years: number | null;
  openings: number | null;
  posted_at: string | null;
  mock_ready: boolean;
  has_applied: boolean;
  // Where the candidate stands on this JD's own interview — the step Apply
  // is gated behind. "none" | "in_progress" | "completed".
  mock_status: "none" | "in_progress" | "completed";
  mock_score: number | null;
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

/** A document the candidate uploaded towards a check (PRD-E F9). */
export type CandidateDocumentRow = {
  id: number;
  kind: string;
  label: string;
  original_name: string;
  size_bytes: number;
  review_status: "pending" | "accepted" | "rejected";
  /**
   * False for a PDF or a photo: we hold the file but cannot read its text,
   * so it waits on a person rather than an assessment.
   */
  machine_readable: boolean;
  uploaded_at: string | null;
  reviewed_at: string | null;
};

export type CandidateDocumentList = {
  data: CandidateDocumentRow[];
  meta: { kinds: { key: string; label: string }[]; max_kilobytes: number };
};

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
  documents: () => apiJson<CandidateDocumentList>(`${base}/me/verification-documents`),
  deleteDocument: (id: number) =>
    apiJson<null>(`${base}/me/verification-documents/${id}`, { method: "DELETE" }),
  applyToJob: (jobId: number) =>
    apiJson<{ data: { id: number } }>(`${base}/me/employer-jobs/${jobId}/apply`, { method: "POST" }),
  startMock: (jobId: number) =>
    apiJson<{ data: { mock_id: number } }>(`${base}/me/employer-jobs/${jobId}/mock`, {
      method: "POST",
    }),
};

export type PublicJob = {
  id: number;
  title: string;
  description: string;
  role_family: string | null;
  skills: string[] | null;
  experience_min_years: number | null;
  experience_max_years: number | null;
  locations: string[] | null;
  remote: boolean;
  ctc_min_paise?: number | null;
  ctc_max_paise?: number | null;
  openings: number | null;
  company?: { name: string; industry: string | null; company_size: string | null };
  published_at: string | null;
};

export type MyMockStatus = {
  status: "none" | "in_progress" | "completed";
  mock_id: number | null;
  score: number | null;
  attempts: { used: number; limit: number };
  has_recording?: boolean;
};

export const jobApi = {
  show: (id: number) => apiJson<{ data: PublicJob }>(`${base}/me/employer-jobs/${id}`),
  myApplications: () =>
    apiJson<{ data: { id: number; employer_job_id: number; stage: string; mock_score: number | null }[] }>(
      `${base}/me/employer-jobs/applications`,
    ),
  /** Whether this candidate has a completed/in-progress mock for this JD — the Apply gate. */
  myMock: (jobId: number) => apiJson<{ data: MyMockStatus }>(`${base}/me/employer-jobs/${jobId}/my-mock`),
};

/**
 * The AI Readiness Interview: general, CV-driven, not tied to any job.
 * Same status shape as a JD's myMock() — completing it is what makes a
 * student's profile visible in an employer's Talent Pool search, even
 * without ever applying anywhere.
 */
export const cvMockApi = {
  status: () => apiJson<{ data: MyMockStatus }>(`${base}/me/cv-mock`),
  start: () => apiJson<{ data: { mock_id: number } }>(`${base}/me/cv-mock`, { method: "POST" }),
};

/**
 * A candidate's own view of their interview recording (candidate request,
 * Aug 2026) — works for any mock interview by id, not just the AI
 * Readiness one. Deleting removes only the recording file; the score and
 * scorecard are untouched.
 */
export const mockRecordingApi = {
  view: (mockId: number) => apiJson<{ data: { url: string } }>(`${base}/me/mocks/${mockId}/recording`),
  remove: (mockId: number) => apiJson<{ ok: boolean }>(`${base}/me/mocks/${mockId}/recording`, { method: "DELETE" }),
};
