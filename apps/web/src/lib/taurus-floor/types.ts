import type { FloorAgent, FloorStatus, FloorZone } from "./floor";

export type { FloorAgent, FloorLook, FloorStatus, FloorZone } from "./floor";

export type FeedStatus = FloorStatus | "done" | "approved";

export type ConsoleKpi = {
  key: string;
  label: string;
  value: string;
  /** Pulses in the "needs you" colour when true. */
  hot?: boolean;
  /** Tiny caption after the value, e.g. "SAMPLE" or "AS REPORTED". */
  note?: string;
};

export type FeedItem = { id: string; at: string; agent: string; status: FeedStatus; text: string };

export type SideItem = {
  id: string;
  title: string;
  subtitle: string;
  status: FloorStatus;
  progress?: number | null;
  metrics?: { label: string; value: string }[];
};

export type StageCount = { key: string; label: string; count: number; sub?: string };

export type ChatMessage = {
  id: string;
  from: "employer" | "taurus";
  text: string;
  at: string;
  attachment?: { name: string; meta: string };
};

/** A WhatsApp-style conversation shown beside the floor (story demos). */
export type ConsoleChat = {
  title: string;
  messages: ChatMessage[];
  typing: boolean;
  /** Quick replies on offer; empty when the employer isn't being asked anything. */
  replies: string[];
  /** Seconds until the first reply is chosen automatically, so the story also plays hands-free. */
  autoIn?: number;
};

export type ConsoleCaption = { step: number; total: number; title: string; text: string };

export type ConsoleState = {
  agents: FloorAgent[];
  zones: FloorZone[];
  kpis: ConsoleKpi[];
  feed: FeedItem[];
  sideTitle: string;
  side: SideItem[];
  stages?: StageCount[];
  /** True when every number on screen is simulated. */
  sample: boolean;
  chat?: ConsoleChat;
  caption?: ConsoleCaption;
  ended?: boolean;
};

export type FloorEffect =
  | { type: "deliver" | "stream" | "focus"; id: string }
  | { type: "flow"; from: string; to: string; count: number };

export type AskResult = { answer: string; audio?: Blob | null };

export interface ConsoleSource {
  /** Starts pushing state; returns a stop function. */
  start(handlers: {
    state: (s: ConsoleState) => void;
    effects: (e: FloorEffect[]) => void;
    error: (message: string | null) => void;
  }): () => void;
  /** Approve the pending action of an agent; resolves to a confirmation line. */
  approve?(agentId: string): Promise<string>;
  ask?(question: string): Promise<AskResult>;
  /** Choose a quick reply in the chat (story demos). */
  reply?(text: string): void;
  restart?(): void;
}

export const STATUS_LABEL: Record<FeedStatus, string> = {
  working: "Working",
  thinking: "Planning",
  needs: "Needs you",
  error: "Error",
  idle: "Idle",
  offline: "Offline",
  done: "Done",
  approved: "Approved",
};

export const istTime = (iso?: string) =>
  new Date(iso ?? Date.now()).toLocaleTimeString("en-GB", { timeZone: "Asia/Kolkata", hour: "2-digit", minute: "2-digit" });
