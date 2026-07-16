import { ApiError } from "@/lib/api";

/** AI Tutor citation → a course/lab link or a plain source label. */
export type Citation = { label: string; href: string | null };

export type TutorMessage = {
  id: number;
  role: "student" | "tutor";
  body: string;
  confidence: "high" | "medium" | "low" | null;
  escalated: boolean;
  ticket_id: number | null;
  citations: Citation[];
  created_at: string | null;
};

export type TutorConversation = {
  id: number;
  title: string | null;
  lesson_id: number | null;
  last_message_at: string | null;
  message_count?: number;
  messages?: TutorMessage[];
};

/** The tutor endpoints return a 429 with this code when the daily budget is spent. */
export function isBudgetError(err: unknown): boolean {
  return (
    err instanceof ApiError &&
    err.status === 429 &&
    (err.body as { error?: { code?: string } })?.error?.code === "ai_budget_exceeded"
  );
}
