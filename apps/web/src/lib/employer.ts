/**
 * Employer portal API client (PRD-E). Thin typed wrappers over apiJson —
 * one module per domain, matching src/lib conventions.
 */
import { apiJson } from "@/lib/api";

export type EmployerRole = "owner" | "recruiter" | "hiring_manager";

export type Workspace = {
  id: number;
  name: string;
  slug: string;
  industry: string | null;
  company_size: string | null;
  status: string;
  my_role: EmployerRole | null;
  members_count?: number;
};

export type StageCounts = Record<string, number>;

export type TalentPoolCandidate = {
  candidate_id: number;
  name: string;
  match_score: number;
  skill_match_pct: number;
  matched_skills: string[];
  missing_skills: string[];
  readiness_index: number;
  mock_average: number;
  mock_attempts: number;
  training: { course: string; status: string } | null;
  cv_ready: boolean;
};

export type DashboardData = {
  funnel: { stage: string; label: string; count: number }[];
  trend: { date: string; applications: number; graded: number }[];
  score_distribution: { band: string; floor: number; count: number }[];
  active_jobs: number;
  total_applications: number;
  graded_applications: number;
  graded_last_7d: number;
  awaiting_review: number;
  interviews_in_flight: number;
  offers_open: number;
  hired: number;
  pipeline: {
    id: number;
    title: string;
    published_at: string | null;
    stage_counts: StageCounts;
    awaiting_review: number;
  }[];
};

export type JobStatus = "draft" | "published" | "paused" | "closed";

export type EmployerJobRow = {
  id: number;
  title: string;
  role_family: string | null;
  skills: string[] | null;
  locations: string[] | null;
  remote: boolean;
  openings: number;
  status: JobStatus;
  published_at: string | null;
  experience_min_years: number;
  experience_max_years: number | null;
  description: string;
  current_mock: { id: number; version: number; status: string } | null;
  created_at: string;
};

export type ApplicationStage =
  | "applied" | "graded" | "shortlisted" | "l1" | "l2"
  | "human_round" | "offer" | "hired" | "rejected" | "withdrawn";

export type JdDraft = {
  description: string;
  skills: string[];
  role_family: string | null;
  responsibilities: string[];
  must_haves: string[];
  source: string;
};

export type RoleTaxonomy = {
  families: { key: string; label: string; sector: string; titles: string[]; core: string[]; optional: string[] }[];
  titles: { title: string; family: string; sector: string }[];
  competencies: { key: string; label: string; hint: string }[];
  question_formats: { key: string; label: string; hint: string }[];
  suggested: { core: string[]; optional: string[] } | null;
};

export type MockDesignData = {
  design: {
    focus_skills: string[];
    competency_weights: Record<string, number>;
    format_mix: Record<string, number>;
    question_count: number | null;
    notes: string | null;
  };
  normalised: { competencies: Record<string, number>; formats: Record<string, number> };
  competencies: { key: string; label: string; hint: string }[];
  question_formats: { key: string; label: string; hint: string }[];
  selectable_skills: string[];
  has_mock: boolean;
};

export type ApplicationRow = {
  id: number;
  stage: ApplicationStage;
  mock_score: number | null;
  is_graded: boolean;
  rejection_reason: string | null;
  candidate?: { id: number; name: string; email: string | null; phone: string | null };
  evidence?: { mock_interview_id: number; overall_score: number | null; scorecard: Record<string, unknown> | null } | null;
  timeline?: { from: string | null; to: string; actor_type: string; note: string | null; occurred_at: string }[];
  applied_at: string;
};

/**
 * The full candidate view behind one application (PRD-E F17).
 *
 * `verification` carries outcomes only — the API never returns the document,
 * the provider reference, or the evidence payload, so there is nothing here
 * to accidentally render.
 */
export type CandidateProfileData = {
  application: {
    id: number;
    stage: ApplicationStage;
    mock_score: number | null;
    mock_attempts: number;
    applied_at: string | null;
    graded_at: string | null;
  };
  candidate: {
    id: number | null;
    name: string | null;
    email: string | null;
    phone: string | null;
    contact_visible: boolean;
  };
  cv: {
    summary: string | null;
    skills: string[];
    experience: Record<string, unknown>[];
    education: Record<string, unknown>[];
  } | null;
  verification: {
    badge: boolean;
    checks: {
      kind: string;
      label: string;
      status: string;
      status_label: string;
      verified_at: string | null;
    }[];
  };
  interview: {
    overall: number | null;
    graded_by: string | null;
    competencies: { name: string; score: number | null }[];
    strengths: string[];
    concerns: string[];
    session: {
      mode: string | null;
      duration_seconds: number | null;
      status: string | null;
      completed_at: string | null;
      proctoring_captured: boolean;
    };
  } | null;
};

export type InterviewRow = {
  id: number;
  round: "l1" | "l2";
  status: string;
  overall_score: number | null;
  dimension_scores: Record<string, number> | null;
  grading_summary: string | null;
  grading_delayed: boolean;
  question_set: { id: number; text: string }[];
  answers: { question_id: number; answer: string }[] | null;
  submitted_at: string | null;
  graded_at: string | null;
};

export type JdMockData = {
  id: number;
  version: number;
  status: string;
  source: string | null;
  questions: { id: number; text: string; skill: string; type: string; weight: number }[] | null;
  rubric: { dimensions: { key: string; label: string; weight: number; criteria: string }[] } | null;
};

export type AutomationRule = {
  id: number;
  trigger: "application_graded" | "interview_graded";
  round: "l1" | "l2" | null;
  min_score: number;
  action: "advance" | "park";
  target_stage: string | null;
  enabled: boolean;
  runs_count?: number;
  would_match?: number;
};

export type MemberRow = {
  id: number;
  role: EmployerRole;
  joined_at: string;
  user?: { id: number; name: string; email: string };
};

const base = "/api/v1/employer";

export const employerApi = {
  workspaces: () => apiJson<{ data: Workspace[] }>(`${base}/workspaces`),
  createWorkspace: (body: { name: string; industry?: string; company_size?: string }) =>
    apiJson<{ data: Workspace }>(`${base}/workspaces`, { method: "POST", body: JSON.stringify(body) }),
  dashboard: (ws: number) => apiJson<{ data: DashboardData }>(`${base}/workspaces/${ws}/dashboard`),

  jobs: (ws: number, status?: string) =>
    apiJson<{ data: EmployerJobRow[] }>(`${base}/workspaces/${ws}/jobs${status ? `?status=${status}` : ""}`),
  job: (ws: number, id: number) => apiJson<{ data: EmployerJobRow }>(`${base}/workspaces/${ws}/jobs/${id}`),
  createJob: (ws: number, body: Record<string, unknown>) =>
    apiJson<{ data: EmployerJobRow }>(`${base}/workspaces/${ws}/jobs`, { method: "POST", body: JSON.stringify(body) }),
  publishJob: (ws: number, id: number) =>
    apiJson<{ data: EmployerJobRow }>(`${base}/workspaces/${ws}/jobs/${id}/publish`, { method: "POST" }),
  changeJobStatus: (ws: number, id: number, status: "paused" | "closed") =>
    apiJson<{ data: EmployerJobRow }>(`${base}/workspaces/${ws}/jobs/${id}/status`, {
      method: "POST",
      body: JSON.stringify({ status }),
    }),

  applications: (ws: number, job: number) =>
    apiJson<{ data: ApplicationRow[] }>(`${base}/workspaces/${ws}/jobs/${job}/applications`),
  application: (ws: number, job: number, id: number) =>
    apiJson<{ data: ApplicationRow }>(`${base}/workspaces/${ws}/jobs/${job}/applications/${id}`),
  candidateProfile: (ws: number, job: number, id: number) =>
    apiJson<{ data: CandidateProfileData }>(
      `${base}/workspaces/${ws}/jobs/${job}/applications/${id}/profile`,
    ),
  draftJd: (ws: number, body: { title: string; notes?: string; experience_min_years?: number; experience_max_years?: number; locations?: string[] }) =>
    apiJson<{ data: JdDraft }>(`${base}/workspaces/${ws}/jd-draft`, {
      method: "POST",
      body: JSON.stringify(body),
    }),
  roleTaxonomy: (title?: string) =>
    apiJson<{ data: RoleTaxonomy }>(
      `${base}/role-taxonomy${title ? `?title=${encodeURIComponent(title)}` : ""}`,
    ),
  mockDesign: (ws: number, job: number) =>
    apiJson<{ data: MockDesignData }>(`${base}/workspaces/${ws}/jobs/${job}/mock/design`),
  saveMockDesign: (ws: number, job: number, body: Record<string, unknown>) =>
    apiJson<{ data: { normalised: MockDesignData["normalised"]; regenerate_required: boolean } }>(
      `${base}/workspaces/${ws}/jobs/${job}/mock/design`,
      { method: "PUT", body: JSON.stringify(body) },
    ),
  moveStage: (ws: number, job: number, id: number, stage: ApplicationStage, note?: string) =>
    apiJson<{ data: ApplicationRow }>(`${base}/workspaces/${ws}/jobs/${job}/applications/${id}/stage`, {
      method: "POST",
      body: JSON.stringify({ stage, note }),
    }),
  interviews: (ws: number, job: number, id: number) =>
    apiJson<{ data: InterviewRow[] }>(`${base}/workspaces/${ws}/jobs/${job}/applications/${id}/interviews`),

  mock: (ws: number, job: number) => apiJson<{ data: JdMockData }>(`${base}/workspaces/${ws}/jobs/${job}/mock`),
  regenerateMock: (ws: number, job: number) =>
    apiJson<{ data: JdMockData }>(`${base}/workspaces/${ws}/jobs/${job}/mock/regenerate`, { method: "POST" }),

  rules: (ws: number, job: number) =>
    apiJson<{ data: AutomationRule[] }>(`${base}/workspaces/${ws}/jobs/${job}/automation-rules`),
  createRule: (ws: number, job: number, body: Record<string, unknown>) =>
    apiJson<{ data: AutomationRule }>(`${base}/workspaces/${ws}/jobs/${job}/automation-rules`, {
      method: "POST",
      body: JSON.stringify(body),
    }),
  toggleRule: (ws: number, job: number, rule: number) =>
    apiJson<{ data: AutomationRule }>(`${base}/workspaces/${ws}/jobs/${job}/automation-rules/${rule}/toggle`, {
      method: "POST",
    }),

  talentPool: (ws: number, job: number) =>
    apiJson<{ data: TalentPoolCandidate[] }>(`${base}/workspaces/${ws}/jobs/${job}/talent-pool`),
  inviteStudent: (ws: number, job: number, candidate: number) =>
    apiJson(`${base}/workspaces/${ws}/jobs/${job}/talent-pool/${candidate}/invite`, { method: "POST" }),

  members: (ws: number) => apiJson<{ data: MemberRow[] }>(`${base}/workspaces/${ws}/members`),
  invite: (ws: number, email: string, role: EmployerRole) =>
    apiJson(`${base}/workspaces/${ws}/invites`, { method: "POST", body: JSON.stringify({ email, role }) }),
};

/** Forward order for pipeline rendering. */
export const STAGE_ORDER: ApplicationStage[] = [
  "applied", "graded", "shortlisted", "l1", "l2", "human_round", "offer", "hired",
];

export const STAGE_LABELS: Record<ApplicationStage, string> = {
  applied: "Applied",
  graded: "Graded",
  shortlisted: "Shortlisted",
  l1: "L1",
  l2: "L2",
  human_round: "Human round",
  offer: "Offer",
  hired: "Hired",
  rejected: "Rejected",
  withdrawn: "Withdrawn",
};

/**
 * The next forward stage a recruiter advances to by hand.
 *
 * `graded` is skipped: it is assigned by the system when a candidate's JD
 * mock is scored, never chosen by a recruiter — so an ungraded applicant
 * advances straight to Shortlisted.
 */
export function nextStage(stage: ApplicationStage): ApplicationStage | null {
  const idx = STAGE_ORDER.indexOf(stage);
  if (idx < 0 || idx >= STAGE_ORDER.length - 1) return null;
  const next = STAGE_ORDER[idx + 1];
  return next === "graded" ? "shortlisted" : next;
}
