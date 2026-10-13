/**
 * The landing page's workforce floor: a made-up business running the five
 * crews the page describes (social media, lead generation, support,
 * recruitment, operations). Every number is invented and the floor labels it
 * SAMPLE — nothing here reads real customer data. Ported from the
 * BrowseJobs Taurus demo; the dashboard floor uses real data (live.ts).
 */
import type { FloorAgent, FloorStatus, FloorZone } from "./floor";
import type { ConsoleSource, ConsoleState, FeedItem, FeedStatus, FloorEffect } from "./types";

const pick = <T,>(a: T[]): T | undefined => a[Math.floor(Math.random() * a.length)];
const rand = (a: number, b: number) => a + Math.random() * (b - a);
const lower = (s: string) => s.charAt(0).toLowerCase() + s.slice(1);
let seq = 0;
const feedItem = (who: string, status: FeedStatus, text: string): FeedItem => ({
  id: `f${++seq}`,
  at: new Date().toISOString(),
  who,
  status,
  text,
});

/* ------------------------------------------------------------------ ops floor */

export const DEMO_ZONES: FloorZone[] = [
  { key: "social", label: "Social media" },
  { key: "leads", label: "Lead gen" },
  { key: "support", label: "Support" },
  { key: "hiring", label: "Recruitment" },
  { key: "ops", label: "Operations" },
];

type DemoEmployee = FloorAgent & { tasks: string[]; ti: number; ask?: string; rate: number; timer: number; busy: boolean };

/** One AI Employee per crew role from the landing page's use cases. */
const DEMO_SEED: Array<[string, string, FloorStatus, string[], string?]> = [
  ["Planner", "social", "working", ["Planning next week's 12 posts", "Mapping launches to the calendar", "Picking each platform's best time"]],
  ["Writer", "social", "thinking", ["Drafting a 7-slide LinkedIn carousel", "Writing 5 Instagram captions", "Turning the blog into an X thread"], "Publish the carousel"],
  ["Brand reviewer", "social", "working", ["Checking 9 drafts against the brand voice", "Flagging 2 off-brand claims", "Fixing tone on 3 captions"]],
  ["Community", "social", "working", ["Replying to 14 comments", "Answering 6 DMs", "Escalating 1 complaint"]],
  ["Researcher", "leads", "working", ["Finding 60 companies that fit the profile", "Listing decision makers", "Checking hiring signals"]],
  ["Qualifier", "leads", "working", ["Scoring 42 new leads", "Enriching 18 company records", "Dropping 7 poor fits"]],
  ["Outreach", "leads", "needs", ["Writing 18 first emails", "Sending day-3 follow-ups", "Personalising a sequence"], "Send 18 first emails"],
  ["Booker", "leads", "idle", ["Answering 4 replies", "Booking 2 intro calls", "Writing call briefs"]],
  ["Front desk", "support", "working", ["Answering 23 chats", "Looking up 9 orders", "Citing the returns policy"]],
  ["Specialist", "support", "working", ["Handling 3 refund requests", "Fixing a billing error", "Closing 6 tickets"], "Refund $60 to a customer"],
  ["Quality", "support", "idle", ["Reviewing this week's conversations", "Proposing 3 help-article fixes", "Scoring tone and accuracy"]],
  ["Sourcer", "hiring", "working", ["Drafting the Data Engineer post", "Searching 300 profiles", "Shortlisting 25"]],
  ["Screener", "hiring", "thinking", ["Ranking 120 applicants", "Checking must-have skills", "Building the shortlist"], "Start round one for 12 people"],
  ["Interviewer", "hiring", "working", ["Interviewing a candidate", "Scoring 4 interviews", "Writing reports"]],
  ["Data", "ops", "working", ["Syncing sales and stock data", "Refreshing the orders sheet", "Pulling support numbers"]],
  ["Analyst", "ops", "working", ["Writing the weekly report", "Explaining a 12% dip in orders", "Charting this month"]],
  ["Ops", "ops", "error", ["Chasing 3 late orders", "Messaging stock owners", "Raising a purchase order"], "Raise a purchase order"],
];

export function workforceDemo(): ConsoleSource {
  const bots: DemoEmployee[] = DEMO_SEED.map(([name, zone, status, tasks, ask], i) => ({
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
  bots.find((b) => b.name === "Analyst")!.progress = 0.97;
  let done = 27;
  let spend = 9.44;
  let feed: FeedItem[] = [
    feedItem("Writer", "thinking", "is planning the next step: Drafting a 7-slide LinkedIn carousel"),
    feedItem("Qualifier", "working", "is 41% through: Scoring 42 new leads"),
    feedItem("Outreach", "needs", "needs your approval: Send 18 first emails"),
    feedItem("Ops", "error", "could not reach the supplier sheet. Retrying."),
    feedItem("Interviewer", "done", "completed: Scoring 4 interviews"),
  ];

  const snapshot = (): ConsoleState => {
    const running = bots.filter((b) => b.status === "working" || b.status === "thinking");
    const needs = bots.filter((b) => b.status === "needs").length;
    return {
      employees: bots.map(({ id, name, zone, status, task, progress, approval }) => ({ id, name, zone, status, task, progress, approval })),
      zones: DEMO_ZONES,
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

  const push = (b: DemoEmployee, status: FeedStatus, text: string) => {
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
            push(errBot, "error", "could not reach a connected tool. Retrying.");
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
      if (!b || b.status !== "needs" || !b.ask) return "Nothing is waiting on that AI Employee.";
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
        answer: `${running} AI Employees are working right now. ${
          needs.length ? `${needs.map((b) => `${b.name} wants to ${lower(b.ask!)}`).join(", and ")}. Approve it on the floor when you're ready.` : "Nothing needs you."
        } ${done} tasks are done today. This is the sample demo, so these numbers are simulated.`,
      };
    },
  };
}
