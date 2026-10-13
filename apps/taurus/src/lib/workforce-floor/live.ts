/**
 * The dashboard's live floor source (Sprint 058): polls the organisation's
 * workforce floor and maps it onto the console. Effects are derived from what
 * changed between two polls, so the floor only animates real events: a robot
 * that starts working streams to the core, one that is approved carries its
 * work in, and a new approval request turns the camera to the robot asking.
 *
 * Approvals go through the same workflow engine as the run page. A waiting
 * step with no AI Employee before it (an approval as a workflow's first step)
 * has no robot to stand over, so it shows as a "Taurus asks" card instead.
 */
import { decideFromFloorAction, getWorkforceFloorAction } from "@/modules/workforce-floor/actions";
import type { FloorSnapshot } from "@/modules/workforce-floor/service";
import type { ConsoleSource, ConsoleState, FloorEffect } from "./types";

export function toConsoleState(s: FloorSnapshot, canDecide: boolean, proposalIds: Map<number, string>): ConsoleState {
  proposalIds.clear();
  const unpinned = canDecide ? s.waiting.filter((w) => w.employeeId === null) : [];
  unpinned.forEach((w, i) => proposalIds.set(i + 1, w.runId));

  return {
    employees: s.employees.map((e) => ({
      id: e.id,
      name: e.name,
      zone: e.zone,
      status: e.status,
      task: e.task,
      progress: e.status === "working" ? 0.6 : 0,
      approval: canDecide ? e.approval : null,
    })),
    zones: s.zones,
    kpis: [
      { key: "working", label: "Working now", value: String(s.kpis.working) },
      { key: "needs", label: "Needs you", value: String(s.kpis.needsYou), hot: s.kpis.needsYou > 0 },
      { key: "runs", label: "Workflow runs today", value: String(s.kpis.runsToday) },
      { key: "answers", label: "Answers today", value: String(s.kpis.answersToday) },
      { key: "team", label: "AI Employees", value: String(s.kpis.team) },
    ],
    feed: s.feed.map((f) => ({ id: f.id, at: f.at, who: f.who, status: f.status, text: f.text })),
    sideTitle: s.waiting.length ? "Waiting for you" : "Your team",
    side: s.waiting.length
      ? s.waiting.slice(0, 5).map((w) => ({ id: w.runId, title: w.workflowName, subtitle: w.instructions, status: "needs" as const }))
      : s.employees.slice(0, 6).map((e) => ({ id: e.id, title: e.name, subtitle: e.task, status: e.status })),
    sample: false,
    proposals: unpinned.map((w, i) => ({ id: i + 1, summary: `${w.workflowName}: ${w.instructions}` })),
  };
}

export function workspaceFloorSource(intervalMs = 10000): ConsoleSource {
  let latest: FloorSnapshot | null = null;
  let canDecide = false;
  let kick: (() => void) | null = null;
  const proposalIds = new Map<number, string>();

  const effectsFor = (prev: FloorSnapshot | null, next: FloorSnapshot): FloorEffect[] => {
    if (!prev) return [];
    const before = new Map(prev.employees.map((e) => [e.id, e.status]));
    const fx: FloorEffect[] = [];
    for (const e of next.employees) {
      const was = before.get(e.id);
      if (was === "needs" && e.status !== "needs") fx.push({ type: "deliver", id: e.id });
      else if (e.status === "needs" && was !== "needs") fx.push({ type: "focus", id: e.id });
      else if (e.status === "working" && was !== "working") fx.push({ type: "stream", id: e.id });
    }
    return fx;
  };

  const decide = async (runId: string, yes: boolean): Promise<string> => {
    const res = await decideFromFloorAction(runId, yes ? "approve" : "reject");
    kick?.();
    return res.message;
  };

  return {
    start({ state, effects, error }) {
      let stopped = false;
      let timer = 0;
      let busy = false;
      let again = false;
      let failures = 0;
      const poll = async () => {
        if (busy) {
          again = true;
          return;
        }
        busy = true;
        window.clearTimeout(timer);
        try {
          const res = await getWorkforceFloorAction();
          if (stopped) return;
          if (res.error || !res.snapshot) {
            error(res.error ?? "Can't load the workforce floor right now.");
          } else {
            canDecide = res.canDecide ?? false;
            const fx = effectsFor(latest, res.snapshot);
            latest = res.snapshot;
            state(toConsoleState(res.snapshot, canDecide, proposalIds));
            if (fx.length) effects(fx);
            failures = 0;
            error(null);
          }
        } catch {
          // One dropped poll isn't worth a banner: it would flash on and off.
          if (!stopped && ++failures >= 2) error("Can't reach Taurus right now. Retrying…");
        }
        busy = false;
        if (stopped) return;
        if (again) {
          again = false;
          void poll();
          return;
        }
        timer = window.setTimeout(poll, intervalMs);
      };
      void poll();
      kick = () => void poll();
      return () => {
        stopped = true;
        kick = null;
        window.clearTimeout(timer);
      };
    },
    async approve(employeeId) {
      const ask = latest?.waiting.find((w) => w.employeeId === employeeId);
      if (!ask) return "Nothing is waiting on that AI Employee any more.";
      return decide(ask.runId, true);
    },
    async decide(id, yes) {
      const runId = proposalIds.get(id);
      if (!runId) return "That request has already been handled.";
      return decide(runId, yes);
    },
  };
}
