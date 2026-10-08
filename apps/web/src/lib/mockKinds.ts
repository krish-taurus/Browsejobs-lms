/**
 * The four AI interview experiences. One database table underneath, but each
 * kind has its own portal URL and its own list. The API derives the kind per
 * session — see MockInterview::kind().
 *
 *   practice → /student-ai-mock/practice/{id}
 *   voice    → /student-ai-mock/voice/{id}
 *   job      → /job-interview/{id}            (hiring directly on BrowseJobs)
 *   cv       → /ai-readiness-interview/{id}   (the free CV interview)
 */
export const MOCK_KINDS = ["practice", "voice", "job", "cv"] as const;

export type MockKind = (typeof MOCK_KINDS)[number];

type KindMeta = {
  label: string; // tab label
  title: string; // list page heading
  blurb: string; // list page intro
  room: string; // what the interview screens call it
  purpose: string; // one line on what this interview is for
};

export const MOCK_KIND_META: Record<MockKind, KindMeta> = {
  practice: {
    label: "Text practice",
    title: "Text practice interviews",
    blurb: "Typed interviews for your course. Every one ends with a scorecard, model answers and three things to fix next.",
    room: "Practice interview",
    purpose: "A practice round for your course — you get a scorecard and three things to fix next.",
  },
  voice: {
    label: "Voice",
    title: "Voice interviews",
    blurb: "Spoken interviews with the AI interviewer — questions read aloud, your answers transcribed as you speak.",
    room: "Voice practice interview",
    purpose: "A spoken practice round — the same scorecard as text practice, out loud.",
  },
  job: {
    label: "Job interviews",
    title: "Job interviews",
    blurb: "The short AI interview for a role you're applying to. Your score goes to the employer with your CV.",
    room: "Job interview",
    purpose: "This is the interview for the role — your score goes to the employer with your CV.",
  },
  cv: {
    label: "AI Readiness",
    title: "AI Readiness Interviews",
    blurb: "The free 15-question interview on your own CV. It decides whether employers can find your profile.",
    room: "AI Readiness Interview",
    purpose: "15 questions built from your own CV. Finishing it is what makes your profile visible to employers.",
  },
};

const BASE: Record<MockKind, string> = {
  practice: "/student-ai-mock/practice",
  voice: "/student-ai-mock/voice",
  job: "/job-interview",
  cv: "/ai-readiness-interview",
};

export function isMockKind(value: unknown): value is MockKind {
  return typeof value === "string" && (MOCK_KINDS as readonly string[]).includes(value);
}

/** The list page for one kind. */
export function mockListPath(kind: MockKind): string {
  return BASE[kind];
}

/** Canonical URL of one session — its kind's base, then /{id}[/room]. */
export function mockPath(kind: MockKind, id: number | string, room = false): string {
  return `${BASE[kind]}/${id}${room ? "/room" : ""}`;
}
