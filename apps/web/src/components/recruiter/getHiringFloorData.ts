/**
 * Single read for the hiring floor.
 *
 * Today this returns scripted demo data. When the desk is wired, replace
 * the body with a mapper from the employer API onto `HiringFloorSnapshot`
 * and set `source` to a live value. Views call this function only — they
 * do not know whether the rows are sample or real.
 */

import {
  DEMO_ACTIVITY,
  DEMO_CALLS,
  DEMO_PEOPLE,
  DEFAULT_BRIEF,
  LOOP_MS,
  type DemoPerson,
} from "./demo-data";
import {
  FLOOR_STAGES,
  type FloorAgent,
  type FloorStageId,
  type HiringFloorQuery,
  type HiringFloorSnapshot,
} from "./types";

const COMING_SOON = ["AI calls", "Pre-BGV", "Offers", "Joining chats"] as const;

function stageOf(person: DemoPerson, t: number): FloorStageId | null {
  let current: FloorStageId | null = null;
  for (const step of person.steps) {
    if (step.at <= t) current = step.stage;
  }
  return current;
}

function activeStage(t: number): FloorStageId {
  if (t < 4_000) return "job";
  if (t < 12_000) return "sourcing";
  if (t < 18_000) return "calls";
  if (t < 24_000) return "ai";
  if (t < 30_000) return "l1";
  if (t < 36_000) return "l2";
  if (t < 42_000) return "human";
  if (t < 48_000) return "bgv";
  if (t < 54_000) return "offer";
  return "joining";
}

function agents(t: number, autonomous: boolean): FloorAgent[] {
  const scout: FloorAgent =
    t < 4_000
      ? { id: "scout", name: "Sourcing", stage: "job", state: "thinking", task: "Waiting for the role to land." }
      : t < 12_000
        ? { id: "scout", name: "Sourcing", stage: "sourcing", state: "working", task: "Ranking the pool and the client file." }
        : { id: "scout", name: "Sourcing", stage: "sourcing", state: "idle", task: "Sample shortlist is on the desk." };

  const caller: FloorAgent =
    t < 12_000
      ? { id: "caller", name: "Caller", stage: "calls", state: "idle", task: "Idle until sourcing finishes." }
      : t < 18_000
        ? {
            id: "caller",
            name: "Caller",
            stage: "calls",
            state: autonomous ? "working" : "approval",
            task: autonomous ? "Autonomous is on. Sample calls start without a pause." : "Waiting for a yes before any call.",
          }
        : { id: "caller", name: "Caller", stage: "calls", state: "working", task: "Sample calls are on the log. This dialler is not live." };

  const interviewer: FloorAgent =
    t < 18_000
      ? { id: "interview", name: "Interviewer", stage: "ai", state: "idle", task: "AI interview opens after a call says yes." }
      : t < 24_000
        ? { id: "interview", name: "Interviewer", stage: "ai", state: "working", task: "Two sample candidates are in the AI interview." }
        : t < 30_000
          ? { id: "interview", name: "Interviewer", stage: "l1", state: "working", task: "L1 is open." }
          : t < 36_000
            ? { id: "interview", name: "Interviewer", stage: "l2", state: "working", task: "L2 is open." }
            : { id: "interview", name: "Interviewer", stage: "l2", state: "idle", task: "Later rounds are with the people who cleared." };

  const closer: FloorAgent =
    t < 36_000
      ? { id: "closer", name: "Closer", stage: "human", state: "idle", task: "Human round, BGV, offer, and joining are later." }
      : t < 42_000
        ? { id: "closer", name: "Closer", stage: "human", state: "working", task: "Asking for a sample slot. Zoom is not sent for real." }
        : t < 48_000
          ? { id: "closer", name: "Closer", stage: "bgv", state: "working", task: "Pre-BGV preview. Vendors are not connected." }
          : t < 54_000
            ? { id: "closer", name: "Closer", stage: "offer", state: "approval", task: "Needs your approval. A person always releases the offer." }
            : { id: "closer", name: "Closer", stage: "joining", state: "error", task: "Sample dropout alert. The joining chat is not live." };

  return [scout, caller, interviewer, closer];
}

export function getHiringFloorData(query: HiringFloorQuery): HiringFloorSnapshot {
  const t = ((query.elapsedMs % LOOP_MS) + LOOP_MS) % LOOP_MS;
  const job = query.brief ?? { ...DEFAULT_BRIEF };

  const candidates = DEMO_PEOPLE.flatMap((person) => {
    const stage = stageOf(person, t);
    if (!stage) return [];
    const timeline = person.steps.filter((step) => step.at <= t).map((step) => ({ label: step.label }));
    return [
      {
        id: person.id,
        name: person.name,
        source: person.source,
        city: person.city,
        notice: person.notice,
        match: person.match,
        interest: person.interest,
        bgv: person.bgv,
        stage,
        scores: person.scores,
        dropoutRisk: stage === "joining" ? person.dropoutRisk : null,
        timeline,
        fictional: true as const,
      },
    ];
  });

  const stages = FLOOR_STAGES.map((stage) => ({
    id: stage.id,
    label: stage.label,
    comingSoon: stage.soon,
    count: stage.id === "job" ? (t >= 1_200 ? 1 : 0) : candidates.filter((person) => person.stage === stage.id).length,
  }));

  return {
    source: "demo",
    label: "Demo data",
    comingSoon: COMING_SOON,
    company: "Northwind Labs (fictional)",
    job,
    autonomous: query.autonomous,
    stages,
    candidates,
    calls: DEMO_CALLS.filter((call) => call.at <= t).map((call) => ({
      id: call.id,
      candidateId: call.candidateId,
      name: call.name,
      when: call.when,
      duration: call.duration,
      outcome: call.outcome,
      snippet: call.snippet,
    })),
    approvals: [
      {
        id: "outreach",
        title: "Start outreach",
        detail: "Call the sample shortlist? Silence is not a yes. This dialler is not live.",
      },
      {
        id: "offer",
        title: "Release offer",
        detail: "Needs your approval. A person always releases the offer, even in autonomous mode.",
      },
    ],
    activity: DEMO_ACTIVITY.filter((item) => item.at <= t)
      .slice(-8)
      .reverse()
      .map((item) => ({
        id: item.id,
        time: item.time,
        agent: item.agent,
        state: item.state,
        text: item.text,
      })),
    agents: agents(t, query.autonomous),
    activeStage: activeStage(t),
    elapsedMs: t,
    loopMs: LOOP_MS,
  };
}
