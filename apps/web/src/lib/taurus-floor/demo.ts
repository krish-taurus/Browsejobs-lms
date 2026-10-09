/**
 * Client-side simulations behind the public /taurusai demos. Every number
 * here is invented for the demo and the console labels it SAMPLE — nothing
 * on these pages reads real agent, candidate or spend data.
 */
import type { FloorAgent, FloorStatus, FloorZone } from "./floor";
import type { ConsoleSource, ConsoleState, FeedItem, FeedStatus, FloorEffect } from "./types";

const pick = <T,>(a: T[]): T | undefined => a[Math.floor(Math.random() * a.length)];
const rand = (a: number, b: number) => a + Math.random() * (b - a);
const lower = (s: string) => s.charAt(0).toLowerCase() + s.slice(1);
let seq = 0;
const feedItem = (agent: string, status: FeedStatus, text: string): FeedItem => ({
  id: `f${++seq}`,
  at: new Date().toISOString(),
  agent,
  status,
  text,
});

/* ------------------------------------------------------------------ ops floor */

export const OPS_ZONES: FloorZone[] = [
  { key: "sales", label: "Sales" },
  { key: "marketing", label: "Marketing" },
  { key: "support", label: "Support" },
  { key: "finance", label: "Finance" },
  { key: "operations", label: "Operations" },
];

type OpsBot = FloorAgent & { tasks: string[]; ti: number; ask?: string; rate: number; timer: number; busy: boolean };

/** A made-up business running on Taurus — generic departments, not real bots. */
const OPS_SEED: Array<[string, string, FloorStatus, string[], string?]> = [
  ["Lead qualifier", "sales", "working", ["Scoring 42 inbound leads", "Enriching 18 company profiles", "Routing hot leads to the team"]],
  ["Outreach SDR", "sales", "needs", ["Writing 18 follow-up emails", "Personalising a cold sequence", "Booking intro calls"], "Send 18 follow-up emails"],
  ["CRM hygiene", "sales", "working", ["Merging 31 duplicate contacts", "Filling missing deal stages", "Archiving dead deals"]],
  ["Social media", "marketing", "thinking", ["Drafting a 7-slide carousel", "Replying to 9 comments", "Planning next week’s posts"], "Publish the carousel post"],
  ["Ads optimiser", "marketing", "working", ["Analysing cost per lead by ad set", "Pausing 2 weak creatives", "Building a lookalike audience"], "Raise budget on 2 ad sets"],
  ["Video studio", "marketing", "working", ["Rendering the weekly update video", "Captioning 3 reels", "Exporting 9:16 cut-downs"]],
  ["SEO", "marketing", "working", ["Fixing 18 missing meta descriptions", "Crawling 1,240 site URLs", "Mapping FAQ schema"]],
  ["Support desk", "support", "working", ["Answering 23 open tickets", "Tagging tickets by topic", "Drafting help-centre articles"], "Send 6 refund replies"],
  ["Inbox assistant", "support", "working", ["Sorting 64 inbox emails", "Drafting 4 replies", "Flagging 2 urgent threads"]],
  ["Reviews", "support", "idle", ["Replying to 12 Google reviews", "Summarising review themes", "Requesting reviews from 20 customers"], "Post 12 review replies"],
  ["Invoice bot", "finance", "working", ["Matching 40 invoices to payments", "Chasing 5 overdue invoices", "Preparing the GST summary"], "Pay 3 vendor invoices"],
  ["Market watch", "finance", "thinking", ["Scanning the watchlist for breakouts", "Writing the market brief", "Tracking 4 competitor prices"]],
  ["Scheduler", "operations", "idle", ["Booking 5 meetings", "Rescheduling 2 clashes", "Sending tomorrow’s agenda"]],
  ["Data sync", "operations", "error", ["Syncing orders to the warehouse sheet", "Refreshing the stock report", "Backing up the CRM"]],
  ["Report writer", "operations", "working", ["Writing the weekly ops report", "Charting this month’s numbers", "Summarising team updates"]],
  ["Backup bot", "operations", "offline", ["Archiving old logs", "Clearing 12 GB of caches", "Checking last night’s backup"]],
];

export function opsDemo(): ConsoleSource {
  const bots: OpsBot[] = OPS_SEED.map(([name, zone, status, tasks, ask], i) => ({
    id: `d${i}`,
    name,
    zone,
    status,
    tasks,
    ti: 0,
    task: tasks[0],
    ask,
    approval: status === "needs" ? ask ?? null : null,
    progress: status === "working" ? rand(0.2, 0.85) : 0.05,
    rate: rand(1 / 70, 1 / 32),
    timer: rand(4, 10),
    busy: false,
  }));
  bots.find((b) => b.name === "Video studio")!.progress = 0.97;
  let done = 27;
  let spend = 9.44;
  let feed: FeedItem[] = [
    feedItem("Social media", "thinking", "is planning the next step: Drafting a 7-slide carousel"),
    feedItem("SEO", "working", "is 41% through: Fixing 18 missing meta descriptions"),
    feedItem("Outreach SDR", "needs", "needs your approval: Send 18 follow-up emails"),
    feedItem("Data sync", "error", "hit an upstream timeout. Retrying."),
    feedItem("Report writer", "done", "completed: Summarising team updates"),
  ];

  const snapshot = (): ConsoleState => {
    const running = bots.filter((b) => b.status === "working" || b.status === "thinking");
    const needs = bots.filter((b) => b.status === "needs").length;
    return {
      agents: bots.map(({ id, name, zone, status, task, progress, approval }) => ({ id, name, zone, status, task, progress, approval })),
      zones: OPS_ZONES,
      kpis: [
        { key: "running", label: "Running now", value: String(running.length) },
        { key: "needs", label: "Needs you", value: String(needs), hot: needs > 0 },
        { key: "done", label: "Done today", value: String(done) },
        { key: "spend", label: "Spent today", value: `$${spend.toFixed(2)}`, note: "SAMPLE" },
      ],
      feed,
      sideTitle: "Running now",
      side: bots
        .filter((b) => b.status === "working" && !b.busy)
        .sort((a, b) => (b.progress ?? 0) - (a.progress ?? 0))
        .slice(0, 4)
        .map((b) => ({ id: b.id, title: b.name, subtitle: b.task ?? "", status: b.status, progress: b.progress })),
      sample: true,
    };
  };

  const push = (b: OpsBot, status: FeedStatus, text: string) => {
    feed = [feedItem(b.name, status, text), ...feed].slice(0, 8);
  };

  return {
    start({ state, effects }) {
      state(snapshot());
      let nextEvent = 2.5;
      let nextAmbient = 0.4;
      const step = 0.5;
      const timer = window.setInterval(() => {
        const fx: FloorEffect[] = [];
        for (const b of bots) {
          if (b.busy) continue;
          if (b.status === "working") {
            b.progress = Math.min(1, (b.progress ?? 0) + step * b.rate);
            if (b.progress >= 1) {
              b.busy = true;
              fx.push({ type: "deliver", id: b.id });
              window.setTimeout(() => {
                done += 1;
                spend += rand(0.03, 0.21);
                push(b, "done", `completed: ${b.task}`);
                b.ti = (b.ti + 1) % b.tasks.length;
                b.task = b.tasks[b.ti];
                b.progress = 0.02;
                b.status = Math.random() < 0.35 ? "thinking" : "working";
                b.timer = rand(4, 8);
                b.busy = false;
              }, 6500);
            }
          } else if (b.status === "thinking" || b.status === "error" || b.status === "idle") {
            b.timer -= step;
            if (b.timer <= 0) {
              if (b.status === "error") push(b, "working", "recovered after a retry");
              if (b.status !== "idle") b.status = "working";
              b.timer = rand(6, 14);
            }
          }
        }
        nextAmbient -= step;
        if (nextAmbient <= 0) {
          const b = pick(bots.filter((x) => x.status === "working" && !x.busy));
          if (b) fx.push({ type: "stream", id: b.id });
          nextAmbient = rand(0.5, 1);
        }
        nextEvent -= step;
        if (nextEvent <= 0) {
          nextEvent = rand(2.4, 4.2);
          const idle = bots.filter((x) => !x.busy);
          const r = Math.random();
          const needsN = bots.filter((x) => x.status === "needs").length;
          const idleBot = idle.find((x) => x.status === "idle");
          const planBot = pick(idle.filter((x) => x.status === "working" && (x.progress ?? 0) < 0.5));
          const askBot = pick(idle.filter((x) => x.ask && (x.status === "working" || x.status === "thinking")));
          const errBot = pick(idle.filter((x) => x.status === "working"));
          if (r < 0.26 && idleBot) {
            idleBot.status = "working";
            idleBot.progress = 0.02;
            push(idleBot, "working", `started: ${idleBot.task}`);
          } else if (r < 0.45 && planBot) {
            planBot.status = "thinking";
            planBot.timer = rand(5, 9);
            push(planBot, "thinking", `is planning the next step: ${planBot.task}`);
          } else if (r < 0.6 && needsN < 2 && askBot) {
            askBot.status = "needs";
            askBot.approval = askBot.ask ?? null;
            push(askBot, "needs", `needs your approval: ${askBot.ask}`);
            fx.push({ type: "focus", id: askBot.id });
          } else if (r < 0.68 && errBot && !bots.some((x) => x.status === "error")) {
            errBot.status = "error";
            errBot.timer = rand(7, 10);
            push(errBot, "error", "hit an upstream timeout. Retrying.");
          } else {
            const b = pick(idle.filter((x) => x.status === "working"));
            if (b) {
              push(b, "working", `is ${Math.round((b.progress ?? 0) * 100)}% through: ${b.task}`);
              fx.push({ type: "stream", id: b.id });
            }
          }
        }
        state(snapshot());
        if (fx.length) effects(fx);
      }, step * 1000);
      return () => window.clearInterval(timer);
    },
    async approve(id) {
      const b = bots.find((x) => x.id === id);
      if (!b || b.status !== "needs" || !b.ask) return "Nothing is waiting on that agent.";
      push(b, "approved", `approved by you: ${b.ask}`);
      b.status = "working";
      b.approval = null;
      b.progress = Math.max(b.progress ?? 0, 0.6);
      return `Approved. ${b.name} will ${lower(b.ask)}.`;
    },
    async ask() {
      const running = bots.filter((b) => b.status === "working" || b.status === "thinking").length;
      const needs = bots.filter((b) => b.status === "needs" && b.ask);
      return {
        answer: `${running} agents are working right now. ${
          needs.length ? `${needs.map((b) => `${b.name} wants to ${lower(b.ask!)}`).join(", and ")}. Approve it on the floor when you're ready.` : "Nothing needs you."
        } ${done} tasks are done today. This is the sample demo, so these numbers are simulated.`,
      };
    },
  };
}

/* ------------------------------------------------------------------ recruitment floor */

export const HIRING_STAGES: FloorZone[] = [
  { key: "applied", label: "Applied" },
  { key: "graded", label: "Screened" },
  { key: "shortlisted", label: "Shortlisted" },
  { key: "l1", label: "AI interview L1" },
  { key: "l2", label: "AI interview L2" },
  { key: "human_round", label: "Your interview" },
  { key: "offer", label: "Offer" },
  { key: "hired", label: "Hired" },
];

export const HIRING_BOTS: Array<{ key: string; name: string; stage: string; role: string }> = [
  { key: "sourcing", name: "Sourcing bot", stage: "applied", role: "Brings in applicants from the job post and the BrowseJobs graded pool" },
  { key: "screener", name: "AI screener", stage: "graded", role: "Scores every CV and screening answer against the job's rubric" },
  { key: "shortlist", name: "Shortlist bot", stage: "shortlisted", role: "Applies your shortlist rule and parks borderline profiles for review" },
  { key: "interviewer_l1", name: "AI interviewer · L1", stage: "l1", role: "Runs the role-specific first-round AI interview" },
  { key: "interviewer_l2", name: "AI interviewer · L2", stage: "l2", role: "Runs the deeper technical AI round" },
  { key: "scheduler", name: "Interview scheduler", stage: "human_round", role: "Lines up your team's interview with the finalists" },
  { key: "offers", name: "Offer desk", stage: "offer", role: "Prepares the offer for your sign-off" },
  { key: "onboarding", name: "Onboarding bot", stage: "hired", role: "Tracks joining and first-day paperwork" },
];

const ROLES = ["Data Engineer", "DevOps Engineer", "Python Developer", "Data Analyst"];

export function recruitmentDemo(): ConsoleSource {
  const counts: Record<string, number> = { applied: 184, graded: 121, shortlisted: 46, l1: 28, l2: 14, human_round: 6, offer: 3, hired: 2 };
  const scores: Record<string, number> = { graded: 68, l1: 71, l2: 74 };
  const today: Record<string, number> = { applied: 23, graded: 17, shortlisted: 6, l1: 5, l2: 3, human_round: 1, offer: 0, hired: 0 };
  const bots = HIRING_BOTS.map((b) => ({
    ...b,
    id: b.key,
    status: (b.stage === "human_round" ? "needs" : b.stage === "hired" ? "idle" : "working") as FloorStatus,
    task: "",
    progress: rand(0.2, 0.8),
    approval: b.stage === "human_round" ? "Book 2 interview slots with your panel" : (null as string | null),
  }));
  let feed: FeedItem[] = [
    feedItem("Interview scheduler", "needs", "has 2 finalists ready for your interview round · Data Engineer"),
    feedItem("AI interviewer · L1", "working", "graded 3 interviews for DevOps Engineer · avg 71/100"),
    feedItem("AI screener", "working", "scored 9 new applicants for Python Developer · avg 66/100"),
    feedItem("Sourcing bot", "working", "added 6 applicants from the graded pool for Data Analyst"),
  ];
  const describe = (b: (typeof bots)[number]) => {
    const role = pick(ROLES)!;
    switch (b.stage) {
      case "applied": return `Reviewing new applicants for ${role}`;
      case "graded": return `Scoring CVs for ${role}`;
      case "shortlisted": return `Applying the 70+ shortlist rule for ${role}`;
      case "l1": return `Interviewing ${Math.max(1, Math.round(counts.l1 / 6))} candidates for ${role}`;
      case "l2": return `Running technical rounds for ${role}`;
      case "human_round": return counts.human_round ? `${counts.human_round} finalists waiting for your interview` : "Waiting for finalists";
      case "offer": return counts.offer ? `${counts.offer} offers waiting for sign-off` : "No offers open";
      default: return counts.hired ? `Onboarding ${counts.hired} new hires` : "No joiners yet";
    }
  };
  bots.forEach((b) => (b.task = describe(b)));
  const metrics = (stage: string) => {
    const m = [{ label: "In stage", value: String(counts[stage]) }, { label: "Today", value: `+${today[stage]}` }];
    if (scores[stage]) m.push({ label: "Avg score", value: `${scores[stage]}` });
    return m;
  };
  const snapshot = (): ConsoleState => {
    const inPipeline = HIRING_STAGES.filter((s) => s.key !== "hired").reduce((n, s) => n + counts[s.key], 0);
    const needs = bots.filter((b) => b.status === "needs").length;
    return {
      agents: bots.map(({ id, name, stage, status, task, progress, approval }) => ({ id, name, zone: stage, status, task, progress, approval })),
      zones: HIRING_STAGES,
      kpis: [
        { key: "roles", label: "Open roles", value: String(ROLES.length) },
        { key: "pipeline", label: "In pipeline", value: String(inPipeline) },
        { key: "interviews", label: "AI interviews", value: String(counts.l1 + counts.l2) },
        { key: "needs", label: "Needs you", value: String(needs), hot: needs > 0 },
        { key: "hired", label: "Hired", value: String(counts.hired), note: "SAMPLE" },
      ],
      feed,
      sideTitle: "Hiring bots",
      side: bots.map((b) => ({ id: b.id, title: b.name, subtitle: b.task, status: b.status, metrics: metrics(b.stage) })),
      stages: HIRING_STAGES.map((s) => ({ key: s.key, label: s.label, count: counts[s.key], sub: scores[s.key] ? `avg ${scores[s.key]}` : undefined })),
      sample: true,
    };
  };
  const push = (agent: string, status: FeedStatus, text: string) => {
    feed = [feedItem(agent, status, text), ...feed].slice(0, 8);
  };
  const move = (i: number, n: number, fx: FloorEffect[]) => {
    const from = HIRING_STAGES[i].key;
    const to = HIRING_STAGES[i + 1].key;
    const k = Math.min(n, counts[from]);
    if (k <= 0) return 0;
    counts[from] -= k;
    counts[to] += k;
    today[to] += k;
    fx.push({ type: "flow", from, to, count: k });
    return k;
  };

  return {
    start({ state, effects }) {
      state(snapshot());
      const timer = window.setInterval(() => {
        const fx: FloorEffect[] = [];
        const role = pick(ROLES)!;
        const r = Math.random();
        if (r < 0.22) {
          const n = Math.round(rand(2, 7));
          counts.applied += n;
          today.applied += n;
          push("Sourcing bot", "working", `added ${n} applicants for ${role}`);
          fx.push({ type: "stream", id: "sourcing" });
        } else if (r < 0.44) {
          const k = move(0, Math.round(rand(2, 6)), fx);
          scores.graded = Math.round(rand(62, 74));
          if (k) push("AI screener", "working", `scored ${k} applicants for ${role} · avg ${scores.graded}/100`);
        } else if (r < 0.58) {
          const k = move(1, Math.round(rand(1, 3)), fx);
          if (k) push("Shortlist bot", "working", `shortlisted ${k} candidates scoring 70+ for ${role}`);
        } else if (r < 0.7) {
          const k = move(2, Math.round(rand(1, 3)), fx);
          scores.l1 = Math.round(rand(64, 78));
          if (k) push("AI interviewer · L1", "working", `invited ${k} candidates to the first AI round for ${role}`);
        } else if (r < 0.8) {
          const k = move(3, 1, fx);
          scores.l2 = Math.round(rand(68, 82));
          if (k) push("AI interviewer · L2", "working", `moved a candidate to the technical round for ${role} · L1 score ${scores.l1}`);
        } else if (r < 0.9) {
          const k = move(4, 1, fx);
          if (k) {
            const s = bots.find((b) => b.key === "scheduler")!;
            s.status = "needs";
            s.approval = `Book ${counts.human_round} interview slots with your panel`;
            push("Interview scheduler", "needs", `has a new finalist for ${role} ready for your interview`);
            fx.push({ type: "focus", id: "scheduler" });
          }
        } else {
          const b = pick(bots.filter((x) => x.status === "working"));
          if (b) fx.push({ type: "stream", id: b.id });
        }
        bots.forEach((b) => {
          if (b.stage !== "human_round" && b.stage !== "offer") b.status = counts[b.stage] > 0 ? "working" : "idle";
          b.progress = Math.min(1, (b.progress ?? 0) + rand(0.01, 0.05));
          if ((b.progress ?? 0) >= 1) b.progress = 0.05;
          b.task = describe(b);
        });
        const offer = bots.find((b) => b.key === "offers")!;
        offer.status = counts.offer > 0 ? "needs" : "idle";
        offer.approval = counts.offer > 0 ? "Release the offer letter you signed off" : null;
        if (Math.random() < 0.3) {
          const b = pick(bots.filter((x) => x.status === "working"));
          if (b) fx.push({ type: "stream", id: b.id });
        }
        state(snapshot());
        if (fx.length) effects(fx);
      }, 2600);
      return () => window.clearInterval(timer);
    },
    async approve(id) {
      const fxStage = id === "scheduler" ? 5 : id === "offers" ? 6 : -1;
      if (fxStage < 0) return "Nothing is waiting on that bot.";
      const n = id === "scheduler" ? Math.min(2, counts.human_round) : 1;
      counts[HIRING_STAGES[fxStage].key] -= n;
      counts[HIRING_STAGES[fxStage + 1].key] += n;
      const b = bots.find((x) => x.key === id)!;
      b.status = "working";
      b.approval = null;
      push(b.name, "approved", id === "scheduler" ? `booked ${n} interview slots after your approval` : "released the offer after your sign-off");
      return id === "scheduler" ? `Approved. ${n} interview slots booked with your panel.` : "Approved. The offer letter goes out now.";
    },
    async ask() {
      return {
        answer: `${HIRING_STAGES.filter((s) => s.key !== "hired").reduce((n, s) => n + counts[s.key], 0)} candidates are in the pipeline across ${ROLES.length} roles. ${counts.l1 + counts.l2} are in AI interviews, ${counts.human_round} finalists are waiting for your interview, and ${counts.offer} offers need your sign-off. This is the sample demo.`,
      };
    },
  };
}
