/**
 * Workforce floor (Sprint 058): an organisation's AI Employees as the 3D
 * floor draws them — one robot per employee, one zone per department — with
 * what each is doing, what is waiting for a person, and a live feed.
 *
 * Built only from data Taurus already records, so nothing on the floor is
 * invented:
 *  - status comes from the employee's lifecycle (draft, training, active,
 *    paused) and its last minutes of work (chat answers in the audit log);
 *  - "waiting for you" is a workflow run paused at an approval step, pinned to
 *    the AI Employee that handed work to that step;
 *  - the feed is the audit log, in plain words.
 */

import type { DataStore } from "@/lib/db/store";
import type { AiEmployee, AuditEvent, Workflow, WorkflowNode, WorkflowRun } from "@/lib/db/types";

export type FloorEmployeeStatus = "working" | "thinking" | "needs" | "error" | "idle" | "offline";
export type FloorFeedStatus = FloorEmployeeStatus | "done" | "approved";

export interface FloorEmployee {
  id: string;
  name: string;
  roleTitle: string;
  zone: string;
  status: FloorEmployeeStatus;
  task: string;
  /** When waiting for a person: what they are being asked to approve. */
  approval: string | null;
  /** The workflow run that approval belongs to. */
  runId: string | null;
}

export interface FloorWaitingRun {
  runId: string;
  workflowId: string;
  workflowName: string;
  /** The AI Employee that handed work to the approval step, when there is one. */
  employeeId: string | null;
  instructions: string;
  since: string;
}

export interface FloorFeedItem {
  id: string;
  at: string;
  who: string;
  status: FloorFeedStatus;
  text: string;
}

export interface FloorSnapshot {
  generatedAt: string;
  organizationName: string;
  zones: { key: string; label: string }[];
  employees: FloorEmployee[];
  waiting: FloorWaitingRun[];
  feed: FloorFeedItem[];
  kpis: { working: number; needsYou: number; runsToday: number; answersToday: number; team: number };
  /** Runs that finished since `since` (for the "work delivered" animation). */
  finishedRunIds: string[];
}

/** Work in the last few minutes counts as "working now". */
export const WORKING_WINDOW_MS = 15 * 60 * 1000;
const FEED_SIZE = 10;
const AUDIT_SCAN = 300;
const RUNS_PER_WORKFLOW = 25;

const GENERAL = "General";

export function zoneOf(employee: Pick<AiEmployee, "department">): string {
  const d = employee.department?.trim();
  return d ? d : GENERAL;
}

/** The employee node whose `next` is the given node — who handed the work over. */
export function handedOverBy(nodes: WorkflowNode[], nodeId: string): string | null {
  const seen = new Set<string>();
  let current = nodeId;
  // Walk back through non-employee steps (a transform, a condition) to the employee before them.
  for (let hops = 0; hops < nodes.length; hops++) {
    const prev = nodes.find((n) => {
      if ("next" in n && n.next === current) return true;
      if (n.type === "condition" && (n.nextIfTrue === current || n.nextIfFalse === current)) return true;
      return false;
    });
    if (!prev || seen.has(prev.id)) return null;
    seen.add(prev.id);
    if (prev.type === "employee") return prev.employeeId;
    current = prev.id;
  }
  return null;
}

function startOfDay(now: Date): number {
  const d = new Date(now);
  d.setHours(0, 0, 0, 0);
  return d.getTime();
}

export interface FloorInputs {
  organizationName: string;
  employees: AiEmployee[];
  workflows: Workflow[];
  runs: WorkflowRun[];
  audit: AuditEvent[];
  now?: Date;
  /** Runs already seen by the client, to report which have finished since. */
  knownRunningRunIds?: string[];
}

/** Pure: everything the floor shows, from data already loaded. */
export function buildFloorSnapshot(input: FloorInputs): FloorSnapshot {
  const now = input.now ?? new Date();
  const since = now.getTime() - WORKING_WINDOW_MS;
  const today = startOfDay(now);
  const staff = input.employees.filter((e) => e.status !== "archived");
  const byId = new Map(staff.map((e) => [e.id, e]));
  const workflows = new Map(input.workflows.map((w) => [w.id, w]));

  // Last answer / failure per employee inside the working window.
  const lastAnswer = new Map<string, number>();
  const lastFailure = new Map<string, number>();
  let answersToday = 0;
  for (const ev of input.audit) {
    const at = Date.parse(ev.createdAt);
    const employeeId = typeof ev.metadata?.employeeId === "string" ? ev.metadata.employeeId : null;
    if (ev.action === "employee_chat.response_generated") {
      if (at >= today) answersToday++;
      if (employeeId && at >= since) lastAnswer.set(employeeId, Math.max(lastAnswer.get(employeeId) ?? 0, at));
    } else if (ev.action === "employee_chat.response_failed" && employeeId && at >= since) {
      lastFailure.set(employeeId, Math.max(lastFailure.get(employeeId) ?? 0, at));
    }
  }

  // Runs waiting at an approval step, pinned to the employee who handed it over.
  const waiting: FloorWaitingRun[] = [];
  for (const run of input.runs) {
    if (run.status !== "waiting" || !run.cursorNodeId) continue;
    const wf = workflows.get(run.workflowId);
    if (!wf) continue;
    const node = wf.graph.nodes.find((n) => n.id === run.cursorNodeId);
    const instructions = node && node.type === "approval" && node.instructions.trim() ? node.instructions.trim() : `Approve the next step of ${wf.name}`;
    const employeeId = handedOverBy(wf.graph.nodes, run.cursorNodeId);
    waiting.push({
      runId: run.id,
      workflowId: wf.id,
      workflowName: wf.name,
      employeeId: employeeId && byId.has(employeeId) ? employeeId : null,
      instructions,
      since: run.startedAt,
    });
  }
  waiting.sort((a, b) => a.since.localeCompare(b.since));

  // Employees inside a running workflow are working on it.
  const inRunningWorkflow = new Map<string, string>();
  for (const run of input.runs) {
    if (run.status !== "running") continue;
    const wf = workflows.get(run.workflowId);
    for (const n of wf?.graph.nodes ?? []) {
      if (n.type === "employee" && !inRunningWorkflow.has(n.employeeId)) inRunningWorkflow.set(n.employeeId, wf!.name);
    }
  }

  const employees: FloorEmployee[] = staff.map((e) => {
    const base = { id: e.id, name: e.name, roleTitle: e.roleTitle, zone: zoneOf(e), approval: null, runId: null };
    const ask = waiting.find((w) => w.employeeId === e.id);
    if (ask) return { ...base, status: "needs", task: `Waiting for you in ${ask.workflowName}`, approval: ask.instructions, runId: ask.runId };
    if (e.status === "draft") return { ...base, status: "offline", task: "Not started yet: finish setting up" };
    if (e.status === "paused") return { ...base, status: "offline", task: "Paused" };
    if (e.status === "training") return { ...base, status: "thinking", task: "Learning from the Knowledge Vault" };
    const failed = lastFailure.get(e.id) ?? 0;
    const answered = lastAnswer.get(e.id) ?? 0;
    if (failed > answered) return { ...base, status: "error", task: "Could not answer just now: check the Model Hub" };
    const flow = inRunningWorkflow.get(e.id);
    if (flow) return { ...base, status: "working", task: `Working in ${flow}` };
    if (answered > 0) return { ...base, status: "working", task: "Answering conversations" };
    return { ...base, status: "idle", task: e.roleTitle ? `Ready: ${e.roleTitle}` : "Ready" };
  });

  // Departments in the order they first appear, General last.
  const zoneKeys: string[] = [];
  for (const e of employees) if (!zoneKeys.includes(e.zone)) zoneKeys.push(e.zone);
  zoneKeys.sort((a, b) => (a === GENERAL ? 1 : b === GENERAL ? -1 : a.localeCompare(b)));

  const known = new Set(input.knownRunningRunIds ?? []);
  const finishedRunIds = input.runs.filter((r) => known.has(r.id) && (r.status === "succeeded" || r.status === "failed")).map((r) => r.id);

  return {
    generatedAt: now.toISOString(),
    organizationName: input.organizationName,
    zones: zoneKeys.map((k) => ({ key: k, label: k })),
    employees,
    waiting,
    feed: feedFrom(input.audit, byId, workflows),
    kpis: {
      working: employees.filter((e) => e.status === "working" || e.status === "thinking").length,
      needsYou: waiting.length,
      runsToday: input.runs.filter((r) => Date.parse(r.startedAt) >= today).length,
      answersToday,
      team: employees.length,
    },
    finishedRunIds,
  };
}

/** The audit log as plain sentences about the workforce. Anything else is left out. */
function feedFrom(audit: AuditEvent[], employees: Map<string, AiEmployee>, workflows: Map<string, Workflow>): FloorFeedItem[] {
  const items: FloorFeedItem[] = [];
  for (const ev of audit) {
    if (items.length >= FEED_SIZE) break;
    const m = ev.metadata ?? {};
    const employee = typeof m.employeeId === "string" ? employees.get(m.employeeId) : ev.targetType === "employee" && ev.targetId ? employees.get(ev.targetId) : undefined;
    const workflow = typeof m.workflowId === "string" ? workflows.get(m.workflowId) : undefined;
    const base = { id: ev.id, at: ev.createdAt };
    switch (ev.action) {
      case "employee_chat.response_generated":
        if (employee) items.push({ ...base, who: employee.name, status: "done", text: "answered a conversation" });
        break;
      case "employee_chat.response_failed":
        if (employee) items.push({ ...base, who: employee.name, status: "error", text: "could not answer a message" });
        break;
      case "employee.created":
        if (employee) items.push({ ...base, who: employee.name, status: "idle", text: `joined the team as ${employee.roleTitle}` });
        break;
      case "employee_dna.published":
        if (employee) items.push({ ...base, who: employee.name, status: "working", text: "got an updated DNA" });
        break;
      case "workflow.run_started":
        if (workflow) items.push({ ...base, who: workflow.name, status: "working", text: "started" });
        break;
      case "workflow.run_waiting":
        if (workflow) items.push({ ...base, who: workflow.name, status: "needs", text: "is waiting for your approval" });
        break;
      case "workflow.run_finished":
        if (workflow) items.push({ ...base, who: workflow.name, status: m.status === "succeeded" ? "done" : "error", text: m.status === "succeeded" ? "finished" : "stopped with an error" });
        break;
    }
  }
  return items;
}

/** Load what the floor needs for one organisation and build it. */
export async function loadFloorSnapshot(
  store: DataStore,
  organization: { id: string; name: string },
  knownRunningRunIds: string[] = [],
): Promise<FloorSnapshot> {
  const [employees, workflows, audit] = await Promise.all([
    store.listEmployees(organization.id),
    store.listWorkflows(organization.id),
    store.listAuditEvents(organization.id, AUDIT_SCAN),
  ]);
  const live = workflows.filter((w) => w.status !== "archived");
  const runs = (await Promise.all(live.map((w) => store.listWorkflowRunsForWorkflow(organization.id, w.id, RUNS_PER_WORKFLOW)))).flat();

  return buildFloorSnapshot({ organizationName: organization.name, employees, workflows: live, runs, audit, knownRunningRunIds });
}
