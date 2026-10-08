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
  type FloorCandidate,
  type FloorCall,
  type FloorMetrics,
  type FloorStageId,
  type HiringFloorQuery,
  type HiringFloorSnapshot,
} from "./types";

const COMING_SOON = [] as const;

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

function bot(
  id: string,
  name: string,
  stage: FloorStageId,
  state: FloorAgent["state"],
  task: string,
  progress: number,
): FloorAgent {
  return { id, name, stage, state, task, progress };
}

function agents(t: number, autonomous: boolean): FloorAgent[] {
  const scout =
    t < 4_000
      ? bot("scout", "Sourcer", "sourcing", "thinking", "Waiting for the role to land.", 20)
      : t < 12_000
        ? bot("scout", "Sourcer", "sourcing", "working", "Ranking the pool and the client file.", 64)
        : bot("scout", "Sourcer", "sourcing", "idle", "Sample shortlist is on the desk.", 0);

  const caller =
    t < 12_000
      ? bot("caller", "Caller", "calls", "idle", "Idle until sourcing finishes. Sample data.", 0)
      : t < 18_000
        ? bot(
            "caller",
            "Caller",
            "calls",
            autonomous ? "working" : "approval",
            autonomous
              ? "Autonomous is on. We call and screen the sample shortlist."
              : "Waiting for a yes before any sample call.",
            autonomous ? 40 : 100,
          )
        : bot("caller", "Caller", "calls", "working", "Sample calls are on the log. We call and screen the shortlist.", 62);

  const interviewer =
    t < 18_000
      ? bot("interview", "AI Interviewer", "ai", "idle", "AI interview opens after a call says yes.", 0)
      : t < 30_000
        ? bot("interview", "AI Interviewer", "ai", "working", "Sample candidates are in the AI interview. Clear mark is 75.", 58)
        : bot("interview", "AI Interviewer", "ai", "thinking", "Reading the latest sample AI interview.", 30);

  const l1 =
    t < 24_000
      ? bot("l1", "L1 Evaluator", "l1", "idle", "L1 opens after the AI interview.", 0)
      : t < 32_000
        ? bot("l1", "L1 Evaluator", "l1", "working", "L1 is open for the people who cleared.", 44)
        : bot("l1", "L1 Evaluator", "l1", "idle", "L1 scores are on the people who sat it.", 0);

  const l2 =
    t < 30_000
      ? bot("l2", "L2 Evaluator", "l2", "idle", "L2 opens after L1.", 0)
      : t < 40_000
        ? bot("l2", "L2 Evaluator", "l2", "working", "L2 is open. Clear mark is 75.", 48)
        : bot("l2", "L2 Evaluator", "l2", "idle", "L2 scores are on the desk.", 0);

  const scheduler =
    t < 36_000
      ? bot("scheduler", "Scheduler", "human", "idle", "Human round is later. Zoom is not sent.", 0)
      : t < 44_000
        ? bot("scheduler", "Scheduler", "human", "working", "Asking for a sample slot. Zoom is not sent for real.", 36)
        : bot("scheduler", "Scheduler", "human", "idle", "Human rounds booked stay on the log. Zoom is not sent.", 0);

  const bgv =
    t < 42_000
      ? bot("bgv", "BGV Checker", "bgv", "idle", "Pre-BGV starts after the rounds. Sample data.", 0)
      : bot("bgv", "BGV Checker", "bgv", "working", "Sample pre-BGV is on the desk.", 55);

  const offer = bot("offer", "Offer Desk", "offer", "approval", "Needs your approval. A person always releases the offer.", 100);

  const engagement =
    t < 48_000
      ? bot("engagement", "Engagement", "joining", "idle", "Joining support starts after the offer. Sample data.", 0)
      : t < 50_000
        ? bot("engagement", "Engagement", "joining", "working", "Sample joining note. We stay in touch until they join.", 22)
        : bot("engagement", "Engagement", "joining", "error", "Sample Rohan Mehta went quiet. Dropout risk 74. Sample data.", 0);

  return [scout, caller, interviewer, l1, l2, scheduler, bgv, offer, engagement];
}

function parseDuration(value: string): number {
  const match = /^(\d+):(\d{2})$/.exec(value);
  if (!match) return 0;
  return Number(match[1]) * 60 + Number(match[2]);
}

function formatDuration(seconds: number): string {
  const rounded = Math.round(seconds);
  const min = Math.floor(rounded / 60);
  const sec = rounded % 60;
  return `${min}:${String(sec).padStart(2, "0")}`;
}

const PAST_BGV: readonly FloorStageId[] = ["bgv", "offer", "joining"];

function metrics(t: number, candidates: FloorCandidate[], calls: FloorCall[]): FloorMetrics {
  const ranked = candidates.filter((person) => !person.timeline.every((step) => /still being ranked/i.test(step.label))).length;
  const interested = calls.filter((call) => call.outcome === "Interested").length;
  const notInterested = calls.filter((call) => call.outcome === "Not interested").length;
  const noAnswer = calls.filter((call) => call.outcome === "No answer").length;
  const durations = calls.map((call) => parseDuration(call.duration)).filter((value) => value > 0);
  const avg = durations.length ? durations.reduce((sum, value) => sum + value, 0) / durations.length : 0;
  const atBgv = candidates.filter((person) => PAST_BGV.includes(person.stage));
  const days = (t / LOOP_MS) * 3;

  return {
    sourced: candidates.length,
    ranked,
    callsMade: calls.length,
    connected: interested + notInterested,
    interested,
    notInterested,
    noAnswer,
    avgCallDuration: durations.length ? formatDuration(avg) : "—",
    interviewsTaken: candidates.filter((person) => person.scores.ai != null).length,
    interviewsCleared: candidates.filter((person) => person.scores.ai != null && person.scores.ai >= 75).length,
    l1Cleared: candidates.filter((person) => person.scores.l1 != null && person.scores.l1 >= 75).length,
    l2Cleared: candidates.filter((person) => person.scores.l2 != null && person.scores.l2 >= 75).length,
    humanBooked: candidates.filter((person) => person.timeline.some((step) => /human round/i.test(step.label))).length,
    bgvVerified: atBgv.filter((person) => person.bgv === "verified").length,
    bgvPending: atBgv.filter((person) => person.bgv === "pending" || person.bgv === "in_progress").length,
    bgvFlagged: atBgv.filter((person) => person.bgv === "flagged").length,
    offersWaiting: candidates.filter((person) => person.stage === "offer").length,
    offersReleased: 0,
    offersAccepted: 0,
    joined: candidates.filter((person) => person.stage === "joining" && person.dropoutRisk == null).length,
    dropoutAlerts: candidates.filter((person) => person.dropoutRisk != null).length,
    elapsedLabel: `${days.toFixed(1)} demo days`,
    targetLabel: "3-day target",
  };
}

/**
 * Answers a status question from the snapshot already on screen.
 * Hire lines are handled by the view via parseHiringPrompt.
 */
export function answerFloorQuestion(question: string, floor: HiringFloorSnapshot): string {
  const q = question.toLowerCase();
  const stageCount = (id: FloorStageId) => floor.stages.find((stage) => stage.id === id)?.count ?? 0;

  if (/bgv/.test(q) && /asha/.test(q)) {
    const asha = floor.candidates.find((person) => person.id === "asha");
    if (!asha) return "Sample Asha Iyer is not on the floor yet. Demo data.";
    const step = asha.timeline.find((row) => /bgv|epfo|digilocker/i.test(row.label));
    const word = asha.bgv === "verified" ? "Verified" : asha.bgv === "flagged" ? "Flagged" : asha.bgv === "in_progress" ? "In progress" : "Pending";
    return `Sample Asha Iyer · BGV ${word}. ${step ? step.label : "No BGV step on her timeline yet."} Demo data. EPFO and DigiLocker are not connected.`;
  }

  if (/l2/.test(q) && /how many|count|at l2|on l2/.test(q)) {
    const n = stageCount("l2");
    return `${n} sample ${n === 1 ? "candidate is" : "candidates are"} at L2. Demo data. The clear mark used in this story is 75.`;
  }

  if (/who/.test(q) && /interest/.test(q)) {
    const names = floor.candidates.filter((person) => person.interest === "interested").map((person) => person.name);
    if (!names.length) return "Nobody is marked interested yet in this demo.";
    return `Interested in this demo: ${names.join(", ")}.`;
  }

  if (/offer/.test(q)) {
    const waiting = floor.metrics.offersWaiting;
    return `${waiting} sample ${waiting === 1 ? "offer is" : "offers are"} awaiting approval. Released 0. Accepted 0. A person always releases the offer. Nothing is emailed.`;
  }

  if (/dropout|quiet/.test(q)) {
    const names = floor.candidates.filter((person) => person.dropoutRisk != null);
    if (!names.length) return "No dropout-risk alert on this pass of the demo.";
    return names.map((person) => `${person.name} · dropout risk ${person.dropoutRisk}. Sample alert.`).join(" ");
  }

  return "I can answer from this demo: how many are at L2, who's interested, BGV status for Asha, and whether an offer is waiting. Sample data.";
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

  const calls = DEMO_CALLS.filter((call) => call.at <= t).map((call) => ({
    id: call.id,
    candidateId: call.candidateId,
    name: call.name,
    when: call.when,
    duration: call.duration,
    outcome: call.outcome,
    snippet: call.snippet,
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
    calls,
    approvals: [
      {
        id: "outreach",
        title: "Start outreach",
        detail: "Call the sample shortlist? Silence is not a yes. We call and screen shortlisted candidates.",
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
    metrics: metrics(t, candidates, calls),
    activeStage: activeStage(t),
    elapsedMs: t,
    loopMs: LOOP_MS,
  };
}
