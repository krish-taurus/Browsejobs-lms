/**
 * Scripted mission-control demo. Nothing here is a real candidate, company,
 * or hiring result. The page plays this timeline on a loop.
 */

export const LOOP_MS = 88_000;

export const PHASES = [
  { id: "job", label: "Job raised" },
  { id: "sourcing", label: "Sourcing" },
  { id: "calls", label: "AI calls" },
  { id: "l1", label: "L1" },
  { id: "l2", label: "L2" },
  { id: "human", label: "Human" },
  { id: "bgv", label: "Pre-BGV" },
  { id: "offer", label: "Offer" },
  { id: "engage", label: "Joining" },
] as const;

export type PhaseId = (typeof PHASES)[number]["id"];

export type Source = "BrowseJobs" | "Your Excel" | "Email";

export type Person = {
  id: string;
  name: string;
  initials: string;
  source: Source;
  phone: string;
  match: number;
  notice: string;
  enter: Partial<Record<PhaseId, number>>;
  callOutcome?: string;
  l1?: number;
  l2?: number;
  dropout?: number;
};

export const PEOPLE: readonly Person[] = [
  {
    id: "asha",
    name: "Sample Asha",
    initials: "SA",
    source: "BrowseJobs",
    phone: "+91 98••• ••21",
    match: 91,
    notice: "30 days",
    enter: { sourcing: 8_500, calls: 32_000, l1: 49_000, l2: 59_000, human: 67_000, bgv: 74_000, offer: 80_000, engage: 84_500 },
    callOutcome: "Interested · notice 30 days",
    l1: 82,
    l2: 86,
  },
  {
    id: "rahul",
    name: "Sample Rahul",
    initials: "SR",
    source: "Your Excel",
    phone: "+91 97••• ••44",
    match: 88,
    notice: "30 days",
    enter: { sourcing: 8_000, calls: 27_000, l1: 48_000, l2: 58_000, human: 66_000, bgv: 73_000, offer: 79_000, engage: 84_000 },
    callOutcome: "Interested · notice 30 days",
    l1: 78,
    l2: 74,
    dropout: 74,
  },
  {
    id: "meera",
    name: "Sample Meera",
    initials: "SM",
    source: "Email",
    phone: "+91 96••• ••18",
    match: 84,
    notice: "15 days",
    enter: { sourcing: 10_000, calls: 31_000, l1: 51_000, l2: 60_000, human: 68_000 },
    callOutcome: "Interested · notice 15 days",
    l1: 80,
    l2: 77,
  },
  {
    id: "vikram",
    name: "Sample Vikram",
    initials: "SV",
    source: "BrowseJobs",
    phone: "+91 90••• ••07",
    match: 79,
    notice: "60 days",
    enter: { sourcing: 11_000, calls: 30_000, l1: 50_000 },
    callOutcome: "Interested · notice 60 days",
    l1: 61,
  },
  {
    id: "neha",
    name: "Sample Neha",
    initials: "SN",
    source: "Your Excel",
    phone: "+91 99••• ••63",
    match: 76,
    notice: "Immediate",
    enter: { sourcing: 12_000, calls: 28_000 },
    callOutcome: "Not interested",
  },
  {
    id: "arjun",
    name: "Sample Arjun",
    initials: "SJ",
    source: "BrowseJobs",
    phone: "+91 98••• ••90",
    match: 74,
    notice: "45 days",
    enter: { sourcing: 13_000, calls: 29_000 },
    callOutcome: "No answer",
  },
  {
    id: "priya",
    name: "Sample Priya",
    initials: "SP",
    source: "Email",
    phone: "+91 97••• ••12",
    match: 71,
    notice: "30 days",
    enter: { sourcing: 14_000, calls: 33_000, l1: 52_000 },
    callOutcome: "Interested · notice 30 days",
    l1: 64,
  },
  {
    id: "kabir",
    name: "Sample Kabir",
    initials: "SK",
    source: "BrowseJobs",
    phone: "+91 96••• ••55",
    match: 68,
    notice: "90 days",
    enter: { sourcing: 15_000 },
  },
];

export type FeedItem = { at: number; bot: string; text: string };
export type ChatLine = { at: number; from: "hr" | "bot"; text: string; voice?: boolean };

export const FEED: readonly FeedItem[] = [
  { at: 1_200, bot: "WhatsApp", text: "Voice note from HR · 0:08" },
  { at: 4_000, bot: "Job bot", text: "Reading the note. Role, city, openings." },
  { at: 6_800, bot: "Job bot", text: "Job created · Backend engineer · Bengaluru · 2 openings" },
  { at: 9_000, bot: "Screening bot", text: "Searching BrowseJobs and your Excel." },
  { at: 16_000, bot: "Screening bot", text: "Matched 142 CVs, 18 above 80%." },
  { at: 20_500, bot: "Mission control", text: "Waiting for HR · start reaching out?" },
  { at: 26_500, bot: "Call bot", text: "HR said yes. Dialling the top matches." },
  { at: 28_000, bot: "Call bot", text: "Speaking with Sample Rahul · interested, notice 30 days." },
  { at: 36_000, bot: "Call bot", text: "Sample Neha declined. Sample Arjun did not pick up." },
  { at: 44_000, bot: "Call bot", text: "Spoke to 6. 4 interested. Asking HR about L1." },
  { at: 48_500, bot: "Interview bot", text: "L1 open for 4 people. Bar is 70." },
  { at: 56_000, bot: "Interview bot", text: "L1: 4 attended, 3 cleared." },
  { at: 60_500, bot: "Interview bot", text: "L2 in progress. Score dials updating." },
  { at: 66_500, bot: "Scheduler", text: "Asking the interviewer for a slot." },
  { at: 70_000, bot: "Scheduler", text: "Tue 11:00 taken. Meeting link sent to three people." },
  { at: 74_500, bot: "BGV bot", text: "Pre-BGV for Sample Asha and Sample Rahul. Consent on file." },
  { at: 77_500, bot: "BGV bot", text: "Employment history matched. Nothing failed." },
  { at: 80_500, bot: "Offer bot", text: "Offer emailed from your template." },
  { at: 85_000, bot: "Engagement bot", text: "Joining chat open. Sample Rahul went quiet." },
  { at: 86_200, bot: "Engagement bot", text: "Dropout risk 74 · Sample Rahul · alert sent to HR." },
];

export const CHAT: readonly ChatLine[] = [
  { at: 800, from: "hr", voice: true, text: "I need backend engineers, Bengaluru, two people, about 18 LPA." },
  { at: 3_200, from: "bot", text: "Got the voice note. Reading it now." },
  { at: 6_200, from: "bot", text: "Role: Backend engineer. City: Bengaluru. Openings: 2. Budget: ₹18 LPA. Reply YES to search." },
  { at: 7_400, from: "hr", text: "YES" },
  { at: 16_400, from: "bot", text: "Top matches are on your desk. 18 of 142 are above 80%. Sources: BrowseJobs, your Excel, email." },
  { at: 20_800, from: "bot", text: "Shall I start reaching out to the top 6?" },
  { at: 26_200, from: "hr", text: "Yes, call them." },
  { at: 44_200, from: "bot", text: "I spoke to 6. 4 are interested. Shall I set up L1?" },
  { at: 47_800, from: "hr", text: "Yes." },
  { at: 56_400, from: "bot", text: "L1: 4 attended, 3 cleared. Send L2?" },
  { at: 58_600, from: "hr", text: "Send L2." },
  { at: 65_800, from: "bot", text: "2 cleared L1 and L2. Set up a human round?" },
  { at: 67_200, from: "hr", text: "Yes. Ask Priya on the team." },
  { at: 70_400, from: "bot", text: "Tue 11:00 is booked. Link sent to the candidate, the interviewer, and you." },
  { at: 73_200, from: "bot", text: "Run pre-BGV on Sample Asha and Sample Rahul?" },
  { at: 74_200, from: "hr", text: "Yes." },
  { at: 78_000, from: "bot", text: "Pre-BGV: identity matched, employment history matched. Nothing failed. Shall we offer?" },
  { at: 79_600, from: "hr", text: "Yes, send the offer." },
  { at: 81_200, from: "bot", text: "Offer emailed from your template. I will tell you when they reply." },
  { at: 86_400, from: "bot", text: "Alert: Sample Rahul may not join. Risk 74. He stopped replying after the offer." },
];

const CALL_LINES: Record<string, readonly { at: number; text: string }[]> = {
  rahul: [
    { at: 27_400, text: "Hello, this is the BrowseJobs assistant calling for a backend role in Bengaluru. This call is recorded." },
    { at: 30_200, text: "Sample Rahul: Yes, I am open to a conversation." },
    { at: 33_000, text: "Notice period is 30 days. Can start after that." },
  ],
  asha: [
    { at: 32_400, text: "Calling Sample Asha. Disclosed as an AI call." },
    { at: 35_500, text: "Sample Asha: Interested. Notice is 30 days." },
  ],
};

export type AgentStatus = "idle" | "working" | "waiting";

export type AgentCard = {
  id: string;
  name: string;
  status: AgentStatus;
  detail: string;
  live?: { who: string; elapsed: string; line: string };
};

export type MissionSnapshot = {
  elapsed: number;
  jobLive: boolean;
  approvalVisible: boolean;
  feed: FeedItem[];
  chat: ChatLine[];
  agents: AgentCard[];
  speakingId: string | null;
  activePhase: PhaseId;
  dropoutAlert: boolean;
};

function phaseOf(person: Person, t: number): PhaseId | null {
  let current: PhaseId | null = null;
  let at = -1;
  for (const phase of PHASES) {
    const entered = person.enter[phase.id];
    if (entered !== undefined && entered <= t && entered >= at) {
      current = phase.id;
      at = entered;
    }
  }
  return current;
}

export function peopleIn(phase: PhaseId, t: number): Person[] {
  return PEOPLE.filter((person) => phaseOf(person, t) === phase);
}

function clock(ms: number): string {
  const s = Math.max(0, Math.floor(ms / 1000));
  return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, "0")}`;
}

function latestLine(id: string, t: number): string {
  const lines = CALL_LINES[id] ?? [];
  const hit = [...lines].reverse().find((line) => line.at <= t);
  return hit?.text ?? "Ringing…";
}

export function snapshot(elapsed: number, autonomous: boolean): MissionSnapshot {
  const t = ((elapsed % LOOP_MS) + LOOP_MS) % LOOP_MS;
  const speakingId = t >= 27_000 && t < 36_000 ? "rahul" : t >= 36_000 && t < 40_000 ? "asha" : null;
  const approvalVisible = !autonomous && t >= 20_000 && t < 26_200;

  const screening: AgentCard =
    t < 8_000
      ? { id: "screen", name: "Screening bot", status: "idle", detail: "Waiting for a role." }
      : t < 20_000
        ? { id: "screen", name: "Screening bot", status: "working", detail: "Scoring CVs from BrowseJobs, your Excel, and email." }
        : { id: "screen", name: "Screening bot", status: "idle", detail: "142 scored · 18 above 80%." };

  const call: AgentCard = speakingId
    ? {
        id: "call",
        name: "Call bot",
        status: "working",
        detail: "Live call",
        live: {
          who: PEOPLE.find((p) => p.id === speakingId)?.name ?? "Sample",
          elapsed: clock(t - (speakingId === "rahul" ? 27_000 : 36_000)),
          line: latestLine(speakingId, t),
        },
      }
    : t >= 20_000 && t < 26_200
      ? {
          id: "call",
          name: "Call bot",
          status: autonomous ? "working" : "waiting",
          detail: autonomous ? "Autonomous is on. Calls start without a pause." : "Waiting for HR to approve outreach.",
        }
      : t >= 26_200 && t < 48_000
        ? { id: "call", name: "Call bot", status: "working", detail: "Working the shortlist." }
        : { id: "call", name: "Call bot", status: "idle", detail: t >= 48_000 ? "6 calls finished. 4 interested." : "Idle." };

  const interview: AgentCard =
    t >= 48_000 && t < 66_000
      ? { id: "interview", name: "Interview bot", status: "working", detail: t < 58_000 ? "L1 sitting in progress." : "L2 sitting in progress." }
      : {
          id: "interview",
          name: "Interview bot",
          status: "idle",
          detail: t >= 66_000 ? "L1 and L2 graded." : "Idle until calls finish.",
        };

  const scheduler: AgentCard =
    t >= 66_000 && t < 72_000
      ? { id: "schedule", name: "Scheduler", status: "working", detail: "Interviewer is choosing Tue 11:00." }
      : {
          id: "schedule",
          name: "Scheduler",
          status: "idle",
          detail: t >= 72_000 ? "Tue 11:00 booked. Link sent." : "Idle.",
        };

  const bgv: AgentCard =
    t >= 73_000 && t < 79_000
      ? { id: "bgv", name: "BGV bot", status: "working", detail: "Employment history check running." }
      : { id: "bgv", name: "BGV bot", status: "idle", detail: t >= 79_000 ? "Clear. Nothing failed." : "Idle." };

  const engage: AgentCard =
    t >= 84_000
      ? {
          id: "engage",
          name: "Engagement bot",
          status: "working",
          detail: t >= 86_000 ? "Dropout risk 74 on Sample Rahul. HR alerted." : "Checking in before joining day.",
        }
      : { id: "engage", name: "Engagement bot", status: "idle", detail: "Starts after the offer." };

  const activePhase: PhaseId =
    t < 6_800 ? "job" : t < 26_200 ? "sourcing" : t < 48_000 ? "calls" : t < 58_000 ? "l1" : t < 66_000 ? "l2" : t < 73_000 ? "human" : t < 79_000 ? "bgv" : t < 84_000 ? "offer" : "engage";

  return {
    elapsed: t,
    jobLive: t >= 6_800,
    approvalVisible,
    feed: FEED.filter((item) => item.at <= t).slice(-8).reverse(),
    chat: CHAT.filter((line) => line.at <= t),
    agents: [screening, call, interview, scheduler, bgv, engage],
    speakingId,
    activePhase,
    dropoutAlert: t >= 86_000,
  };
}

export function personPhase(person: Person, t: number): PhaseId | null {
  return phaseOf(person, t);
}

export function callTranscript(id: string, t: number): readonly { at: number; text: string }[] {
  return (CALL_LINES[id] ?? []).filter((line) => line.at <= t);
}

export { clock };
