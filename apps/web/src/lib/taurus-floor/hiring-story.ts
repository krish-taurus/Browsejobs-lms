/**
 * The Taurus hiring story — a scripted, start-to-finish walkthrough of one
 * role being filled through WhatsApp, played on the 3D hiring floor. It is an
 * illustration with sample numbers (labelled on screen), not live data.
 *
 * Every step that contacts candidates, releases an offer or runs a check
 * waits for the employer's "yes" in the chat. The yes can be tapped, or it
 * is picked automatically after a few seconds so the story also plays
 * hands-free (and records cleanly as a video).
 */
import type { FloorStatus, FloorZone } from "./floor";
import type { ChatMessage, ConsoleCaption, ConsoleSource, ConsoleState, FeedItem, FeedStatus, FloorEffect } from "./types";

export const STORY_STAGES: FloorZone[] = [
  { key: "request", label: "Request" },
  { key: "sourcing", label: "Sourcing" },
  { key: "screening", label: "Screening" },
  { key: "calls", label: "AI calls" },
  { key: "shortlist", label: "Shortlist" },
  { key: "l1", label: "L1" },
  { key: "l2", label: "L2" },
  { key: "bgv", label: "Pre-BGV (optional)" },
  { key: "human", label: "Human round" },
  { key: "offer", label: "Offer" },
];

const BOTS: Array<{ id: string; name: string; zone: string }> = [
  { id: "concierge", name: "Taurus on WhatsApp", zone: "request" },
  { id: "sourcing", name: "Sourcing bot", zone: "sourcing" },
  { id: "screener", name: "Screening agent", zone: "screening" },
  { id: "caller", name: "AI caller", zone: "calls" },
  { id: "shortlist", name: "Shortlist bot", zone: "shortlist" },
  { id: "l1", name: "AI interviewer · L1", zone: "l1" },
  { id: "l2", name: "AI interviewer · L2", zone: "l2" },
  { id: "bgv", name: "BGV agent", zone: "bgv" },
  { id: "scheduler", name: "Interview scheduler", zone: "human" },
  { id: "offers", name: "Offer desk", zone: "offer" },
];

export const STORY_STEPS = 10;
const AUTO_REPLY_MS = 5200;

class Cancelled extends Error {}

export function hiringStory(): ConsoleSource {
  let emitState: ((s: ConsoleState) => void) | null = null;
  let emitFx: ((e: FloorEffect[]) => void) | null = null;
  let run = 0;
  let seq = 0;
  let pendingReply: ((text: string) => void) | null = null;
  let autoTimer = 0;
  let autoTick = 0;

  const fresh = () => ({
    counts: Object.fromEntries(STORY_STAGES.map((s) => [s.key, 0])) as Record<string, number>,
    bots: Object.fromEntries(BOTS.map((b) => [b.id, { status: "idle" as FloorStatus, task: "Waiting for a request", approval: null as string | null }])),
    kpi: { screened: 0, calls: 0, shortlisted: 0, interviewing: 0, offers: 0 },
    feed: [] as FeedItem[],
    chat: [] as ChatMessage[],
    typing: false,
    replies: [] as string[],
    autoIn: undefined as number | undefined,
    caption: undefined as ConsoleCaption | undefined,
    ended: false,
  });
  let S = fresh();

  const snapshot = (): ConsoleState => ({
    agents: BOTS.map((b) => ({ id: b.id, name: b.name, zone: b.zone, status: S.bots[b.id].status, task: S.bots[b.id].task, progress: 0.5, approval: S.bots[b.id].approval })),
    zones: STORY_STAGES,
    kpis: [
      { key: "screened", label: "CVs screened", value: String(S.kpi.screened) },
      { key: "calls", label: "AI calls", value: String(S.kpi.calls) },
      { key: "shortlisted", label: "Shortlisted", value: String(S.kpi.shortlisted) },
      { key: "interviewing", label: "In interviews", value: String(S.kpi.interviewing) },
      { key: "offers", label: "Offers released", value: String(S.kpi.offers), note: "SAMPLE" },
    ],
    feed: S.feed,
    sideTitle: "",
    side: [],
    stages: STORY_STAGES.map((s) => ({ key: s.key, label: s.label, count: S.counts[s.key] })),
    sample: true,
    chat: { title: "Taurus · Hiring", messages: S.chat, typing: S.typing, replies: S.replies, autoIn: S.autoIn },
    caption: S.caption,
    ended: S.ended,
  });
  const push = () => emitState?.(snapshot());
  const fx = (...e: FloorEffect[]) => emitFx?.(e);

  /* ---------------------------------------------------------------- script helpers */
  const story = (my: number) => {
    const alive = () => {
      if (my !== run) throw new Cancelled();
    };
    const wait = (ms: number) =>
      new Promise<void>((resolve, reject) => {
        window.setTimeout(() => (my === run ? resolve() : reject(new Cancelled())), ms);
      });
    const step = (n: number, title: string, text: string) => {
      S.caption = { step: n, total: STORY_STEPS, title, text };
      push();
    };
    const bot = (id: string, status: FloorStatus, task: string, approval: string | null = null) => {
      S.bots[id] = { status, task, approval };
      push();
    };
    const log = (agent: string, status: FeedStatus, text: string) => {
      S.feed = [{ id: `s${++seq}`, at: new Date().toISOString(), agent, status, text }, ...S.feed].slice(0, 8);
      push();
    };
    const msg = (from: ChatMessage["from"], text: string, attachment?: ChatMessage["attachment"]) => {
      S.chat = [...S.chat, { id: `m${++seq}`, from, text, at: new Date().toISOString(), attachment }];
      push();
    };
    const say = async (text: string, attachment?: ChatMessage["attachment"], typingMs = 1300) => {
      S.typing = true;
      push();
      await wait(typingMs);
      S.typing = false;
      msg("taurus", text, attachment);
    };
    const ask = (replies: string[]) =>
      new Promise<string>((resolve, reject) => {
        alive();
        S.replies = replies;
        S.autoIn = Math.round(AUTO_REPLY_MS / 1000);
        push();
        const done = (text: string) => {
          window.clearTimeout(autoTimer);
          window.clearInterval(autoTick);
          pendingReply = null;
          if (my !== run) return reject(new Cancelled());
          S.replies = [];
          S.autoIn = undefined;
          msg("employer", text);
          resolve(text);
        };
        pendingReply = done;
        autoTick = window.setInterval(() => {
          if (S.autoIn !== undefined && S.autoIn > 1) {
            S.autoIn -= 1;
            push();
          }
        }, 1000);
        autoTimer = window.setTimeout(() => done(replies[0]), AUTO_REPLY_MS);
      });
    /** Animate a stage count towards a target, sending candidate tokens along the ring. */
    const count = async (stage: string, to: number, ms: number, from?: string, kpi?: keyof typeof S.kpi) => {
      const start = S.counts[stage];
      const ticks = Math.max(1, Math.round(ms / 220));
      for (let i = 1; i <= ticks; i++) {
        await wait(ms / ticks);
        const v = Math.round(start + ((to - start) * i) / ticks);
        const gained = v - S.counts[stage];
        S.counts[stage] = v;
        if (kpi) S.kpi[kpi] = v;
        if (from && gained > 0) fx({ type: "flow", from, to: stage, count: Math.min(3, gained) });
        if (i % 3 === 0) fx({ type: "stream", id: BOTS.find((b) => b.zone === stage)!.id });
        push();
      }
    };
    return { alive, wait, step, bot, log, msg, say, ask, count };
  };

  async function play(my: number) {
    const { wait, step, bot, log, msg, say, ask, count } = story(my);
    await wait(1400);

    step(1, "The request", "An employer messages Taurus on WhatsApp, the same way they'd message a recruiter.");
    msg("employer", "Hi Taurus 👋 I need 5 software developers in Bangalore. 3–6 years, React and Node. Budget up to ₹24 LPA.");
    S.counts.request = 5;
    bot("concierge", "working", "Reading the brief: 5 × Software Developer, Bangalore");
    await say("Got it: 5 × Software Developer · Bangalore · 3–6 yrs · React, Node · up to ₹24 LPA. Starting the search now.");
    log("Taurus on WhatsApp", "working", "opened a role: 5 × Software Developer, Bangalore");
    fx({ type: "deliver", id: "concierge" });
    await wait(800);
    bot("concierge", "idle", "Waiting for your next message");

    step(2, "Sourcing", "The sourcing bot pulls matching profiles from the BrowseJobs graded pool and the job post.");
    bot("sourcing", "working", "Searching the graded pool and job boards");
    await count("sourcing", 212, 4200, "request");
    log("Sourcing bot", "done", "found 212 profiles for Software Developer");
    bot("sourcing", "idle", "212 profiles found");

    step(3, "CV screening", "The screening agent scores every CV against the brief and keeps the ones that fit.");
    bot("screener", "working", "Scoring 212 CVs against your brief");
    S.kpi.screened = 0;
    for (let i = 1; i <= 8; i++) {
      await wait(330);
      S.kpi.screened = Math.round((212 * i) / 8);
      push();
    }
    await count("screening", 38, 2200, "sourcing");
    log("Screening agent", "done", "matched 38 of 212 CVs to the brief · avg fit 74/100");
    bot("screener", "idle", "38 strong matches");
    await say("I screened 212 CVs. 38 match your brief (average fit 74/100). Shall I get in touch with them?");
    const first = await ask(["Yes, go ahead", "Show me the list first"]);
    if (first !== "Yes, go ahead") {
      await say("Here they are, ranked by fit.", { name: "Matched_CVs_Software_Developer_BLR.pdf", meta: "38 profiles · PDF" });
      await ask(["Yes, contact them"]);
    }

    step(4, "AI screening calls", "The AI caller phones each candidate to check interest, notice period and expected salary.");
    bot("caller", "working", "Calling 38 candidates");
    await say("Calling them now. I'll check interest, notice period and salary expectations.", undefined, 900);
    for (let i = 1; i <= 10; i++) {
      await wait(380);
      S.kpi.calls = Math.round((38 * i) / 10);
      if (i % 2 === 0) fx({ type: "stream", id: "caller" });
      push();
    }
    log("AI caller", "working", "reached 31 of 38 candidates · 24 are interested");
    await count("calls", 18, 2000, "screening");
    log("AI caller", "done", "18 candidates cleared the screening call");
    bot("caller", "idle", "38 calls done · 18 cleared");

    step(5, "Shortlist", "The shortlist goes back to the employer on WhatsApp, with notes from every call.");
    bot("shortlist", "working", "Building the shortlist with call notes");
    await count("shortlist", 18, 1600, "calls", "shortlisted");
    await say("18 candidates cleared the screening call. Here's your shortlist.", { name: "Shortlist_Software_Developer_BLR.pdf", meta: "18 CVs · call notes · PDF" });
    bot("shortlist", "idle", "Shortlist sent on WhatsApp");
    await say("Do you want me to start the L1 round?", undefined, 900);
    await ask(["Yes, start L1"]);

    step(6, "L1 round", "Everyone on the shortlist gets a link to a role-specific AI interview, graded once it's done.");
    bot("l1", "working", "Sending L1 interview links to 18 candidates");
    await say("Sent L1 AI interview links to all 18.", undefined, 900);
    await count("l1", 18, 2200, "shortlist", "interviewing");
    log("AI interviewer · L1", "working", "16 of 18 completed the L1 interview");
    await wait(900);
    S.counts.l1 = 9;
    push();
    log("AI interviewer · L1", "done", "9 cleared L1 · avg 72/100");
    bot("l1", "idle", "9 cleared L1");

    step(7, "L2 round", "Candidates who clear L1 move straight to a deeper technical round.");
    bot("l2", "working", "Running technical rounds for 9 candidates");
    await count("l2", 9, 1800, "l1");
    S.kpi.interviewing = 9;
    await wait(1000);
    S.counts.l2 = 6;
    push();
    log("AI interviewer · L2", "done", "6 cleared L2 · avg 78/100");
    bot("l2", "idle", "6 cleared L2");
    await say("6 candidates cleared L2. Shall I run a pre-BGV check? It's optional.");
    const bgvChoice = await ask(["Yes, run pre-BGV", "Skip pre-BGV"]);

    step(8, "Pre-BGV · optional", "If you opt in, the BGV agent checks EPFO employment history and DigiLocker documents, with each candidate's consent.");
    let pick = "Book all 6";
    if (bgvChoice === "Skip pre-BGV") {
      await say("Skipping pre-BGV for this role.", undefined, 900);
      bot("bgv", "idle", "Skipped for this role");
    } else {
    bot("bgv", "working", "Checking EPFO history and DigiLocker documents");
    await count("bgv", 6, 2400, "l2");
    log("BGV agent", "done", "5 verified · 1 flagged for review (employment dates don't match)");
    bot("bgv", "idle", "5 verified · 1 flagged");
    await say("5 verified. 1 is flagged for your review because the employment dates don't match. Nobody is rejected automatically. Who should I book for your interview round?");
    pick = await ask(["Book the 5 verified", "Book all 6"]);
    }
    const finalists = pick === "Book all 6" ? 6 : 5;

    step(9, "Human round", "Your panel interviews the finalists. Taurus books the slots and collects the decision.");
    bot("scheduler", "working", `Booking ${finalists} interviews with your panel`);
    await count("human", finalists, 1600, bgvChoice === "Skip pre-BGV" ? "l2" : "bgv");
    await say(`Booked ${finalists} interviews with your panel for Thursday, 11:00 to 15:00.`);
    bot("scheduler", "idle", `${finalists} interviews booked`);
    await wait(1600);
    log("Interview scheduler", "done", "panel selected 4 candidates");
    bot("offers", "needs", "Offer letters ready for 4 candidates", "Release 4 offer letters");
    fx({ type: "focus", id: "offers" });
    await say("Your panel selected 4. Offer letters are ready. Shall I release them?");
    const release = await ask(["Approve offers", "Hold for now"]);
    if (release !== "Approve offers") {
      await say("Holding. Nothing goes out until you approve.", undefined, 900);
      await ask(["Approve offers"]);
    }

    step(10, "Offer", "Only after the employer approves does the offer desk release the letters.");
    bot("offers", "working", "Releasing 4 offer letters");
    await count("offer", 4, 1400, "human", "offers");
    fx({ type: "deliver", id: "offers" });
    log("Offer desk", "approved", "released 4 offer letters after your approval");
    await say("Offer letters released to 4 candidates. I'll track acceptances and joining dates for you.");
    bot("offers", "idle", "4 offers released");
    await wait(1200);
    S.caption = {
      step: 10,
      total: STORY_STEPS,
      title: "212 CVs to 4 offers",
      text: "Every call, round, check and offer waited for a yes on WhatsApp.",
    };
    S.ended = true;
    S.replies = ["Replay the story"];
    S.autoIn = undefined;
    push();
  }

  const begin = () => {
    window.clearTimeout(autoTimer);
    window.clearInterval(autoTick);
    pendingReply = null;
    const my = ++run;
    S = fresh();
    push();
    play(my).catch((e) => {
      if (!(e instanceof Cancelled)) throw e;
    });
  };

  return {
    start({ state, effects }) {
      emitState = state;
      emitFx = effects;
      begin();
      return () => {
        run++;
        window.clearTimeout(autoTimer);
        window.clearInterval(autoTick);
        emitState = null;
        emitFx = null;
      };
    },
    reply(text) {
      if (S.ended) {
        begin();
        return;
      }
      pendingReply?.(text);
    },
    restart: begin,
    async approve(id) {
      if (id === "offers" && pendingReply && S.replies.includes("Approve offers")) {
        pendingReply("Approve offers");
        return "Approved. The offer desk releases the letters now.";
      }
      return "Nothing is waiting on that bot right now.";
    },
    async ask() {
      const c = S.counts;
      return {
        answer: S.ended
          ? "This role went from 212 CVs to 4 offers. Every step waited for your yes on WhatsApp. This is the sample story."
          : `So far: ${S.kpi.screened} CVs screened, ${S.kpi.calls} AI calls made, ${S.kpi.shortlisted} shortlisted, ${c.l1 + c.l2} in interview rounds. This is the sample story.`,
      };
    },
  };
}
