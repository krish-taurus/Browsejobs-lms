/**
 * The four AI interview experiences. One database table underneath, but each
 * kind has its own portal URL and its own list (/student-ai-mock/{kind}).
 * The API derives the kind per session — see MockInterview::kind().
 */
export const MOCK_KINDS = ["practice", "voice", "job", "cv"] as const;

export type MockKind = (typeof MOCK_KINDS)[number];

export const MOCK_KIND_META: Record<MockKind, { label: string; title: string; blurb: string }> = {
  practice: {
    label: "Text practice",
    title: "Text practice interviews",
    blurb: "Typed interviews for your course. Every one ends with a scorecard, model answers and three things to fix next.",
  },
  voice: {
    label: "Voice",
    title: "Voice interviews",
    blurb: "Spoken interviews with the AI interviewer — questions read aloud, your answers transcribed as you speak.",
  },
  job: {
    label: "Job interviews",
    title: "Job interviews",
    blurb: "The short AI interview for a role you're applying to. Your score goes to the employer with your CV.",
  },
  cv: {
    label: "CV readiness",
    title: "CV readiness interviews",
    blurb: "The free 15-question interview on your own CV. It decides whether employers can find your profile.",
  },
};

export function isMockKind(value: unknown): value is MockKind {
  return typeof value === "string" && (MOCK_KINDS as readonly string[]).includes(value);
}

/** Canonical URL of one session: /student-ai-mock/{kind}/{id}[/room]. */
export function mockPath(kind: MockKind, id: number | string, room = false): string {
  return `/student-ai-mock/${kind}/${id}${room ? "/room" : ""}`;
}
