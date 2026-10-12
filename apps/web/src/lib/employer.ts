/**
 * Employer portal API client (PRD-E). Thin typed wrappers over apiJson —
 * one module per domain, matching src/lib conventions.
 */
import { apiJson, apiPostBlob } from "@/lib/api";

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
  /** Their score on the general AI Readiness Interview, if they've taken it — null otherwise. Boosts match_score; not required to appear here. */
  cv_mock_score: number | null;
  /** Label only for now — nothing behind it checks a real record yet. */
  bgb_verified: boolean;
  /** True only for a synthetic row from the opt-in "preview with sample data" toggle — never a real candidate. */
  is_sample: boolean;
  /** Present only on a sample row — a real candidate's contact details stay withheld until Shortlisted. */
  email?: string;
  phone?: string;
  location?: string;
  education?: string;
  cv_summary?: string;
};

/** Where the shortlist agent looks: BrowseJobs students, the client's own database, their Naukri export. */
export type ShortlistSource = "browsejobs" | "client_db" | "naukri";

export type ShortlistSettings = {
  top_n: number;
  min_score: number;
  sources: ShortlistSource[];
  auto_on_publish: boolean;
  /** Off: students without the AI Readiness Interview still surface (ranked lower). */
  students_need_readiness: boolean;
  /** On: CV match and AI interview must both clear first_bar (picked first), then second_bar. */
  require_interview: boolean;
  first_bar: number;
  second_bar: number;
  /** On: everyone shortlisted gets the WhatsApp interview invite straight away. */
  auto_invite: boolean;
  /** Time of day (India, "HH:MM") of every L1/L2 slot offered on WhatsApp. */
  interview_time: string;
  /** Score that clears L1 (→ L2 invite) and L2 (→ result to the client). */
  round_pass_mark: number;
};

export type ShortlistEntry = {
  id: number;
  rank: number;
  source: ShortlistSource;
  match_score: number;
  status: "shortlisted" | "removed";
  reasons: {
    /** CV match (share of the JD's skills on the CV). */
    cv_match?: number;
    /** Best AI interview for this JD or its role; null = none yet. */
    interview_score?: number | null;
    /** 1 = cleared the first bar, 2 = the second. */
    tier?: 1 | 2;
    skill_match_pct: number;
    matched_skills: string[];
    missing_skills: string[];
    /** Students only. */
    readiness_index?: number;
    mock_average?: number;
    ai_readiness?: number | null;
    training?: { course: string; status: string } | null;
    /** Client database / Naukri only. */
    experience?: string;
    location?: string;
    role_fit?: boolean;
  };
  candidate_user_id: number | null;
  employer_candidate_id: number | null;
  name: string;
  current_title: string | null;
  current_company: string | null;
  location: string | null;
  experience_years: number | null;
  notice_period: string | null;
  expected_ctc: string | null;
  /** Only for the client's own candidates — a student's contact stays withheld. */
  email: string | null;
  phone: string | null;
  removed_at: string | null;
  /** The message agent's WhatsApp invite for the first interview round, once sent. */
  invite?: { round?: string; status: string; day: string | null; on_zoom?: boolean; error: string | null } | null;
};

export type ShortlistRun = {
  id: number;
  trigger: "publish" | "manual" | "screening";
  by: string | null;
  considered: Partial<Record<ShortlistSource, { considered: number; matched: number }>>;
  shortlisted_count: number;
  /** CV matches sent the AI screening interview. */
  awaiting_count?: number;
  settings: ShortlistSettings;
  created_at: string | null;
};

export type ShortlistData = {
  /** Hiring step 5: WhatsApp invites to pick an interview day. */
  message_agent: {
    templates: Record<string, string | null>;
    /** True once Meta approved the invite template. */
    ready: boolean;
    /** L1/L2 run as Zoom calls the AI interviewer bot joins (else the BrowseJobs room). */
    zoom?: boolean;
    invites: Record<string, number>;
  };
  invite_result?: { queued: number; skipped: Record<string, number> };
  template_result?: Record<string, string>;
  settings: ShortlistSettings;
  source_counts: { client_db: number; naukri: number };
  last_run: ShortlistRun | null;
  entries: ShortlistEntry[];
  removed: ShortlistEntry[];
  /** CV matches with no AI interview yet, and where their AI screening interview stands. */
  awaiting: ShortlistAwaiting[];
};

export type ShortlistAwaiting = {
  name: string;
  source: ShortlistSource;
  cv_match: number;
  /** null = not sent yet (e.g. waiting for Meta); open = sent; done = finished. */
  invite_status: string | null;
  error: string | null;
};

export type CandidateImport = {
  id: number;
  source: "client_db" | "naukri";
  file_name: string;
  rows_total: number;
  created: number;
  updated: number;
  skipped: number;
  errors: string[];
  imported_by: string | null;
  created_at: string | null;
};

export type CandidateSourcesData = {
  counts: { client_db: number; naukri: number };
  imports: CandidateImport[];
  /** Present on the response to an upload: what that one file did. */
  import?: CandidateImport;
};

/** "Post jobs on WhatsApp": the member's QR and the phones linked to this workspace. */
export type WhatsAppPostingData = {
  /** Null for a hiring manager — only owners/recruiters can post jobs. */
  code: string | null;
  business_number: string | null;
  chat_url: string | null;
  /** Server-rendered SVG of chat_url. */
  qr_svg: string | null;
  links: { id: number; phone: string; profile_name: string | null; acts_as: string | null; state: string; last_message_at: string | null }[];
  messages: { id: number; phone: string; direction: "in" | "out"; body: string; status: string; at: string | null }[];
};

/** The live AI Recruiter floor: the hiring agents and the people in each step. */
export type RecruiterFloorAgent = {
  id: string;
  name: string;
  zone: string;
  state: "idle" | "working" | "thinking" | "approval" | "error";
  task: string;
  progress: number;
  metrics: { label: string; value: number | string | null }[];
  /** False for an agent that is not built or connected yet (BGV, client report). */
  live: boolean;
};

export type RecruiterFloorPerson = {
  id: string;
  name: string;
  source: string;
  zone: string;
  stage: string;
  match: number | null;
  scores: { screen: number | null; l1: number | null; l2: number | null };
  matched_skills: string[];
  missing_skills: string[];
  facts: string[];
  timeline: { label: string; at: string | null }[];
};

export type RecruiterFloorData = {
  company: string;
  jobs: { id: number; title: string; status: string }[];
  job: { id: number; title: string; status: string; where: string; openings: number; experience: string } | null;
  zones: { id: string; label: string; count: number }[];
  agents: RecruiterFloorAgent[];
  people: RecruiterFloorPerson[];
  activity: { id: string; at: string; agent: string; text: string }[];
  kpis: { jobs_live: number; shortlisted: number; interviews: number; cleared: number };
  generated_at: string;
};

export type TalentPoolCandidateCv = {
  summary: string | null;
  skills: string[];
  experience: { title?: string; company?: string; period?: string; bullets?: string[] }[];
  education: { name?: string; detail?: string }[];
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

/** Workspace-wide, independent of whatever status/search filter produced `data` below — see JobController::index(). */
export type EmployerJobCounts = {
  total: number;
  published: number;
  closed: number;
  open_positions: number;
};

export type EmployerJobsPage = {
  data: EmployerJobRow[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
  };
  counts: EmployerJobCounts;
};

export type ApplicationStage =
  | "applied" | "graded" | "shortlisted" | "l1" | "l2"
  | "human_round" | "offer" | "hired" | "rejected" | "withdrawn";

/** What the model understood from a spoken hiring request. */
export type JdIntentRead = {
  title: string;
  experience_min_years: number | null;
  experience_max_years: number | null;
  locations: string[];
  openings: number | null;
  remote: boolean;
};

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
  job_id: number;
  stage: ApplicationStage;
  mock_score: number | null;
  is_graded: boolean;
  rejection_reason: string | null;
  candidate?: { id: number; name: string; email: string | null; phone: string | null };
  evidence?: {
    mock_interview_id: number;
    overall_score: number | null;
    scorecard: Record<string, unknown> | null;
    /** Signed, short-lived (~30 min) — fetch a fresh application if it has expired. */
    recording_url: string | null;
  } | null;
  timeline?: { from: string | null; to: string; actor_type: string; note: string | null; occurred_at: string }[];
  applied_at: string;
};

/**
 * Workspace + job_id + search scope, stage NOT applied — selecting a stage
 * narrows `data` without erasing any other stage's count here. See
 * ApplicationController::indexForWorkspace().
 */
export type EmployerApplicationCounts = {
  total: number;
  scored: number;
  unscored: number;
  hired: number;
  by_stage: Partial<Record<ApplicationStage, number>>;
};

export type EmployerApplicationsPage = {
  data: ApplicationRow[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
  };
  counts: EmployerApplicationCounts;
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

export type JobRound = {
  id: number;
  key: string;
  position: number;
  name: string;
  kind: "ai_interview" | "mcq" | "human";
  /** Who conducts this round when it's a human one — a workspace member id. */
  assigned_member_id: number | null;
  assigned_member_name: string | null;
  focus_skills: string[];
  competency_weights: Record<string, number>;
  format_mix: Record<string, number>;
  /** Exact questions chosen by hand, by their text. Overrides focus_skills for this round when non-empty. */
  selected_questions: string[];
  question_count: number | null;
  notes: string | null;
  window_hours: number;
  dispatch: "manual" | "auto";
  auto_min_score: number | null;
  enabled: boolean;
  /** A round already sat is evidence — it can be switched off, never deleted. */
  has_interviews: boolean;
};

export type InterviewProcessData = {
  data: JobRound[];
  meta: {
    kinds: string[];
    competencies: { key: string; label: string; hint: string }[];
    question_formats: { key: string; label: string; hint: string }[];
    selectable_skills: string[];
    default_window_hours: number;
    has_mock: boolean;
    /** The current JD mock's own question bank, to pick exact questions from. */
    mock_questions: { text: string; skill: string | null; type: string | null }[];
  };
};

export type InterviewRow = {
  id: number;
  /** The round's key. Free-form since employers define their own rounds. */
  round: string;
  round_name?: string | null;
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

/**
 * A person who has been sent a link but has not joined yet. `name` and
 * `whatsapp` are whatever the inviter typed as a label for their own
 * reference — never the invitee's real profile, which is always self-entered
 * on acceptance.
 */
export type InviteRow = {
  id: number;
  email: string;
  name: string | null;
  whatsapp: string | null;
  role: EmployerRole;
  expires_at: string;
  accepted_at: string | null;
  created_at: string;
};

const base = "/api/v1/employer";

export const employerApi = {
  workspaces: () => apiJson<{ data: Workspace[] }>(`${base}/workspaces`),
  createWorkspace: (body: { name: string; industry?: string; company_size?: string }) =>
    apiJson<{ data: Workspace }>(`${base}/workspaces`, { method: "POST", body: JSON.stringify(body) }),
  dashboard: (ws: number) => apiJson<{ data: DashboardData }>(`${base}/workspaces/${ws}/dashboard`),

  /**
   * `status`/`search` narrow `data` (and the pagination in `meta`); `counts`
   * is always workspace-wide regardless of either — see JobController::index().
   * The single-arg call (CommandPalette's jump-to-job index) is unchanged.
   */
  jobs: (ws: number, params?: { status?: string; search?: string; page?: number }) => {
    const qs = new URLSearchParams();
    if (params?.status) qs.set("status", params.status);
    if (params?.search) qs.set("search", params.search);
    if (params?.page) qs.set("page", String(params.page));
    const query = qs.toString();
    return apiJson<EmployerJobsPage>(`${base}/workspaces/${ws}/jobs${query ? `?${query}` : ""}`);
  },
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
  /**
   * Every application in the workspace, across jobs — the Pipeline page's
   * List/Board data source. `job_id`/`search` scope `counts` too; `stage`
   * only narrows `data`. See ApplicationController::indexForWorkspace().
   */
  workspaceApplications: (ws: number, params?: { jobId?: number; stage?: string; search?: string; page?: number }) => {
    const qs = new URLSearchParams();
    if (params?.jobId) qs.set("job_id", String(params.jobId));
    if (params?.stage) qs.set("stage", params.stage);
    if (params?.search) qs.set("search", params.search);
    if (params?.page) qs.set("page", String(params.page));
    const query = qs.toString();
    return apiJson<EmployerApplicationsPage>(`${base}/workspaces/${ws}/applications${query ? `?${query}` : ""}`);
  },
  application: (ws: number, job: number, id: number) =>
    apiJson<{ data: ApplicationRow }>(`${base}/workspaces/${ws}/jobs/${job}/applications/${id}`),
  candidateProfile: (ws: number, job: number, id: number) =>
    apiJson<{ data: CandidateProfileData }>(
      `${base}/workspaces/${ws}/jobs/${job}/applications/${id}/profile`,
    ),
  /**
   * Read a spoken request into fields. The model does this, not a regular
   * expression — "hair Taurus can you post job for Java developer a full stack
   * Java developer for" has to come back as "Full Stack Java Developer".
   */
  readIntent: (ws: number, said: string) =>
    apiJson<{ data: JdIntentRead }>(`${base}/workspaces/${ws}/jd-intent`, {
      method: "POST",
      body: JSON.stringify({ said }),
    }),
  /**
   * Apply one spoken change to a draft. Returns the whole description back,
   * because a fragment could not be merged into the existing text reliably.
   */
  reviseJd: (
    ws: number,
    body: { title: string; description: string; skills: string[]; instruction: string },
  ) =>
    apiJson<{ data: { description: string; skills: string[]; summary: string } }>(
      `${base}/workspaces/${ws}/jd-revise`,
      { method: "POST", body: JSON.stringify(body) },
    ),
  draftJd: (ws: number, body: { title: string; notes?: string; experience_min_years?: number; experience_max_years?: number; locations?: string[] }) =>
    apiJson<{ data: JdDraft }>(`${base}/workspaces/${ws}/jd-draft`, {
      method: "POST",
      body: JSON.stringify(body),
    }),
  /** The hiring console's own voice. Null when TTS is not configured. */
  speak: (ws: number, text: string) => apiPostBlob(`${base}/workspaces/${ws}/speak`, { text }),
  /**
   * Turns a recorded clip into text. MediaRecorder (which every real
   * browser has) records the clip client-side; this just proxies it to
   * server-side speech-to-text, so the console's mic works in Firefox
   * and Safari too, not only the browsers that shipped the Web Speech API.
   */
  transcribe: (ws: number, audio: FormData) =>
    apiJson<{ data: { text: string } }>(`${base}/workspaces/${ws}/transcribe`, { method: "POST", body: audio }),

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
  rounds: (ws: number, job: number) =>
    apiJson<InterviewProcessData>(`${base}/workspaces/${ws}/jobs/${job}/rounds`),
  saveRounds: (ws: number, job: number, rounds: Record<string, unknown>[]) =>
    apiJson<{ data: JobRound[] }>(`${base}/workspaces/${ws}/jobs/${job}/rounds`, {
      method: "PUT",
      body: JSON.stringify({ rounds }),
    }),
  sendRound: (ws: number, job: number, application: number, round: number) =>
    apiJson<{ data: InterviewRow }>(
      `${base}/workspaces/${ws}/jobs/${job}/applications/${application}/rounds/${round}/send`,
      { method: "POST" },
    ),
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

  talentPool: (ws: number, job: number, sample?: boolean) =>
    apiJson<{ data: TalentPoolCandidate[] }>(`${base}/workspaces/${ws}/jobs/${job}/talent-pool${sample ? "?sample=1" : ""}`),
  inviteStudent: (ws: number, job: number, candidate: number) =>
    apiJson(`${base}/workspaces/${ws}/jobs/${job}/talent-pool/${candidate}/invite`, { method: "POST" }),
  shortlistCandidate: (ws: number, job: number, candidate: number) =>
    apiJson(`${base}/workspaces/${ws}/jobs/${job}/talent-pool/${candidate}/shortlist`, { method: "POST" }),
  talentPoolCv: (ws: number, job: number, candidate: number) =>
    apiJson<{ data: TalentPoolCandidateCv }>(`${base}/workspaces/${ws}/jobs/${job}/talent-pool/${candidate}/cv`),
  talentPoolRecording: (ws: number, job: number, candidate: number) =>
    apiJson<{ data: { url: string } }>(`${base}/workspaces/${ws}/jobs/${job}/talent-pool/${candidate}/recording`),

  shortlist: (ws: number, job: number) => apiJson<{ data: ShortlistData }>(`${base}/workspaces/${ws}/jobs/${job}/shortlist`),
  saveShortlistSettings: (ws: number, job: number, body: ShortlistSettings) =>
    apiJson<{ data: ShortlistData }>(`${base}/workspaces/${ws}/jobs/${job}/shortlist/settings`, {
      method: "PUT",
      body: JSON.stringify(body),
    }),
  runShortlist: (ws: number, job: number) =>
    apiJson<{ data: ShortlistData }>(`${base}/workspaces/${ws}/jobs/${job}/shortlist/run`, { method: "POST" }),
  sendInterviewInvites: (ws: number, job: number, entryIds?: number[]) =>
    apiJson<{ data: ShortlistData }>(`${base}/workspaces/${ws}/jobs/${job}/shortlist/invite`, {
      method: "POST",
      body: JSON.stringify(entryIds ? { entry_ids: entryIds } : {}),
    }),
  submitInterviewTemplates: (ws: number, job: number) =>
    apiJson<{ data: ShortlistData }>(`${base}/workspaces/${ws}/jobs/${job}/shortlist/templates`, { method: "POST" }),
  removeShortlistEntry: (ws: number, job: number, entry: number) =>
    apiJson<{ data: ShortlistData }>(`${base}/workspaces/${ws}/jobs/${job}/shortlist/${entry}/remove`, { method: "POST" }),
  restoreShortlistEntry: (ws: number, job: number, entry: number) =>
    apiJson<{ data: ShortlistData }>(`${base}/workspaces/${ws}/jobs/${job}/shortlist/${entry}/restore`, { method: "POST" }),
  candidateSources: (ws: number) => apiJson<{ data: CandidateSourcesData }>(`${base}/workspaces/${ws}/candidate-sources`),
  importCandidates: (ws: number, source: "client_db" | "naukri", file: File) => {
    const form = new FormData();
    form.append("source", source);
    form.append("file", file);
    return apiJson<{ data: CandidateSourcesData }>(`${base}/workspaces/${ws}/candidate-sources`, { method: "POST", body: form });
  },
  clearCandidates: (ws: number, source: "client_db" | "naukri") =>
    apiJson<{ data: CandidateSourcesData }>(`${base}/workspaces/${ws}/candidate-sources/${source}`, { method: "DELETE" }),

  recruiterFloor: (ws: number, job: number | null) =>
    apiJson<{ data: RecruiterFloorData }>(`${base}/workspaces/${ws}/recruiter-floor${job ? `?job=${job}` : ""}`),
  whatsappPosting: (ws: number) => apiJson<{ data: WhatsAppPostingData }>(`${base}/workspaces/${ws}/whatsapp`),
  regenerateWhatsappCode: (ws: number) =>
    apiJson<{ data: WhatsAppPostingData }>(`${base}/workspaces/${ws}/whatsapp/regenerate`, { method: "POST" }),
  unlinkWhatsapp: (ws: number, link: number) =>
    apiJson(`${base}/workspaces/${ws}/whatsapp/links/${link}`, { method: "DELETE" }),

  members: (ws: number) => apiJson<{ data: MemberRow[] }>(`${base}/workspaces/${ws}/members`),
  /** People invited but not yet joined — only visible to an owner. */
  invites: (ws: number) => apiJson<{ data: InviteRow[] }>(`${base}/workspaces/${ws}/invites`),
  invite: (
    ws: number,
    body: { email: string; role: EmployerRole; name?: string; whatsapp?: string },
  ) => apiJson<{ data: InviteRow }>(`${base}/workspaces/${ws}/invites`, { method: "POST", body: JSON.stringify(body) }),
  /** The last owner can never be removed — the API enforces this and reports it. */
  removeMember: (ws: number, memberId: number) =>
    apiJson(`${base}/workspaces/${ws}/members/${memberId}`, { method: "DELETE" }),
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
