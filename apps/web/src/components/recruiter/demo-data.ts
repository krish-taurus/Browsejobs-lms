/**
 * Fictional cast for the hiring-floor demo. Every name is prefixed "Sample".
 * Nothing here is a customer, a candidate, or a result.
 */

import type { BgvStatus, CandidateSource, FloorStageId, Interest } from "./types";

export const LOOP_MS = 72_000;

/** Rich frame used by tests (`?at=52`) and the first paint of the full floor. */
export const SHOWCASE_MS = 52_000;

export const DEFAULT_BRIEF = {
  title: "React developers",
  city: "Bengaluru",
  openings: 3,
  experience: "2–4 yrs",
  notice: "Under 30 days",
  raw: "Hire 3 React developers in Bangalore, 2-4 yrs, notice under 30 days",
} as const;

export const SAMPLE_PROMPTS = [
  "Hire 3 React developers in Bangalore, 2-4 yrs, notice under 30 days",
  "Hire 2 backend engineers in Hyderabad, 3-5 yrs, notice 15 days",
  "Hire 1 data analyst, remote, any notice",
] as const;

type Step = { stage: FloorStageId; at: number; label: string };

export type DemoPerson = {
  id: string;
  name: string;
  source: CandidateSource;
  city: string;
  notice: string;
  match: number;
  interest: Interest;
  bgv: BgvStatus;
  scores: { ai: number | null; l1: number | null; l2: number | null };
  dropoutRisk: number | null;
  steps: readonly Step[];
};

export const DEMO_PEOPLE: readonly DemoPerson[] = [
  {
    id: "asha",
    name: "Sample Asha Iyer",
    source: "BrowseJobs pool",
    city: "Bengaluru",
    notice: "30 days",
    match: 91,
    interest: "interested",
    bgv: "verified",
    scores: { ai: 86, l1: 82, l2: 84 },
    dropoutRisk: null,
    steps: [
      { stage: "sourcing", at: 4_000, label: "Matched from the BrowseJobs pool" },
      { stage: "calls", at: 12_000, label: "AI call · interested · notice 30 days" },
      { stage: "ai", at: 18_000, label: "AI interview · 86" },
      { stage: "l1", at: 24_000, label: "L1 · 82" },
      { stage: "l2", at: 30_000, label: "L2 · 84" },
      { stage: "human", at: 36_000, label: "Human round booked · Tue 11:00 · sample Zoom link" },
      { stage: "bgv", at: 42_000, label: "Pre-BGV · EPFO matched · DigiLocker verified" },
      { stage: "offer", at: 48_000, label: "Offer drafted · waiting for a person to release it" },
    ],
  },
  {
    id: "rohan",
    name: "Sample Rohan Mehta",
    source: "Client file",
    city: "Bengaluru",
    notice: "20 days",
    match: 88,
    interest: "interested",
    bgv: "verified",
    scores: { ai: 80, l1: 78, l2: 76 },
    dropoutRisk: 74,
    steps: [
      { stage: "sourcing", at: 4_500, label: "Matched from the client file" },
      { stage: "calls", at: 13_000, label: "AI call · interested · notice 20 days" },
      { stage: "ai", at: 19_000, label: "AI interview · 80" },
      { stage: "l1", at: 25_000, label: "L1 · 78" },
      { stage: "l2", at: 31_000, label: "L2 · 76" },
      { stage: "human", at: 37_000, label: "Human round cleared" },
      { stage: "bgv", at: 43_000, label: "Pre-BGV verified" },
      { stage: "offer", at: 49_000, label: "Sample offer marked accepted" },
      { stage: "joining", at: 50_000, label: "Joining chat went quiet · dropout risk 74" },
    ],
  },
  {
    id: "meera",
    name: "Sample Meera Kapoor",
    source: "Email",
    city: "Bengaluru",
    notice: "15 days",
    match: 84,
    interest: "interested",
    bgv: "in_progress",
    scores: { ai: 81, l1: 79, l2: 77 },
    dropoutRisk: null,
    steps: [
      { stage: "sourcing", at: 5_000, label: "Matched from a forwarded email" },
      { stage: "calls", at: 14_000, label: "AI call · interested · notice 15 days" },
      { stage: "ai", at: 20_000, label: "AI interview · 81" },
      { stage: "l1", at: 26_000, label: "L1 · 79" },
      { stage: "l2", at: 32_000, label: "L2 · 77" },
      { stage: "bgv", at: 42_500, label: "Pre-BGV in progress · EPFO check running" },
    ],
  },
  {
    id: "isha",
    name: "Sample Isha Qureshi",
    source: "BrowseJobs pool",
    city: "Bengaluru",
    notice: "30 days",
    match: 83,
    interest: "interested",
    bgv: "flagged",
    scores: { ai: 79, l1: 76, l2: 74 },
    dropoutRisk: null,
    steps: [
      { stage: "sourcing", at: 5_500, label: "Matched from the BrowseJobs pool" },
      { stage: "calls", at: 14_500, label: "AI call · interested · notice 30 days" },
      { stage: "ai", at: 20_500, label: "AI interview · 79" },
      { stage: "l1", at: 26_500, label: "L1 · 76" },
      { stage: "l2", at: 32_500, label: "L2 · 74" },
      { stage: "bgv", at: 43_500, label: "Pre-BGV flagged · employment dates do not match the CV" },
    ],
  },
  {
    id: "vikram",
    name: "Sample Vikram Desai",
    source: "BrowseJobs pool",
    city: "Pune",
    notice: "45 days",
    match: 80,
    interest: "interested",
    bgv: "pending",
    scores: { ai: 77, l1: 74, l2: 73 },
    dropoutRisk: null,
    steps: [
      { stage: "sourcing", at: 6_000, label: "Matched from the BrowseJobs pool" },
      { stage: "calls", at: 15_000, label: "AI call · interested · notice 45 days" },
      { stage: "ai", at: 21_000, label: "AI interview · 77" },
      { stage: "l1", at: 27_000, label: "L1 · 74" },
      { stage: "l2", at: 33_000, label: "L2 · 73" },
      { stage: "human", at: 39_000, label: "Human round · slot offered, not confirmed" },
    ],
  },
  {
    id: "neha",
    name: "Sample Neha Rao",
    source: "Client file",
    city: "Hyderabad",
    notice: "30 days",
    match: 78,
    interest: "interested",
    bgv: "pending",
    scores: { ai: 75, l1: 72, l2: 71 },
    dropoutRisk: null,
    steps: [
      { stage: "sourcing", at: 6_500, label: "Matched from the client file" },
      { stage: "calls", at: 15_500, label: "AI call · interested · notice 30 days" },
      { stage: "ai", at: 21_500, label: "AI interview · 75" },
      { stage: "l1", at: 27_500, label: "L1 · 72" },
      { stage: "l2", at: 34_000, label: "L2 in progress" },
    ],
  },
  {
    id: "arjun",
    name: "Sample Arjun Shah",
    source: "BrowseJobs pool",
    city: "Mumbai",
    notice: "Immediate",
    match: 76,
    interest: "interested",
    bgv: "pending",
    scores: { ai: 74, l1: 70, l2: null },
    dropoutRisk: null,
    steps: [
      { stage: "sourcing", at: 7_000, label: "Matched from the BrowseJobs pool" },
      { stage: "calls", at: 16_000, label: "AI call · interested · can join immediately" },
      { stage: "ai", at: 22_000, label: "AI interview · 74" },
      { stage: "l1", at: 28_000, label: "L1 in progress" },
    ],
  },
  {
    id: "priya",
    name: "Sample Priya Nair",
    source: "Email",
    city: "Bengaluru",
    notice: "30 days",
    match: 74,
    interest: "interested",
    bgv: "pending",
    scores: { ai: 68, l1: null, l2: null },
    dropoutRisk: null,
    steps: [
      { stage: "sourcing", at: 7_500, label: "Matched from a forwarded email" },
      { stage: "calls", at: 16_500, label: "AI call · interested · notice 30 days" },
      { stage: "ai", at: 22_500, label: "AI interview in progress" },
    ],
  },
  {
    id: "kabir",
    name: "Sample Kabir Sen",
    source: "BrowseJobs pool",
    city: "Kolkata",
    notice: "60 days",
    match: 71,
    interest: "interested",
    bgv: "pending",
    scores: { ai: 64, l1: null, l2: null },
    dropoutRisk: null,
    steps: [
      { stage: "sourcing", at: 8_000, label: "Matched from the BrowseJobs pool" },
      { stage: "calls", at: 17_000, label: "AI call · interested · notice 60 days" },
      { stage: "ai", at: 23_000, label: "AI interview in progress" },
    ],
  },
  {
    id: "lata",
    name: "Sample Lata Menon",
    source: "Client file",
    city: "Chennai",
    notice: "30 days",
    match: 69,
    interest: "no_answer",
    bgv: "pending",
    scores: { ai: null, l1: null, l2: null },
    dropoutRisk: null,
    steps: [
      { stage: "sourcing", at: 8_500, label: "Matched from the client file" },
      { stage: "calls", at: 17_500, label: "AI call · no answer · one retry left" },
    ],
  },
  {
    id: "nia",
    name: "Sample Nia Reddy",
    source: "BrowseJobs pool",
    city: "Bengaluru",
    notice: "Immediate",
    match: 66,
    interest: "not_interested",
    bgv: "pending",
    scores: { ai: null, l1: null, l2: null },
    dropoutRisk: null,
    steps: [
      { stage: "sourcing", at: 9_000, label: "Matched from the BrowseJobs pool" },
      { stage: "calls", at: 18_000, label: "AI call · not interested · staying in the current role" },
    ],
  },
  {
    id: "dev",
    name: "Sample Dev Kulkarni",
    source: "Email",
    city: "Pune",
    notice: "90 days",
    match: 62,
    interest: "unknown",
    bgv: "pending",
    scores: { ai: null, l1: null, l2: null },
    dropoutRisk: null,
    steps: [{ stage: "sourcing", at: 9_500, label: "Still being ranked · notice 90 days" }],
  },
];

export type DemoCall = {
  id: string;
  candidateId: string;
  name: string;
  when: string;
  duration: string;
  outcome: string;
  snippet: string;
  at: number;
};

export const DEMO_CALLS: readonly DemoCall[] = [
  {
    id: "c-asha",
    candidateId: "asha",
    name: "Sample Asha Iyer",
    when: "Day 1 · 10:02",
    duration: "2:04",
    outcome: "Interested",
    snippet: "Open to Bengaluru. Notice is 30 days. Will sit the AI interview this week.",
    at: 12_400,
  },
  {
    id: "c-rohan",
    candidateId: "rohan",
    name: "Sample Rohan Mehta",
    when: "Day 1 · 10:06",
    duration: "2:11",
    outcome: "Interested",
    snippet: "Came from the client file. Notice is 20 days. Asked who would call next.",
    at: 13_400,
  },
  {
    id: "c-meera",
    candidateId: "meera",
    name: "Sample Meera Kapoor",
    when: "Day 1 · 10:11",
    duration: "1:48",
    outcome: "Interested",
    snippet: "Forwarded by email. Notice is 15 days. Happy to do the AI interview first.",
    at: 14_400,
  },
  {
    id: "c-isha",
    candidateId: "isha",
    name: "Sample Isha Qureshi",
    when: "Day 1 · 10:14",
    duration: "1:36",
    outcome: "Interested",
    snippet: "Notice is 30 days. Mentioned a gap that the CV does not explain.",
    at: 14_900,
  },
  {
    id: "c-lata",
    candidateId: "lata",
    name: "Sample Lata Menon",
    when: "Day 1 · 10:22",
    duration: "0:18",
    outcome: "No answer",
    snippet: "Rang out. The demo would retry once on the next morning.",
    at: 17_800,
  },
  {
    id: "c-nia",
    candidateId: "nia",
    name: "Sample Nia Reddy",
    when: "Day 1 · 10:24",
    duration: "0:41",
    outcome: "Not interested",
    snippet: "Said she is staying at her current company. No follow-up.",
    at: 18_200,
  },
];

export type DemoActivity = {
  id: string;
  at: number;
  time: string;
  agent: string;
  state: "idle" | "working" | "thinking" | "approval" | "error";
  text: string;
};

export const DEMO_ACTIVITY: readonly DemoActivity[] = [
  { id: "a1", at: 1_200, time: "0:01", agent: "Recruiter", state: "thinking", text: "Reading the role, the city, and the notice rule." },
  { id: "a2", at: 4_000, time: "0:04", agent: "Sourcing", state: "working", text: "Scoring the BrowseJobs pool and the client file together." },
  { id: "a3", at: 10_000, time: "0:10", agent: "Sourcing", state: "idle", text: "12 sample profiles ranked. One person is still in scoring." },
  { id: "a4", at: 12_000, time: "0:12", agent: "Caller", state: "approval", text: "Outreach is waiting for a yes, unless autonomous is on." },
  { id: "a5", at: 18_000, time: "0:18", agent: "Interviewer", state: "working", text: "Candidates sit the AI interview before L1." },
  { id: "a6", at: 28_000, time: "0:28", agent: "Interviewer", state: "working", text: "L1 is open for the people who cleared the AI interview." },
  { id: "a7", at: 36_000, time: "0:36", agent: "Scheduler", state: "working", text: "Human round is optional. A sample slot is Tue 11:00." },
  { id: "a8", at: 42_000, time: "0:42", agent: "BGV", state: "working", text: "Sample pre-BGV is on the desk." },
  { id: "a9", at: 48_000, time: "0:48", agent: "Offer", state: "approval", text: "Sample offer for Sample Asha Iyer needs a person." },
  { id: "a10", at: 50_500, time: "0:50", agent: "Joining", state: "error", text: "Sample Rohan Mehta went quiet. Dropout risk 74. Demo only." },
];

const CITIES: Record<string, string> = {
  bangalore: "Bengaluru",
  bengaluru: "Bengaluru",
  hyderabad: "Hyderabad",
  pune: "Pune",
  chennai: "Chennai",
  mumbai: "Mumbai",
  delhi: "Delhi",
  noida: "Noida",
  gurugram: "Gurugram",
  kolkata: "Kolkata",
  remote: "Remote",
};

/**
 * Reads a typed or spoken hiring line into a brief. Returns null when the
 * line does not name a role, so the floor does not invent one.
 */
export function parseHiringPrompt(raw: string): {
  title: string;
  city: string;
  openings: number;
  experience: string;
  notice: string;
  raw: string;
} | null {
  const text = raw.replace(/\s+/g, " ").trim();
  if (text.length < 8) return null;

  const openingsMatch = text.match(/\bhire\s+(\d{1,2})\b/i);
  const openings = openingsMatch ? Math.min(20, Math.max(1, Number(openingsMatch[1]))) : 1;

  const lower = text.toLowerCase();
  const cityKey = Object.keys(CITIES).find((city) => lower.includes(city));
  const city = cityKey ? CITIES[cityKey] : "Bengaluru";

  const exp = text.match(/(\d{1,2})\s*[-–to]+\s*(\d{1,2})\s*(?:yrs|years|yr)\b/i);
  const experience = exp ? `${exp[1]}–${exp[2]} yrs` : "Not specified";

  const under = text.match(/under\s+(\d{1,3})\s*days/i);
  const days = text.match(/(\d{1,3})\s*days/i);
  const notice = /any notice/i.test(text) ? "Any" : under ? `Under ${under[1]} days` : days ? `${days[1]} days` : "Not specified";

  const titleMatch = text.match(/\bhire\s+(?:\d{1,2}\s+)?(.+?)(?:\s+in\s+|\s*,|\s+notice\b|\s+\d{1,2}\s*[-–]|$)/i);
  let title = (titleMatch?.[1] ?? "")
    .replace(/\b(a|an|the)\b/gi, "")
    .replace(/\s+/g, " ")
    .trim();
  if (title.length < 3) {
    if (/react/i.test(text)) title = "React developers";
    else if (/backend/i.test(text)) title = "Backend engineers";
    else if (/data/i.test(text)) title = "Data analysts";
    else return null;
  }

  return { title, city, openings, experience, notice, raw: text };
}
