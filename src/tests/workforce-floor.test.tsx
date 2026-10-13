import { beforeAll, describe, expect, it } from "vitest";
import { render } from "@testing-library/react";
import type { AiEmployee, AuditEvent, Workflow, WorkflowRun } from "@/lib/db/types";
import { buildFloorSnapshot, handedOverBy, WORKING_WINDOW_MS, zoneOf } from "@/modules/workforce-floor/service";
import { toConsoleState } from "@/lib/workforce-floor/live";
import { workforceDemo, DEMO_ZONES } from "@/lib/workforce-floor/demo";
import { WorkforceFloorSection } from "@/components/landing/v3/workforce-floor-section";

const NOW = new Date("2026-10-14T10:00:00.000Z");
const ago = (ms: number) => new Date(NOW.getTime() - ms).toISOString();

function employee(id: string, patch: Partial<AiEmployee> = {}): AiEmployee {
  return {
    id,
    organizationId: "org",
    name: id[0].toUpperCase() + id.slice(1),
    roleTitle: "Support Specialist",
    department: "Support",
    description: null,
    status: "active",
    visibility: "organization",
    responsibilities: [],
    workingStyle: null,
    avatarUrl: null,
    createdBy: null,
    createdAt: ago(86_400_000),
    updatedAt: ago(86_400_000),
    ...patch,
  };
}

function audit(action: string, at: string, metadata: Record<string, unknown> = {}, id = Math.random().toString(36)): AuditEvent {
  return { id, organizationId: "org", actorType: "system", actorId: null, action, metadata, createdAt: at };
}

/** trigger → writer (employee) → approval → publish */
const workflow: Workflow = {
  id: "wf",
  organizationId: "org",
  name: "Weekly posts",
  description: null,
  status: "active",
  trigger: { type: "manual" } as Workflow["trigger"],
  graph: {
    entryNodeId: "t",
    nodes: [
      { id: "t", type: "trigger", next: "w" },
      { id: "w", type: "employee", employeeId: "writer", messageTemplate: "Draft posts", next: "fmt" },
      { id: "fmt", type: "transform", template: "{{steps.w.output}}", next: "ok" },
      { id: "ok", type: "approval", instructions: "Publish the 5 posts", next: null },
    ] as Workflow["graph"]["nodes"],
  },
  createdByUserId: null,
  createdAt: ago(1e6),
  updatedAt: ago(1e6),
};

function run(id: string, status: WorkflowRun["status"], cursorNodeId: string | null, startedAt = ago(60_000)): WorkflowRun {
  return { id, organizationId: "org", workflowId: "wf", status, triggeredBy: "manual", input: {}, output: null, error: null, stepCount: 2, cursorNodeId, createdByUserId: null, startedAt, finishedAt: null };
}

describe("Workforce floor (Sprint 058)", () => {
  it("puts each AI Employee in its department's zone, General last", () => {
    const snap = buildFloorSnapshot({
      organizationName: "Acme",
      employees: [employee("ana", { department: "Sales" }), employee("bo", { department: null }), employee("cy", { department: "Marketing" }), employee("old", { status: "archived" })],
      workflows: [],
      runs: [],
      audit: [],
      now: NOW,
    });
    expect(snap.zones.map((z) => z.key)).toEqual(["Marketing", "Sales", "General"]);
    expect(snap.employees.map((e) => e.id)).toEqual(["ana", "bo", "cy"]);
    expect(zoneOf({ department: "  " })).toBe("General");
  });

  it("reads status from the lifecycle and recent work, never inventing activity", () => {
    const snap = buildFloorSnapshot({
      organizationName: "Acme",
      employees: [
        employee("draft", { status: "draft" }),
        employee("paused", { status: "paused" }),
        employee("training", { status: "training" }),
        employee("busy"),
        employee("broken"),
        employee("quiet"),
        employee("stale"),
      ],
      workflows: [],
      runs: [],
      audit: [
        audit("employee_chat.response_generated", ago(60_000), { employeeId: "busy" }),
        audit("employee_chat.response_generated", ago(300_000), { employeeId: "broken" }),
        audit("employee_chat.response_failed", ago(30_000), { employeeId: "broken" }),
        audit("employee_chat.response_generated", ago(WORKING_WINDOW_MS + 60_000), { employeeId: "stale" }),
      ],
      now: NOW,
    });
    const status = Object.fromEntries(snap.employees.map((e) => [e.id, e.status]));
    expect(status).toEqual({ draft: "offline", paused: "offline", training: "thinking", busy: "working", broken: "error", quiet: "idle", stale: "idle" });
    expect(snap.kpis.answersToday).toBe(3);
    expect(snap.kpis.working).toBe(2); // busy + training
  });

  it("pins a waiting approval to the AI Employee that handed work to it, through non-employee steps", () => {
    expect(handedOverBy(workflow.graph.nodes, "ok")).toBe("writer");
    expect(handedOverBy(workflow.graph.nodes, "w")).toBeNull();

    const snap = buildFloorSnapshot({
      organizationName: "Acme",
      employees: [employee("writer", { department: "Marketing" }), employee("other")],
      workflows: [workflow],
      runs: [run("r1", "waiting", "ok"), run("r2", "succeeded", null, ago(2 * 86_400_000))],
      audit: [audit("workflow.run_waiting", ago(10_000), { workflowId: "wf" })],
      now: NOW,
    });

    const writer = snap.employees.find((e) => e.id === "writer")!;
    expect(writer).toMatchObject({ status: "needs", approval: "Publish the 5 posts", runId: "r1" });
    expect(snap.waiting).toEqual([expect.objectContaining({ runId: "r1", employeeId: "writer", workflowName: "Weekly posts" })]);
    expect(snap.kpis).toMatchObject({ needsYou: 1, runsToday: 1 });
    expect(snap.feed[0]).toMatchObject({ who: "Weekly posts", status: "needs", text: "is waiting for your approval" });
  });

  it("shows an approval with no AI Employee before it as a Taurus-asks card, and hides approvals from viewers", () => {
    const firstStep: Workflow = {
      ...workflow,
      graph: { entryNodeId: "ok", nodes: [{ id: "ok", type: "approval", instructions: "Start the campaign", next: null }] as Workflow["graph"]["nodes"] },
    };
    const snap = buildFloorSnapshot({ organizationName: "Acme", employees: [employee("writer")], workflows: [firstStep], runs: [run("r9", "waiting", "ok")], audit: [], now: NOW });

    const ids = new Map<number, string>();
    const manager = toConsoleState(snap, true, ids);
    expect(manager.proposals).toEqual([{ id: 1, summary: "Weekly posts: Start the campaign" }]);
    expect(ids.get(1)).toBe("r9");

    const viewer = toConsoleState(snap, false, new Map());
    expect(viewer.proposals).toEqual([]);
    expect(viewer.employees.every((e) => e.approval === null)).toBe(true);
  });
});

describe("Workforce floor on the landing page", () => {
  beforeAll(() => {
    class ObserverStub {
      observe() {}
      unobserve() {}
      disconnect() {}
      takeRecords() {
        return [];
      }
    }
    Object.defineProperty(window, "IntersectionObserver", { writable: true, value: ObserverStub });
    Object.defineProperty(window, "ResizeObserver", { writable: true, value: ObserverStub });
    Object.defineProperty(window, "matchMedia", {
      writable: true,
      value: (query: string) => ({ matches: false, media: query, onchange: null, addListener: () => {}, removeListener: () => {}, addEventListener: () => {}, removeEventListener: () => {}, dispatchEvent: () => false }),
    });
  });

  it("runs the five crews as sample data, labelled as such", () => {
    expect(DEMO_ZONES.map((z) => z.label)).toEqual(["Social media", "Lead gen", "Support", "Recruitment", "Operations"]);
    let first: { sample: boolean; employees: unknown[] } | null = null;
    const stop = workforceDemo().start({ state: (s) => (first ??= s), effects: () => {}, error: () => {} });
    stop();
    expect(first!.sample).toBe(true);
    expect(first!.employees.length).toBeGreaterThan(10);
  });

  it("renders the section with its heading, legend and sample-data note", () => {
    const { container } = render(<WorkforceFloorSection />);
    expect(container.querySelector("#floor")).toBeTruthy();
    expect(container.textContent).toContain("Watch your");
    expect(container.textContent).toContain("Waiting for you");
    expect(container.textContent).toContain("Sample data");
  });
});
