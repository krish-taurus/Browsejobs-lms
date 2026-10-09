/**
 * Live data sources for the Taurus console: the founder's agent floor
 * (/api/v1/admin/taurus/*) and an employer's hiring floor
 * (/api/v1/employer/workspaces/{id}/hiring-floor). Both poll; effects
 * (robots walking work to the core, candidates moving stage) are derived from
 * what changed between two polls, so the floor only animates real events.
 */
import { apiJson, apiPostBlob, ApiError } from "@/lib/api";
import type { FloorAgent, FloorStatus, FloorZone } from "./floor";
import type { ConsoleSource, ConsoleState, FeedItem, FeedStatus, FloorEffect } from "./types";
import { HIRING_STAGES } from "./demo";

/* ------------------------------------------------------------------ workspace agent floor */

export type TaurusAgentRow = {
  id: number;
  slug: string;
  name: string;
  zone: string | null;
  role: string | null;
  platform: string | null;
  status: FloorStatus;
  task: string | null;
  progress: number;
  last_seen_at: string | null;
};
export type TaurusTaskRow = {
  id: number;
  agent_id: number;
  agent_name: string;
  ref: string;
  title: string;
  status: "queued" | "running" | "needs_approval" | "done" | "failed" | "rejected";
  progress: number;
  risk: "low" | "high" | null;
  approval_action: string | null;
  decision: "approved" | "rejected" | null;
  created_at: string;
  updated_at: string;
  finished_at: string | null;
};
export type TaurusEventRow = {
  id: number;
  agent_id: number;
  agent_name: string;
  kind: "status" | "task" | "message" | "spend" | "approval";
  status: string | null;
  message: string;
  occurred_at: string;
};
export type TaurusSpendLine = { source: string; currency: string | null; amount: number | null; units: number | null; unit_label: string | null };
export type TaurusState = {
  floor: string;
  generated_at: string;
  agents: TaurusAgentRow[];
  tasks: TaurusTaskRow[];
  events: TaurusEventRow[];
  kpis: { running: number; needs: number; done_today: number; failed_today: number; online: number; agents: number };
  spend: {
    today: TaurusSpendLine[];
    by_agent: { agent_id: number; agent_name: string; currency: string | null; amount: number | null }[];
    days: { date: string; currency: string | null; amount: number | null }[];
  };
};

const FEED_STATUSES = new Set<FeedStatus>(["working", "thinking", "needs", "error", "idle", "offline", "done", "approved"]);

function eventStatus(e: TaurusEventRow): FeedStatus {
  const s = (e.status ?? "") as FeedStatus;
  if (FEED_STATUSES.has(s)) return s;
  if (e.status === "needs_approval") return "needs";
  if (e.status === "failed") return "error";
  if (e.status === "running") return "working";
  if (e.status === "rejected") return "idle";
  if (e.kind === "approval") return "approved";
  return "working";
}

export function formatSpend(lines: TaurusSpendLine[]): string {
  const money = lines.filter((l) => l.amount !== null && l.currency);
  if (!money.length) return "—";
  const byCur = new Map<string, number>();
  money.forEach((l) => byCur.set(l.currency!, (byCur.get(l.currency!) ?? 0) + (l.amount ?? 0)));
  return [...byCur]
    .map(([cur, amt]) => (cur === "USD" ? `$${amt.toFixed(2)}` : cur === "INR" ? `₹${Math.round(amt).toLocaleString("en-IN")}` : `${amt.toFixed(2)} ${cur}`))
    .join(" · ");
}

export function zonesFrom(agents: { zone: string | null }[]): FloorZone[] {
  const seen: string[] = [];
  agents.forEach((a) => {
    const z = a.zone?.trim() || "General";
    if (!seen.includes(z)) seen.push(z);
  });
  return seen.map((z) => ({ key: z, label: z }));
}

/** A member's view of one Taurus workspace. `base` is /api/v1/taurus/workspaces/{id}. */
export function workspaceFloorSource(base: string, floor: "ops" | "recruitment" = "ops", intervalMs = 5000): ConsoleSource & { latest(): TaurusState | null } {
  let latest: TaurusState | null = null;
  const lastEventId = { v: 0 };

  const toState = (s: TaurusState): ConsoleState => {
    const pending = new Map<number, TaurusTaskRow>();
    s.tasks.filter((t) => t.status === "needs_approval").forEach((t) => pending.set(t.agent_id, t));
    const agents: FloorAgent[] = s.agents.map((a) => ({
      id: String(a.id),
      name: a.name,
      zone: a.zone?.trim() || "General",
      status: a.status,
      task: a.task,
      progress: a.progress,
      approval: pending.get(a.id)?.approval_action ?? null,
    }));
    const feed: FeedItem[] = s.events.slice(0, 8).map((e) => ({ id: `e${e.id}`, at: e.occurred_at, agent: e.agent_name, status: eventStatus(e), text: e.message }));
    return {
      agents,
      zones: zonesFrom(s.agents),
      kpis: [
        { key: "running", label: "Running now", value: String(s.kpis.running) },
        { key: "needs", label: "Needs you", value: String(s.kpis.needs), hot: s.kpis.needs > 0 },
        { key: "done", label: "Done today", value: String(s.kpis.done_today) },
        { key: "failed", label: "Failed today", value: String(s.kpis.failed_today) },
        { key: "spend", label: "Spent today", value: formatSpend(s.spend.today), note: "AS REPORTED" },
      ],
      feed,
      sideTitle: "Running now",
      side: s.tasks
        .filter((t) => t.status === "running" || t.status === "needs_approval")
        .slice(0, 5)
        .map((t) => ({
          id: String(t.agent_id),
          title: t.agent_name,
          subtitle: t.status === "needs_approval" ? `Waiting for you: ${t.approval_action ?? t.title}` : t.title,
          status: t.status === "needs_approval" ? "needs" : "working",
          progress: t.status === "running" ? t.progress : null,
        })),
      sample: false,
    };
  };

  const effectsFor = (s: TaurusState): FloorEffect[] => {
    const fresh = s.events.filter((e) => e.id > lastEventId.v);
    const first = lastEventId.v === 0;
    if (s.events.length) lastEventId.v = Math.max(lastEventId.v, ...s.events.map((e) => e.id));
    if (first) return [];
    return fresh
      .reverse()
      .map((e): FloorEffect => {
        const id = String(e.agent_id);
        if (e.status === "done") return { type: "deliver", id };
        if (e.status === "needs_approval" || e.status === "needs") return { type: "focus", id };
        return { type: "stream", id };
      });
  };

  return {
    latest: () => latest,
    start({ state, effects, error }) {
      let stopped = false;
      let timer = 0;
      const poll = async () => {
        try {
          const res = await apiJson<{ data: TaurusState }>(`${base}/state?floor=${floor}`);
          if (stopped) return;
          latest = res.data;
          state(toState(res.data));
          const fx = effectsFor(res.data);
          if (fx.length) effects(fx);
          error(null);
        } catch (e) {
          if (!stopped)
            error(
              e instanceof ApiError && (e.status === 403 || e.status === 404)
                ? e.status === 403
                  ? "This workspace is suspended or you don't have access."
                  : "You don't have access to this workspace."
                : "Can't reach the Taurus API. Retrying…",
            );
        }
        if (!stopped) timer = window.setTimeout(poll, intervalMs);
      };
      void poll();
      return () => {
        stopped = true;
        window.clearTimeout(timer);
      };
    },
    async approve(agentId) {
      const task = latest?.tasks.find((t) => String(t.agent_id) === agentId && t.status === "needs_approval");
      if (!task) return "Nothing is waiting on that agent any more.";
      await apiJson(`${base}/tasks/${task.id}/approve`, { method: "POST", body: JSON.stringify({}) });
      return `Approved. ${task.agent_name} can go ahead: ${task.approval_action ?? task.title}.`;
    },
    async ask(question) {
      try {
        const res = await apiJson<{ data: { answer: string } }>(`${base}/ask`, {
          method: "POST",
          body: JSON.stringify({ question, floor }),
        });
        let audio: Blob | null = null;
        try {
          audio = await apiPostBlob(`${base}/speak`, { text: res.data.answer });
        } catch {
          audio = null;
        }
        return { answer: res.data.answer, audio };
      } catch (e) {
        if (e instanceof ApiError && e.status === 503) return { answer: "My brain isn't connected yet. Add an LLM key in Brain & voice." };
        throw e;
      }
    },
  };
}

/* ------------------------------------------------------------------ employer hiring floor */

export type HiringFloor = {
  kpis: { open_roles: number; in_pipeline: number; interviews_in_flight: number; offers: number; hired: number };
  stages: { key: string; label: string; count: number; avg_score: number | null; avg_days_in_stage: number | null; moved_today: number }[];
  bots: { key: string; name: string; stage: string; status: "working" | "idle" | "needs"; task: string; metrics: { label: string; value: string | number | null }[] }[];
  events: { occurred_at: string; message: string; stage: string | null; actor: string }[];
};

export function employerFloorSource(workspaceId: number, jobId?: number | null, intervalMs = 15000): ConsoleSource {
  let prev: HiringFloor | null = null;
  const order = HIRING_STAGES.map((s) => s.key);

  const toState = (f: HiringFloor): ConsoleState => {
    const label = (key: string) => f.stages.find((s) => s.key === key)?.label ?? HIRING_STAGES.find((s) => s.key === key)?.label ?? key;
    return {
      agents: f.bots.map((b) => ({ id: b.key, name: b.name, zone: b.stage, status: b.status, task: b.task, progress: 0.5, approval: null })),
      zones: order.map((k) => ({ key: k, label: label(k) })),
      kpis: [
        { key: "roles", label: "Open roles", value: String(f.kpis.open_roles) },
        { key: "pipeline", label: "In pipeline", value: String(f.kpis.in_pipeline) },
        { key: "interviews", label: "AI interviews", value: String(f.kpis.interviews_in_flight) },
        { key: "offers", label: "Offers open", value: String(f.kpis.offers), hot: f.kpis.offers > 0 },
        { key: "hired", label: "Hired", value: String(f.kpis.hired) },
      ],
      feed: f.events.slice(0, 8).map((e, i) => ({
        id: `${e.occurred_at}-${i}`,
        at: e.occurred_at,
        agent: f.bots.find((b) => b.stage === e.stage)?.name ?? "Pipeline",
        status: "working",
        text: e.message,
      })),
      sideTitle: "Hiring bots",
      side: f.bots.map((b) => ({
        id: b.key,
        title: b.name,
        subtitle: b.task,
        status: b.status,
        metrics: b.metrics.map((m) => ({ label: m.label, value: m.value === null ? "—" : String(m.value) })),
      })),
      stages: f.stages.map((s) => ({
        key: s.key,
        label: s.label,
        count: s.count,
        sub: s.avg_score !== null ? `avg ${Math.round(s.avg_score)}` : s.moved_today ? `+${s.moved_today} today` : undefined,
      })),
      sample: false,
    };
  };

  const effectsFor = (f: HiringFloor): FloorEffect[] => {
    if (!prev) return [];
    const fx: FloorEffect[] = [];
    f.stages.forEach((s) => {
      const before = prev!.stages.find((p) => p.key === s.key);
      const gained = s.count - (before?.count ?? s.count);
      const i = order.indexOf(s.key);
      if (gained > 0 && i > 0) fx.push({ type: "flow", from: order[i - 1], to: s.key, count: Math.min(gained, 6) });
    });
    f.bots.filter((b) => b.status === "working").forEach((b) => fx.push({ type: "stream", id: b.key }));
    return fx;
  };

  return {
    start({ state, effects, error }) {
      let stopped = false;
      let timer = 0;
      const poll = async () => {
        try {
          const q = jobId ? `?job_id=${jobId}` : "";
          const res = await apiJson<{ data: HiringFloor }>(`/api/v1/employer/workspaces/${workspaceId}/hiring-floor${q}`);
          if (stopped) return;
          state(toState(res.data));
          const fx = effectsFor(res.data);
          prev = res.data;
          if (fx.length) effects(fx);
          error(null);
        } catch {
          if (!stopped) error("Can't load your hiring floor right now. Retrying…");
        }
        if (!stopped) timer = window.setTimeout(poll, intervalMs);
      };
      void poll();
      return () => {
        stopped = true;
        window.clearTimeout(timer);
      };
    },
    async ask(question) {
      try {
        const res = await apiJson<{ data: { answer: string } }>(`/api/v1/employer/workspaces/${workspaceId}/hiring-floor/ask`, {
          method: "POST",
          body: JSON.stringify(jobId ? { question, job_id: jobId } : { question }),
        });
        let audio: Blob | null = null;
        try {
          audio = await apiPostBlob(`/api/v1/employer/workspaces/${workspaceId}/speak`, { text: res.data.answer });
        } catch {
          audio = null;
        }
        return { answer: res.data.answer, audio };
      } catch (e) {
        if (e instanceof ApiError && e.status === 503) return { answer: "Taurus isn't connected yet. Please try again later." };
        throw e;
      }
    },
  };
}

/* ------------------------------------------------------------------ workspaces */

export type TaurusWorkspace = { id: number; name: string; slug: string; status: "active" | "suspended"; is_owner: boolean; role: "owner" | "admin" | "viewer" };

export const workspaceBase = (id: number) => `/api/v1/taurus/workspaces/${id}`;

export const listMyWorkspaces = () => apiJson<{ data: TaurusWorkspace[] }>("/api/v1/taurus/workspaces").then((r) => r.data);
