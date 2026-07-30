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

export type DashboardData = {
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

/** The next forward stage a recruiter usually advances to. */
export function nextStage(stage: ApplicationStage): ApplicationStage | null {
  const idx = STAGE_ORDER.indexOf(stage);
  if (idx < 0 || idx >= STAGE_ORDER.length - 1) return null;
  return STAGE_ORDER[idx + 1];
}
