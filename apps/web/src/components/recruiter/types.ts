/**
 * Hiring-floor contract. The demo adapter and a future API client both
 * return this shape. Views never import sample rows directly.
 */

export const FLOOR_STAGES = [
  { id: "job", label: "Job", soon: false },
  { id: "sourcing", label: "Sourcing", soon: false },
  { id: "calls", label: "AI calls", soon: true },
  { id: "ai", label: "AI interview", soon: false },
  { id: "l1", label: "L1", soon: false },
  { id: "l2", label: "L2", soon: false },
  { id: "human", label: "Human", soon: false },
  { id: "bgv", label: "BGV", soon: true },
  { id: "offer", label: "Offer", soon: true },
  { id: "joining", label: "Joining", soon: true },
] as const;

export type FloorStageId = (typeof FLOOR_STAGES)[number]["id"];

export type AgentState = "idle" | "working" | "thinking" | "approval" | "error";

export type BgvStatus = "pending" | "in_progress" | "verified" | "flagged";

export type Interest = "interested" | "not_interested" | "no_answer" | "unknown";

export type CandidateSource = "BrowseJobs pool" | "Client file" | "Email";

export type JobBrief = {
  title: string;
  city: string;
  openings: number;
  experience: string;
  notice: string;
  raw: string;
};

export type StageCount = {
  id: FloorStageId;
  label: string;
  count: number;
  comingSoon: boolean;
};

export type FloorCandidate = {
  id: string;
  name: string;
  source: CandidateSource;
  city: string;
  notice: string;
  match: number;
  interest: Interest;
  bgv: BgvStatus;
  stage: FloorStageId;
  scores: { ai: number | null; l1: number | null; l2: number | null };
  dropoutRisk: number | null;
  timeline: { label: string }[];
  fictional: true;
};

export type FloorCall = {
  id: string;
  candidateId: string;
  name: string;
  when: string;
  duration: string;
  outcome: string;
  snippet: string;
};

export type FloorApproval = {
  id: "outreach" | "offer";
  title: string;
  detail: string;
};

export type FloorActivity = {
  id: string;
  time: string;
  agent: string;
  state: AgentState;
  text: string;
};

export type FloorAgent = {
  id: string;
  name: string;
  stage: FloorStageId;
  state: AgentState;
  task: string;
  /** 0–100. Drawn on the hologram. Derived from the demo clock, not a live meter. */
  progress: number;
};

/** Counts taken from the candidates and calls already on the floor. */
export type FloorMetrics = {
  sourced: number;
  ranked: number;
  callsMade: number;
  connected: number;
  interested: number;
  notInterested: number;
  noAnswer: number;
  avgCallDuration: string;
  interviewsTaken: number;
  interviewsCleared: number;
  l1Cleared: number;
  l2Cleared: number;
  humanBooked: number;
  bgvVerified: number;
  bgvPending: number;
  bgvFlagged: number;
  offersWaiting: number;
  /** Always 0 in the demo. A person has not released a letter. */
  offersReleased: number;
  /** Always 0 in the demo. Nothing is emailed or accepted. */
  offersAccepted: number;
  joined: number;
  dropoutAlerts: number;
  elapsedLabel: string;
  targetLabel: string;
};

export type HiringFloorSnapshot = {
  /** "demo" until a workspace API is wired. Callers branch on this, not on copy. */
  source: "demo";
  label: "Demo data";
  /** Calls, pre-BGV, offers, and joining chats are not a live product yet. */
  comingSoon: readonly string[];
  company: string;
  job: JobBrief;
  autonomous: boolean;
  stages: StageCount[];
  candidates: FloorCandidate[];
  calls: FloorCall[];
  approvals: FloorApproval[];
  activity: FloorActivity[];
  agents: FloorAgent[];
  metrics: FloorMetrics;
  activeStage: FloorStageId;
  elapsedMs: number;
  loopMs: number;
};

export type HiringFloorQuery = {
  elapsedMs: number;
  autonomous: boolean;
  brief: JobBrief | null;
};
